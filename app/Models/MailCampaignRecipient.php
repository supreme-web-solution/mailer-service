<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailCampaignRecipient extends Model
{
    protected $fillable = [
        'mail_campaign_id',
        'mail_contact_id',
        'user_id',
        'email',
        'name',
        'status',
        'provider_message_id',
        'unsubscribe_token',
        'last_error',
        'sent_at',
        'failed_at',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MailCampaign::class, 'mail_campaign_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(MailContact::class, 'mail_contact_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
