<?php

namespace ItmoSoft\EmailMarketing\Enums;

enum CampaignStatus: string
{
    case Draft = 'draft';
    case Queued = 'queued';
    case Sending = 'sending';
    case Sent = 'sent';
    case Cancelled = 'cancelled';
}
