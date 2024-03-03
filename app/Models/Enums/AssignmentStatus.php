<?php

namespace App\Models\Enums;

enum AssignmentStatus: string
{
    const PENDING = 'PENDING';
    const COMPLETED = 'COMPLETED';
    const IGNORED = 'IGNORED';
}
