<?php

namespace ItmoSoft\EmailMarketing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use ItmoSoft\EmailMarketing\Enums\SubscriberStatus;
use ItmoSoft\EmailMarketing\Models\Concerns\UsesConfiguredTable;

class Subscriber extends Model
{
    use UsesConfiguredTable;

    protected $guarded = [];

    protected function tableConfigKey(): string { return 'subscribers'; }

    protected function casts(): array
    {
        return ['status' => SubscriberStatus::class, 'consented_at' => 'datetime', 'unsubscribed_at' => 'datetime', 'attributes' => 'array'];
    }

    public function audience(): BelongsTo
    {
        return $this->belongsTo(config('itmosoft-email-marketing.models.audience'), 'audience_id');
    }

    public function isMarketable(): bool
    {
        return $this->status === SubscriberStatus::Subscribed && $this->consented_at !== null;
    }
}
