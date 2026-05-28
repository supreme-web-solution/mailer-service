<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mail_campaign_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mail_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mail_contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('name')->nullable();
            $table->string('status', 32)->default('pending');
            $table->string('provider_message_id')->nullable();
            $table->string('unsubscribe_token', 80)->unique();
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->unique(['mail_campaign_id', 'email']);
            $table->index(['mail_campaign_id', 'status']);
            $table->index(['user_id', 'email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mail_campaign_recipients');
    }
};
