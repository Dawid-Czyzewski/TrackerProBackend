<?php

namespace App\Enum;

enum ApplicationStatus: string
{
    case APPLIED = 'applied';
    case RECRUITMENT_TASK = 'recruitment_task';
    case INTERVIEW = 'interview';
    case GOT_JOB = 'got_job';
    case REJECTED = 'rejected';
    case NO_RESPONSE = 'no_response';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
