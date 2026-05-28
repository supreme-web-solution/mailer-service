<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Mailer\CampaignController;
use App\Http\Controllers\Mailer\ContactController;
use App\Http\Controllers\Mailer\MailTemplateController;
use App\Http\Controllers\Mailer\ResendWebhookController;
use App\Http\Controllers\Mailer\UnsubscribeController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');
Route::get('/unsubscribe/{token}', UnsubscribeController::class)->name('mailer.unsubscribe');
Route::post('/webhooks/resend', ResendWebhookController::class)->name('mailer.webhooks.resend');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::prefix('mailer')->name('mailer.')->group(function (): void {
        Route::get('templates', [MailTemplateController::class, 'index'])->name('templates.index');
        Route::post('templates', [MailTemplateController::class, 'store'])->name('templates.store');
        Route::patch('templates/{template}', [MailTemplateController::class, 'update'])->name('templates.update');
        Route::delete('templates/{template}', [MailTemplateController::class, 'destroy'])->name('templates.destroy');

        Route::get('contacts', [ContactController::class, 'index'])->name('contacts.index');
        Route::get('contacts/batches/{batch}', [ContactController::class, 'showBatch'])->name('contacts.batches.show');
        Route::delete('contacts/batches/{batch}/unsubscribed/{suppression}', [ContactController::class, 'destroyBatchSuppression'])
            ->name('contacts.batches.unsubscribed.destroy');
        Route::delete('contacts/batches/{batch}/unsubscribed', [ContactController::class, 'bulkDestroyBatchSuppressions'])
            ->name('contacts.batches.unsubscribed.bulk-destroy');
        Route::post('contacts/import', [ContactController::class, 'store'])->name('contacts.import');
        Route::post('contacts/batches', [ContactController::class, 'storeBatch'])->name('contacts.batches.store');
        Route::delete('contacts/{contact}', [ContactController::class, 'destroy'])->name('contacts.destroy');

        Route::get('send', [CampaignController::class, 'create'])->name('send.create');
        Route::post('send', [CampaignController::class, 'store'])->name('send.store');
    });
});

require __DIR__.'/settings.php';
