<?php

namespace ItmoSoft\EmailMarketing\Enums;

enum SubscriberStatus: string
{
    case Pending = 'pending';
    case Subscribed = 'subscribed';
    case Unsubscribed = 'unsubscribed';
    case Bounced = 'bounced';
}
