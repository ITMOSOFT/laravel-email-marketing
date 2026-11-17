<?php

namespace ItmoSoft\EmailMarketing\Models;

use Illuminate\Database\Eloquent\Model;
use ItmoSoft\EmailMarketing\Models\Concerns\UsesConfiguredTable;

class EmailTemplate extends Model
{
    use UsesConfiguredTable;

    protected $guarded = [];

    protected function tableConfigKey(): string { return 'templates'; }
}
