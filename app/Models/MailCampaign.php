<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MailCampaign extends Model
{
    protected $fillable = [
        'user_id',
        'mail_template_id',
        'subject',
        'recipient_count',
        'sent_count',
        'failed_count',
        'status',
        'last_error',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MailTemplate::class, 'mail_template_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(MailCampaignRecipient::class, 'mail_campaign_id');
    }
}
