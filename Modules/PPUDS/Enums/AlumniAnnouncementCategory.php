<?php

namespace Modules\PPUDS\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AlumniAnnouncementCategory: string implements HasLabel, HasColor
{
    case JOB_OPENINGS = 'job_openings';
    case JOB_FAIRS = 'job_fairs';
    case ALUMNI_MEETINGS = 'alumni_meetings';
    case MENTORING_ACTIVITIES = 'mentoring_activities';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::JOB_OPENINGS => __('Job Openings'),
            self::JOB_FAIRS => __('Job Fairs'),
            self::ALUMNI_MEETINGS => __('Alumni Meetings'),
            self::MENTORING_ACTIVITIES => __('Mentoring Activities'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::JOB_OPENINGS => 'success',
            self::JOB_FAIRS => 'warning',
            self::ALUMNI_MEETINGS => 'info',
            self::MENTORING_ACTIVITIES => 'primary',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])->toArray();
    }
}
