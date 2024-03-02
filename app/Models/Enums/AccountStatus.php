<?php

namespace App\Models\Enums;

enum AccountStatus: string
{
    const SUSPENDED = 'SUSPENDED';
    const PENDING = 'PENDING';
    const ACTIVE = 'ACTIVE';
}
