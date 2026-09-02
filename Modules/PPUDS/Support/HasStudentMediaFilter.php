<?php

namespace Modules\PPUDS\Support;

use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\PPUDS\Entities\FieldVisit;
use Modules\PPUDS\Entities\LeaveRequest;
use Modules\PPUDS\Entities\Note;
use Modules\PPUDS\Entities\Payment;
use Modules\PPUDS\Entities\Registration;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Entities\StudentProfile;
use Modules\PPUDS\Entities\StudentReport;

/**
 * Filters a media library table down to one student. Media is polymorphic, so
 * a student's files are spread over every record tied to them — their own
 * account, their profile, and each record hanging off one of their placements
 * — and this collects all of them behind a single select.
 */
trait HasStudentMediaFilter
{
    protected function studentMediaSelectFilter(): SelectFilter
    {
        return SelectFilter::make('student')
            ->label(__('Student'))
            ->options(fn (): array => $this->studentMediaFilterOptions())
            ->searchable()
            ->preload()
            ->query(fn (Builder $query, array $data): Builder => blank($data['value'] ?? null)
                ? $query
                : $this->applyStudentMediaFilter($query, (int) $data['value']));
    }

    protected function studentMediaFilterOptions(): array
    {
        return User::query()
            ->whereHas(
                'roles',
                fn (Builder $query): Builder => $query->where('name', UserRole::STUDENT->value)
            )
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * Every media row belongs to exactly one model, so this is an OR over the
     * models that can carry a file for the student: the id list per model is
     * kept as a subquery, never loaded into PHP.
     */
    protected function applyStudentMediaFilter(Builder $query, int $studentId): Builder
    {
        $studentCompanyIds = fn (): Builder => StudentCompany::query()
            ->where('student_id', $studentId)
            ->select('id');

        $owners = [
            // The student's own account: avatar and cover photo.
            User::class => [$studentId],
            StudentProfile::class => StudentProfile::query()->where('user_id', $studentId)->select('id'),
            Note::class => Note::query()->where('user_id', $studentId)->select('id'),
            Registration::class => Registration::query()->where('student_id', $studentId)->select('id'),
            StudentCompany::class => $studentCompanyIds(),
            Payment::class => Payment::query()->whereIn('student_company_id', $studentCompanyIds())->select('id'),
            LeaveRequest::class => LeaveRequest::query()->whereIn('student_company_id', $studentCompanyIds())->select('id'),
            FieldVisit::class => FieldVisit::query()->whereIn('student_company_id', $studentCompanyIds())->select('id'),
            StudentReport::class => StudentReport::query()
                ->whereHas(
                    'studentAttendance',
                    fn (Builder $attendance): Builder => $attendance->whereIn('student_company_id', $studentCompanyIds())
                )
                ->select('id'),
        ];

        return $query->where(function (Builder $inner) use ($owners): void {
            foreach ($owners as $model => $ids) {
                $inner->orWhere(fn (Builder $branch): Builder => $branch
                    ->where('model_type', $model)
                    ->whereIn('model_id', $ids));
            }
        });
    }
}
