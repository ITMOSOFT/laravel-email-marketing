<?php

namespace ItmoSoft\EmailMarketing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use ItmoSoft\EmailMarketing\Models\Concerns\UsesConfiguredTable;

class Audience extends Model
{
    use UsesConfiguredTable;

    protected $guarded = [];

    protected function tableConfigKey(): string { return 'audiences'; }

    public function subscribers(): HasMany
    {
        return $this->hasMany(config('itmosoft-email-marketing.models.subscriber'), 'audience_id');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(config('itmosoft-email-marketing.models.campaign'), 'audience_id');
    }
}
