<?php

namespace App\Http\Controllers\Mailer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mailer\ContactImportRequest;
use App\Models\MailContact;
use App\Models\MailContactBatch;
use App\Models\MailSuppression;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
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
        $emails = collect($this->extractEmailsFromText((string) $request->input('emails_text', '')));

        if ($request->hasFile('csv_file')) {
            $fileContent = file_get_contents((string) $request->file('csv_file')?->getRealPath());
            if (is_string($fileContent) && trim($fileContent) !== '') {
                $emails = $emails->merge($this->extractEmailsFromCsv($fileContent));
            }
        }

        $emails = $emails
            ->map(fn (string $email): string => strtolower(trim($email)))
            ->filter()
            ->unique()
            ->values();

        if ($emails->isEmpty()) {
            return back()->withErrors(['emails_text' => 'Please provide at least one valid email.']);
        }

        $validEmails = $emails->filter(fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false);
        if ($validEmails->isEmpty()) {
            return back()->withErrors(['emails_text' => 'No valid email addresses were found.']);
        }

        $user = $request->user();
        $created = 0;
        $contactIds = [];
        $batchName = trim((string) $request->input('batch_name', ''));
        $batch = $batchName === '' ? null : $user?->mailContactBatches()->firstOrCreate(['name' => $batchName]);

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

        return back()->with('status', "contacts-imported:{$created}");
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

    /**
     * @return array<int, string>
     */
    private function extractEmailsFromText(string $input): array
    {
        if (trim($input) === '') {
            return [];
        }

        preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $input, $matches);

        return array_values($matches[0] ?? []);
    }

    /**
     * @return array<int, string>
     */
    private function extractEmailsFromCsv(string $csv): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $csv) ?: [];
        $emails = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            foreach (str_getcsv($line) as $cell) {
                $emails = [...$emails, ...$this->extractEmailsFromText((string) $cell)];
            }
        }

        return $emails;
    }
}
