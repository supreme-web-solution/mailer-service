<?php

namespace App\Http\Controllers\Mailer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mailer\MailTemplateStoreRequest;
use App\Models\MailTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MailTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $templates = $request->user()?->mailTemplates()
            ->latest()
            ->paginate(10, ['id', 'name', 'subject', 'body', 'updated_at'])
            ->through(fn (MailTemplate $template) => [
                'id' => $template->id,
                'name' => $template->name,
                'subject' => $template->subject,
                'body' => $template->body,
                'updated_at' => $template->updated_at->toDateTimeString(),
            ])
            ->withQueryString();

        return Inertia::render('mailer/Templates', [
            'templates' => $templates,
            'status' => session('status'),
        ]);
    }

    public function store(MailTemplateStoreRequest $request): RedirectResponse
    {
        $request->user()?->mailTemplates()->create($request->validated());

        return back()->with('status', 'template-created');
    }

    public function update(MailTemplateStoreRequest $request, MailTemplate $template): RedirectResponse
    {
        abort_unless($template->user_id === $request->user()?->id, 404);

        $template->update($request->validated());

        return back()->with('status', 'template-updated');
    }

    public function destroy(Request $request, MailTemplate $template): RedirectResponse
    {
        abort_unless($template->user_id === $request->user()?->id, 404);
        $template->delete();

        return back()->with('status', 'template-deleted');
    }
}
