<?php

return [
    'table_prefix' => 'email_marketing_',
    'tables' => [
        'audiences' => 'audiences',
        'subscribers' => 'subscribers',
        'templates' => 'templates',
        'campaigns' => 'campaigns',
        'recipients' => 'recipients',
    ],
    'models' => [
        'audience' => ItmoSoft\EmailMarketing\Models\Audience::class,
        'subscriber' => ItmoSoft\EmailMarketing\Models\Subscriber::class,
        'template' => ItmoSoft\EmailMarketing\Models\EmailTemplate::class,
        'campaign' => ItmoSoft\EmailMarketing\Models\Campaign::class,
        'recipient' => ItmoSoft\EmailMarketing\Models\CampaignRecipient::class,
    ],
];
