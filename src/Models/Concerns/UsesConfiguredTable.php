<?php

namespace ItmoSoft\EmailMarketing\Models\Concerns;

trait UsesConfiguredTable
{
    abstract protected function tableConfigKey(): string;

    public function getTable(): string
    {
        $key = $this->tableConfigKey();

        return config('itmosoft-email-marketing.table_prefix', 'email_marketing_')
            .config("itmosoft-email-marketing.tables.{$key}", $key);
    }
}
