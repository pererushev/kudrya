<?php

namespace App\Enums;

enum FulfillmentStatus: string
{
    case Pending = 'pending';
    case Unknown = 'unknown';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
