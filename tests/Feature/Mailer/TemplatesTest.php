<?php

namespace Tests\Feature\Mailer;

use App\Models\MailTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_update_and_delete_templates(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post('/mailer/templates', [
            'name' => 'Launch',
            'subject' => 'Hello world',
            'body' => '<p>Body</p>',
        ])->assertRedirect();

        $template = MailTemplate::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('Launch', $template->name);

        $this->patch('/mailer/templates/'.$template->id, [
            'name' => 'Launch v2',
            'subject' => 'Updated subject',
            'body' => '<p>Updated</p>',
        ])->assertRedirect();

        $template->refresh();
        $this->assertSame('Launch v2', $template->name);

        $this->delete('/mailer/templates/'.$template->id)
            ->assertRedirect();

        $this->assertDatabaseMissing('mail_templates', ['id' => $template->id]);
    }
}
