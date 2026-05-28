<?php

namespace Tests\Feature\Mailer;

use App\Models\MailContact;
use App\Models\MailContactBatch;
use App\Models\MailSuppression;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactsTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_import_accepts_comma_and_newline_separated_emails(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post('/mailer/contacts/import', [
            'emails_text' => "one@example.com,\ntwo@example.com three@example.com",
        ])->assertRedirect();

        $this->assertDatabaseCount('mail_contacts', 3);
        $this->assertDatabaseHas('mail_contacts', [
            'user_id' => $user->id,
            'email' => 'one@example.com',
        ]);
    }

    public function test_contact_import_can_create_and_attach_batch(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post('/mailer/contacts/import', [
            'emails_text' => "batch-one@example.com\nbatch-two@example.com",
            'batch_name' => 'May Campaign',
        ])->assertRedirect();

        $this->assertDatabaseHas('mail_contact_batches', [
            'user_id' => $user->id,
            'name' => 'May Campaign',
        ]);

        $this->assertDatabaseCount('mail_contact_batch_members', 2);
    }

    public function test_store_batch_can_group_existing_contacts(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $first = MailContact::query()->create([
            'user_id' => $user->id,
            'email' => 'first@example.com',
            'is_active' => true,
        ]);
        $second = MailContact::query()->create([
            'user_id' => $user->id,
            'email' => 'second@example.com',
            'is_active' => true,
        ]);

        $this->post('/mailer/contacts/batches', [
            'batch_name' => 'Weekly list',
            'contact_ids' => [$first->id, $second->id],
        ])->assertRedirect();

        $this->assertDatabaseHas('mail_contact_batches', [
            'user_id' => $user->id,
            'name' => 'Weekly list',
        ]);
        $this->assertDatabaseCount('mail_contact_batch_members', 2);
    }

    public function test_bulk_delete_unsubscribed_for_batch_removes_only_selected_records(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $batch = MailContactBatch::query()->create([
            'user_id' => $user->id,
            'name' => 'Batch',
        ]);

        $contact = MailContact::query()->create([
            'user_id' => $user->id,
            'email' => 'in-batch@example.com',
            'is_active' => true,
        ]);
        $batch->contacts()->sync([$contact->id]);

        $inBatchSuppression = MailSuppression::query()->create([
            'user_id' => $user->id,
            'email' => 'in-batch@example.com',
            'reason' => 'unsubscribed',
        ]);
        $otherSuppression = MailSuppression::query()->create([
            'user_id' => $user->id,
            'email' => 'other@example.com',
            'reason' => 'unsubscribed',
        ]);

        $this->delete("/mailer/contacts/batches/{$batch->id}/unsubscribed", [
            'suppression_ids' => [$inBatchSuppression->id, $otherSuppression->id],
        ])->assertRedirect();

        $this->assertDatabaseMissing('mail_suppressions', ['id' => $inBatchSuppression->id]);
        $this->assertDatabaseHas('mail_suppressions', ['id' => $otherSuppression->id]);
    }
}
