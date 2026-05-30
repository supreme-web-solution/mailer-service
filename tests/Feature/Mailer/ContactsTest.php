<?php

namespace Tests\Feature\Mailer;

use App\Models\MailContact;
use App\Models\MailContactBatch;
use App\Models\MailSuppression;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use ZipArchive;

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

    public function test_contact_import_accepts_xlsx_upload(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $xlsxPath = $this->createImportXlsx([
            ['Lead', 'spreadsheet@example.com'],
        ]);

        $this->post('/mailer/contacts/import', [
            'batch_name' => 'Excel Batch',
            'csv_file' => new UploadedFile(
                $xlsxPath,
                'contacts.xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                null,
                true,
            ),
        ])->assertRedirect();

        @unlink($xlsxPath);

        $this->assertDatabaseHas('mail_contacts', [
            'user_id' => $user->id,
            'email' => 'spreadsheet@example.com',
        ]);
        $this->assertDatabaseHas('mail_contact_batches', [
            'user_id' => $user->id,
            'name' => 'Excel Batch',
        ]);
    }

    public function test_contact_import_can_add_emails_to_existing_batch(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $batch = MailContactBatch::query()->create([
            'user_id' => $user->id,
            'name' => 'Existing Batch',
        ]);

        $this->post("/mailer/contacts/batches/{$batch->id}/import", [
            'emails_text' => 'added@example.com',
        ])->assertRedirect();

        $this->assertDatabaseHas('mail_contacts', [
            'user_id' => $user->id,
            'email' => 'added@example.com',
        ]);
        $this->assertDatabaseHas('mail_contact_batch_members', [
            'mail_contact_batch_id' => $batch->id,
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

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    private function createImportXlsx(array $rows): string
    {
        $sharedStrings = [];
        $sharedStringIndex = [];

        $resolveSharedString = function (string $value) use (&$sharedStrings, &$sharedStringIndex): int {
            if (! array_key_exists($value, $sharedStringIndex)) {
                $sharedStringIndex[$value] = count($sharedStrings);
                $sharedStrings[] = $value;
            }

            return $sharedStringIndex[$value];
        };

        $sheetRows = '';
        foreach ($rows as $rowNumber => $cells) {
            $rowIndex = $rowNumber + 1;
            $sheetCells = '';
            foreach ($cells as $columnIndex => $value) {
                $columnLetter = chr(ord('A') + $columnIndex);
                $sharedIndex = $resolveSharedString($value);
                $sheetCells .= '<c r="'.$columnLetter.$rowIndex.'" t="s"><v>'.$sharedIndex.'</v></c>';
            }
            $sheetRows .= '<row r="'.$rowIndex.'">'.$sheetCells.'</row>';
        }

        $sharedStringsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.count($sharedStrings).'" uniqueCount="'.count($sharedStrings).'">';
        foreach ($sharedStrings as $value) {
            $sharedStringsXml .= '<si><t>'.htmlspecialchars($value, ENT_XML1).'</t></si>';
        }
        $sharedStringsXml .= '</sst>';

        $worksheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetData>'.$sheetRows.'</sheetData>'
            .'</worksheet>';

        $path = tempnam(sys_get_temp_dir(), 'import_');
        $this->assertNotFalse($path);
        @unlink($path);
        $path .= '.xlsx';

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/></Types>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $worksheetXml);
        $zip->addFromString('xl/sharedStrings.xml', $sharedStringsXml);
        $zip->close();

        return $path;
    }
}
