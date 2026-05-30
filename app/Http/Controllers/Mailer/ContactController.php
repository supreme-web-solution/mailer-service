<?php

namespace App\Http\Controllers\Mailer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mailer\ContactImportRequest;
use App\Models\MailContact;
use App\Models\MailContactBatch;
use App\Services\ContactImportEmailExtractor;
use App\Models\MailSuppression;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function __construct(
        private readonly ContactImportEmailExtractor $emailExtractor,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('mailer/Contacts', [
            'batches' => $user?->mailContactBatches()
                ->withCount(['contacts as contacts_count' => function ($query) use ($user): void {
                    $query->where('is_active', true)
                        ->whereNotIn('email', function ($subQuery) use ($user): void {
                            $subQuery->from('mail_suppressions')
                                ->select('email')
                                ->where('user_id', $user?->id);
                        });
                }])
                ->orderBy('name')
                ->paginate(10, ['id', 'name'])
                ->withQueryString(),
            'totalContacts' => (int) ($user?->mailContacts()->where('is_active', true)->count() ?? 0),
            'status' => session('status'),
        ]);
    }

    public function showBatch(Request $request, MailContactBatch $batch): Response
    {
        $user = $request->user();
        abort_unless($batch->user_id === $user?->id, 404);

        $contacts = $batch->contacts()
            ->where('is_active', true)
            ->whereNotIn('email', function ($query) use ($user): void {
                $query->from('mail_suppressions')
                    ->select('email')
                    ->where('user_id', $user?->id);
            })
            ->orderBy('email')
            ->paginate(10, ['mail_contacts.id', 'mail_contacts.name', 'mail_contacts.email'])
            ->through(fn (MailContact $contact) => [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
            ])
            ->withQueryString();

        return Inertia::render('mailer/BatchShow', [
            'batch' => [
                'id' => $batch->id,
                'name' => $batch->name,
                'contacts_count' => (int) $contacts->total(),
            ],
            'contacts' => $contacts,
            'unsubscribed' => MailSuppression::query()
                ->where('user_id', $user?->id)
                ->whereIn('email', function ($query) use ($batch): void {
                    $query->from('mail_contact_batch_members')
                        ->join('mail_contacts', 'mail_contacts.id', '=', 'mail_contact_batch_members.mail_contact_id')
                        ->where('mail_contact_batch_members.mail_contact_batch_id', $batch->id)
                        ->select('mail_contacts.email');
                })
                ->orderByDesc('id')
                ->paginate(10, ['id', 'email', 'reason', 'created_at'])
                ->through(fn (MailSuppression $suppression) => [
                    'id' => $suppression->id,
                    'email' => $suppression->email,
                    'reason' => $suppression->reason,
                    'created_at' => $suppression->created_at?->toDateTimeString(),
                ])
                ->withQueryString(),
            'templates' => $user?->mailTemplates()
                ->orderBy('name')
                ->get(['id', 'name', 'subject'])
                ->all() ?? [],
            'status' => session('status'),
        ]);
    }

    public function destroyBatch(Request $request, MailContactBatch $batch): RedirectResponse
    {
        abort_unless($batch->user_id === $request->user()?->id, 404);

        $batch->delete();

        return redirect()
            ->route('mailer.contacts.index')
            ->with('status', 'batch-deleted');
    }

    public function unsubscribeBatchContact(Request $request, MailContactBatch $batch, MailContact $contact): RedirectResponse
    {
        $user = $request->user();
        abort_unless($batch->user_id === $user?->id, 404);
        abort_unless($contact->user_id === $user?->id, 404);
        abort_unless($this->contactBelongsToBatch($batch, $contact), 404);
        abort_unless($user !== null, 403);

        $this->suppressContact($user->id, $contact->email, 'manual');

        return back()->with('status', 'contact-unsubscribed');
    }

    public function unsubscribeBatchContacts(Request $request, MailContactBatch $batch): RedirectResponse
    {
        $validated = $request->validate([
            'contact_ids' => ['required', 'array', 'min:1'],
            'contact_ids.*' => ['integer'],
        ]);

        $user = $request->user();
        abort_unless($batch->user_id === $user?->id, 404);

        $contacts = $batch->contacts()
            ->where('mail_contacts.user_id', $user?->id)
            ->whereIn('mail_contacts.id', $validated['contact_ids'])
            ->get(['mail_contacts.id', 'mail_contacts.email']);

        if ($contacts->isEmpty()) {
            return back()->withErrors(['contact_ids' => 'Select at least one valid contact.']);
        }

        abort_unless($user !== null, 403);

        foreach ($contacts as $contact) {
            $this->suppressContact($user->id, $contact->email, 'manual');
        }

        return back()->with('status', 'contacts-unsubscribed');
    }

    public function destroyBatchSuppression(Request $request, MailContactBatch $batch, MailSuppression $suppression): RedirectResponse
    {
        $user = $request->user();
        abort_unless($batch->user_id === $user?->id, 404);
        abort_unless($suppression->user_id === $user?->id, 404);

        $belongsToBatch = $batch->contacts()
            ->where('mail_contacts.email', $suppression->email)
            ->exists();

        abort_unless($belongsToBatch, 404);

        $suppression->delete();

        return back()->with('status', 'unsubscribed-deleted');
    }

    public function bulkDestroyBatchSuppressions(Request $request, MailContactBatch $batch): RedirectResponse
    {
        $validated = $request->validate([
            'suppression_ids' => ['required', 'array', 'min:1'],
            'suppression_ids.*' => ['integer'],
        ]);

        $user = $request->user();
        abort_unless($batch->user_id === $user?->id, 404);

        $batchEmailsSubQuery = $batch->contacts()->select('mail_contacts.email');

        MailSuppression::query()
            ->where('user_id', $user?->id)
            ->whereIn('id', $validated['suppression_ids'])
            ->whereIn('email', $batchEmailsSubQuery)
            ->delete();

        return back()->with('status', 'unsubscribed-bulk-deleted');
    }

    public function store(ContactImportRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validEmails = $this->validatedEmailsFromImportRequest($request);
        if ($validEmails instanceof RedirectResponse) {
            return $validEmails;
        }

        $batchName = trim((string) $request->input('batch_name', ''));
        $batch = $batchName === '' ? null : $user?->mailContactBatches()->firstOrCreate(['name' => $batchName]);

        $created = $this->persistImportedContacts($user, $validEmails, $batch);

        return back()->with('status', "contacts-imported:{$created}");
    }

    public function importToBatch(ContactImportRequest $request, MailContactBatch $batch): RedirectResponse
    {
        $user = $request->user();
        abort_unless($batch->user_id === $user?->id, 404);

        $validEmails = $this->validatedEmailsFromImportRequest($request);
        if ($validEmails instanceof RedirectResponse) {
            return $validEmails;
        }

        $created = $this->persistImportedContacts($user, $validEmails, $batch);

        return back()->with('status', "contacts-imported:{$created}");
    }

    private function validatedEmailsFromImportRequest(ContactImportRequest $request): Collection|RedirectResponse
    {
        $emails = collect($this->emailExtractor->fromText((string) $request->input('emails_text', '')));

        if ($request->hasFile('csv_file')) {
            $emails = $emails->merge($this->emailExtractor->fromUploadedFile($request->file('csv_file')));
        }

        $emails = $emails
            ->map(fn (string $email): string => strtolower(trim($email)))
            ->filter()
            ->unique()
            ->values();

        if ($emails->isEmpty()) {
            return back()->withErrors(['emails_text' => 'Please provide at least one valid email.']);
        }

        $validEmails = $emails->filter(fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)->values();
        if ($validEmails->isEmpty()) {
            return back()->withErrors(['emails_text' => 'No valid email addresses were found.']);
        }

        return $validEmails;
    }

    /**
     * @param  Collection<int, string>  $validEmails
     */
    private function persistImportedContacts(?User $user, Collection $validEmails, ?MailContactBatch $batch): int
    {
        $created = 0;
        $contactIds = [];

        foreach ($validEmails as $email) {
            $result = $user?->mailContacts()->updateOrCreate(
                ['email' => $email],
                ['is_active' => true]
            );

            if ($result !== null) {
                $contactIds[] = $result->id;
            }

            if ($result?->wasRecentlyCreated) {
                $created++;
            }
        }

        if ($batch !== null && $contactIds !== []) {
            $batch->contacts()->syncWithoutDetaching($contactIds);
        }

        return $created;
    }

    public function destroy(Request $request, MailContact $contact): RedirectResponse
    {
        abort_unless($contact->user_id === $request->user()?->id, 404);

        $contact->delete();

        return back()->with('status', 'contact-deleted');
    }

    public function storeBatch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'batch_name' => ['required', 'string', 'max:120'],
            'contact_ids' => ['required', 'array', 'min:1'],
            'contact_ids.*' => ['integer'],
        ]);

        $user = $request->user();
        $contactIds = $user?->mailContacts()
            ->whereIn('id', $validated['contact_ids'])
            ->pluck('id')
            ->all() ?? [];

        if ($contactIds === []) {
            return back()->withErrors(['contact_ids' => 'Select at least one valid contact.']);
        }

        $batchName = trim((string) $validated['batch_name']);
        if ($batchName === '') {
            return back()->withErrors(['batch_name' => 'Batch name is required.']);
        }

        $batch = $user?->mailContactBatches()->firstOrCreate([
            'name' => $batchName,
        ]);

        $batch?->contacts()->syncWithoutDetaching($contactIds);

        return back()->with('status', 'batch-saved');
    }

    private function contactBelongsToBatch(MailContactBatch $batch, MailContact $contact): bool
    {
        return $batch->contacts()
            ->whereKey($contact->id)
            ->exists();
    }

    private function suppressContact(int $userId, string $email, string $reason): void
    {
        MailSuppression::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'email' => strtolower($email),
            ],
            [
                'reason' => $reason,
            ]
        );
    }
}
