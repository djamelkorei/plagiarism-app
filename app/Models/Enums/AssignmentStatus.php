<?php

namespace App\Models\Enums;

enum AssignmentStatus: string
{
    const PENDING = 'PENDING';
    const PROCESSING = 'PROCESSING';
    const COMPLETED = 'COMPLETED';
    const IGNORED = 'IGNORED';
}
