<?php

namespace App\Models\Enums;

enum AssignmentStatus: string
{
    const WAITING = 'WAITING';
    const PENDING = 'PENDING';
    const COMPLETED = 'COMPLETED';
    const IGNORED = 'IGNORED';
}
