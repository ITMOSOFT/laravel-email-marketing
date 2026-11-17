<?php

namespace ItmoSoft\EmailMarketing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use ItmoSoft\EmailMarketing\Enums\CampaignStatus;
use ItmoSoft\EmailMarketing\Models\Concerns\UsesConfiguredTable;

class Campaign extends Model
{
    use UsesConfiguredTable;

    protected $guarded = [];

    protected function tableConfigKey(): string { return 'campaigns'; }

    protected function casts(): array
    {
        return ['status' => CampaignStatus::class, 'scheduled_at' => 'datetime', 'sent_at' => 'datetime'];
    }

    public function audience(): BelongsTo
    {
        return $this->belongsTo(config('itmosoft-email-marketing.models.audience'), 'audience_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(config('itmosoft-email-marketing.models.template'), 'template_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(config('itmosoft-email-marketing.models.recipient'), 'campaign_id');
    }
}
