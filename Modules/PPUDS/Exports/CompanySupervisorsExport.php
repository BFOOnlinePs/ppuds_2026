<?php

namespace Modules\PPUDS\Exports;

use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Modules\Core\Entities\User;
use Modules\PPUDS\Entities\StudentCompany;

/**
 * صف لكل مشرف شركة كما في شاشة مشرفي الشركات، مع تفاصيل مقاعده
 * (شركة ← فرع ← قسم) وطلابه. المقاعد والطلاب تُقرأ باستعلامين مجمّعين
 * لكل المشرفين بدل استعلام لكل صف.
 */
class CompanySupervisorsExport implements FromGenerator, ShouldAutoSize, WithHeadings
{
    /**
     * user id => مقاعده في branch_department مع أسماء الشركة والفرع والقسم.
     *
     * @var array<int, Collection<int, object>>
     */
    protected array $assignments = [];

    /**
     * user id => "branch_id-department_id" => student id => اسم الطالب في ذلك المقعد.
     *
     * @var array<int, array<string, array<int, string>>>
     */
    protected array $students = [];

    public function __construct(protected Builder $query) {}

    public function headings(): array
    {
        return [
            __('Name'),
            __('Email'),
            __('Phone'),
            __('Companies'),
            __('Branches'),
            __('Assigned Departments'),
            __('Supervised Students Count'),
            __('Supervised Students'),
            __('Status'),
            __('Created At'),
        ];
    }

    public function generator(): Generator
    {
        $userIds = (clone $this->query)->reorder()->pluck('users.id')->all();

        $this->assignments = $this->loadAssignments($userIds);
        $this->students = $this->loadStudents($userIds);

        foreach ((clone $this->query)->lazy(200) as $supervisor) {
            yield $this->rowFor($supervisor);
        }
    }

    protected function rowFor(User $supervisor): array
    {
        $assignments = $this->assignments[(int) $supervisor->id] ?? collect();
        $students = $this->students[(int) $supervisor->id] ?? [];

        $departments = $assignments
            ->map(fn (object $row): string => sprintf(
                '%s › %s (%d)',
                $row->branch_name ?? '—',
                $row->department_name ?? '—',
                count($students[$row->branch_id.'-'.$row->company_department_id] ?? []),
            ))
            ->implode(' | ');

        // بالمفتاح لا بالاسم: الطالب في أكثر من مقعد يُعدّ مرة واحدة، والأسماء المتشابهة لا تندمج.
        $studentNames = collect(array_replace([], ...array_values($students)));

        return [
            (string) ($supervisor->name ?? '---'),
            (string) ($supervisor->email ?? '---'),
            (string) ($supervisor->phone ?? '---'),
            $this->distinctValues($assignments, 'company_name'),
            $this->distinctValues($assignments, 'branch_name'),
            $departments !== '' ? $departments : '---',
            (string) $studentNames->count(),
            $studentNames->isNotEmpty() ? $studentNames->implode(', ') : '---',
            $supervisor->trashed() ? __('Deleted') : __('Active'),
            (string) ($supervisor->created_at?->format('Y-m-d H:i') ?? '---'),
        ];
    }

    protected function distinctValues(Collection $assignments, string $field): string
    {
        $values = $assignments->pluck($field)->filter()->unique()->implode(', ');

        return $values !== '' ? $values : '---';
    }

    /**
     * نفس انضمامات assignmentRows في شاشة مشرفي الشركات، لكن لكل المشرفين دفعة واحدة.
     *
     * @return array<int, Collection<int, object>>
     */
    protected function loadAssignments(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $prefix = config('ppuds.table_prefix');
        $locale = app()->getLocale();

        return DB::table($prefix.'branch_department as bd')
            ->whereIn('bd.user_id', $userIds)
            ->leftJoin('branch_branch_translations as bt', function ($join) use ($locale): void {
                $join->on('bt.branch_id', '=', 'bd.branch_id')->where('bt.locale', '=', $locale);
            })
            ->leftJoin($prefix.'company_department_translations as dt', function ($join) use ($locale): void {
                $join->on('dt.department_id', '=', 'bd.company_department_id')->where('dt.locale', '=', $locale);
            })
            ->leftJoin($prefix.'branch_company as bc', 'bc.branch_id', '=', 'bd.branch_id')
            ->leftJoin($prefix.'company_translations as ct', function ($join) use ($locale): void {
                $join->on('ct.company_id', '=', 'bc.company_id')->where('ct.locale', '=', $locale);
            })
            ->select([
                'bd.user_id',
                'bd.branch_id',
                'bd.company_department_id',
                'bt.name as branch_name',
                'dt.name as department_name',
                'ct.name as company_name',
            ])
            ->get()
            ->groupBy('user_id')
            ->mapWithKeys(fn (Collection $rows, $userId): array => [(int) $userId => $rows->values()])
            ->all();
    }

    /**
     * الطالب يتبع المشرف عبر مقعد (فرع + قسم)، بنفس شرط supervisedStudentsCount في الشاشة.
     *
     * @return array<int, array<string, array<int, string>>>
     */
    protected function loadStudents(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $prefix = config('ppuds.table_prefix');
        $table = (new StudentCompany)->getTable();

        return StudentCompany::query()
            ->join($prefix.'branch_department as bd', function ($join) use ($table): void {
                $join->on('bd.branch_id', '=', "{$table}.branch_id")
                    ->on('bd.company_department_id', '=', "{$table}.department_id");
            })
            ->join('users as students', 'students.id', '=', "{$table}.student_id")
            ->whereIn('bd.user_id', $userIds)
            ->whereNotNull("{$table}.branch_id")
            ->whereNotNull("{$table}.department_id")
            ->distinct()
            ->toBase()
            ->get([
                'bd.user_id',
                'bd.branch_id',
                'bd.company_department_id',
                "{$table}.student_id",
                'students.name as student_name',
            ])
            ->groupBy('user_id')
            ->map(fn (Collection $rows): array => $rows
                ->groupBy(fn (object $row): string => $row->branch_id.'-'.$row->company_department_id)
                ->map(fn (Collection $seat): array => $seat->unique('student_id')->pluck('student_name', 'student_id')->all())
                ->all())
            ->mapWithKeys(fn (array $seats, $userId): array => [(int) $userId => $seats])
            ->all();
    }
}
