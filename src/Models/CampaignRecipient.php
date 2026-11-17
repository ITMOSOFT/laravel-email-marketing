<?php

namespace ItmoSoft\EmailMarketing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use ItmoSoft\EmailMarketing\Models\Concerns\UsesConfiguredTable;

class CampaignRecipient extends Model
{
    use UsesConfiguredTable;

    protected $guarded = [];

    protected function tableConfigKey(): string { return 'recipients'; }

    protected function casts(): array
    {
        return ['delivered_at' => 'datetime', 'opened_at' => 'datetime', 'clicked_at' => 'datetime'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(config('itmosoft-email-marketing.models.campaign'), 'campaign_id');
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(config('itmosoft-email-marketing.models.subscriber'), 'subscriber_id');
    }
}
