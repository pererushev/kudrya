<?php

namespace App\Enums;

enum LedgerAccount: string
{
    case PlatformCash = 'platform_cash';
    case CustomerClearing = 'customer_clearing';
    case CogsCodes = 'cogs_codes';
}
