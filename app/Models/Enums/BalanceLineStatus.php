<?php

namespace App\Models\Enums;

enum BalanceLineStatus: string
{
    const PENDING = 'PENDING';
    const APPROVED = 'APPROVED';
    const REFUSED = 'REFUSED';
}
