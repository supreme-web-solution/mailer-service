<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MailContact extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'email',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function batches(): BelongsToMany
    {
        return $this->belongsToMany(
            MailContactBatch::class,
            'mail_contact_batch_members',
            'mail_contact_id',
            'mail_contact_batch_id'
        )->withTimestamps();
    }
}
