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
        Schema::create('mail_contact_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['user_id', 'name']);
        });

        Schema::create('mail_contact_batch_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mail_contact_batch_id')->constrained('mail_contact_batches')->cascadeOnDelete();
            $table->foreignId('mail_contact_id')->constrained('mail_contacts')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['mail_contact_batch_id', 'mail_contact_id'], 'mail_batch_member_unique');
            $table->index('mail_contact_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mail_contact_batch_members');
        Schema::dropIfExists('mail_contact_batches');
    }
};
