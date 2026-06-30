<?php

namespace Modules\PPUDS\Livewire\Pages\StudentAttendanceReport;

use App\View\Components\AppLayout;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\InfoAction;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\StudentReport;
use Modules\PPUDS\Support\ScopesStudentCompanyVisibility;

class Index extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;
    use ScopesStudentCompanyVisibility;

    public array $filters = [];

    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                // 1. أخذ نسخة من الفلاتر للتعامل معها بأمان دون التأثير على حالة Livewire
                $filtersData = $this->filters;

                $query = StudentReport::query()->with([
                    'studentAttendance.studentCompany.student.media',
                ]);

                $query->whereHas(
                    'studentAttendance.studentCompany',
                    fn ($studentCompanyQuery) => $this->applyStudentCompanyVisibilityScope($studentCompanyQuery)
                );

                // 2. تطبيق فلتر الشركة من خلال العلاقة إذا كان موجوداً
                if (isset($filtersData['student_company_id'])) {
                    $companyId = $filtersData['student_company_id'];
                    $query->whereHas('studentAttendance', function ($q) use ($companyId) {
                        $q->where('student_company_id', $companyId);
                    });

                    // حذف الفلتر بعد استخدامه كي لا يضرب الجدول الرئيسي
                    unset($filtersData['student_company_id']);
                }

                if (isset($filtersData['student_id'])) {
                    $studentId = $filtersData['student_id'];
                    $query->whereHas('studentAttendance.studentCompany', function ($q) use ($studentId) {
                        $q->where('student_id', $studentId);
                    });

                    unset($filtersData['student_id']);
                }

                // 3. تطبيق أي فلاتر أخرى إن وجدت
                if (!empty($filtersData)) {
                    $query->where($filtersData);
                }

                return $query;
            })
            ->columns([
                TextColumn::make('studentAttendance.studentCompany.student.name')
                    ->label(__('Student'))
                    ->getStateUsing(fn (StudentReport $record): HtmlString|string => $this->studentDisplayColumnState($record))
                    ->html()
                    ->url(fn (StudentReport $record): ?string => $this->studentDetailsUrl($record))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('report_text')
                    ->label(__('Report Text'))
                    ->html() // ترجمة أكواد HTML
                    ->limit(40)
                    ->wrap(),

                TextColumn::make('company_feedback')
                    ->label(__('Company Feedback'))
                    ->html() // ترجمة أكواد HTML
                    ->limit(40)
                    ->wrap(),

                TextColumn::make('academic_feedback')
                    ->label(__('Academic Feedback'))
                    ->html() // ترجمة أكواد HTML
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('submit_lattitude')
                    ->label(__('Location'))
                    ->formatStateUsing(fn () => __('View Map'))
                    ->icon('heroicon-m-map-pin')
                    ->color('primary')
                    ->url(fn ($record) => "https://www.google.com/maps/search/?api=1&query={$record->submit_lattitude},{$record->submit_longitude}")
                    ->openUrlInNewTab(), // رابط صحيح لخرائط جوجل
            ])
            ->filters($this->getTableFilters(), layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(5)
            ->actions($this->getTableActions())
            ->headerActions([
                // إخفاء زر الإنشاء من هنا لأن التقارير يتم إنشاؤها غالباً من شاشة الحضور
                // \Modules\Core\Filament\Forms\Components\CreateAction::make('create')
                //    ->label(__('Add Report'))
                //    ->url(route('student-reports.add'))
                //    ->visible(fn() => auth()->user()->can('StudentReport Create')),
            ])
            ->bulkActions($this->getTableBulkAction());
    }

    protected function studentDisplayColumnState(StudentReport $record): HtmlString|string
    {
        return $record->studentAttendance?->studentCompany?->student?->getUserDisplayHtmlAttribute() ?? '---';
    }

    protected function studentDetailsUrl(StudentReport $record): ?string
    {
        $student = $record->studentAttendance?->studentCompany?->student;

        if (! $student || ! auth()->user()?->can('Student Details List')) {
            return null;
        }

        return route('students.details', $student->id);
    }

    protected function getTableFilters(): array
    {
        return [
            Filter::make('submitted_date')
                ->label(__('Submitted At'))
                ->form([
                    DatePicker::make('from')
                        ->label(__('From Date'))
                        ->native(false)
                        ->format('Y-m-d')
                        ->displayFormat('Y-m-d'),

                    DatePicker::make('until')
                        ->label(__('Until Date'))
                        ->native(false)
                        ->format('Y-m-d')
                        ->displayFormat('Y-m-d'),
                ])
                ->columns(2)
                ->query(fn (Builder $query, array $data): Builder => $this->applyDateRangeFilter($query, $data, 'created_at'))
                ->indicateUsing(fn (array $data): array => $this->dateRangeIndicators($data)),

            Filter::make('attendance_date')
                ->label(__('Attendance Date'))
                ->form([
                    DatePicker::make('from')
                        ->label(__('From Date'))
                        ->native(false)
                        ->format('Y-m-d')
                        ->displayFormat('Y-m-d'),

                    DatePicker::make('until')
                        ->label(__('Until Date'))
                        ->native(false)
                        ->format('Y-m-d')
                        ->displayFormat('Y-m-d'),
                ])
                ->columns(2)
                ->query(fn (Builder $query, array $data): Builder => $query->whereHas(
                    'studentAttendance',
                    fn (Builder $attendanceQuery): Builder => $this->applyDateRangeFilter($attendanceQuery, $data, 'attendance_date')
                ))
                ->indicateUsing(fn (array $data): array => $this->dateRangeIndicators($data)),

            SelectFilter::make('company_id')
                ->label(__('Company'))
                ->options(fn (): array => $this->reportCompanyOptions())
                ->searchable()
                ->preload()
                ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                    ? $query->whereHas(
                        'studentAttendance.studentCompany',
                        fn (Builder $studentCompanyQuery): Builder => $studentCompanyQuery->where('company_id', $data['value'])
                    )
                    : $query),

            Filter::make('feedback_status')
                ->label(__('Report Status'))
                ->form([
                    Select::make('status')
                        ->label(__('Report Status'))
                        ->options([
                            'with_company_feedback' => __('Company Feedback').' - '.__('Yes'),
                            'without_company_feedback' => __('Company Feedback').' - '.__('No'),
                            'with_academic_feedback' => __('Academic Feedback').' - '.__('Yes'),
                            'without_academic_feedback' => __('Academic Feedback').' - '.__('No'),
                        ])
                        ->native(false),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return match ($data['status'] ?? null) {
                        'with_company_feedback' => $query->whereNotNull('company_feedback')->where('company_feedback', '!=', ''),
                        'without_company_feedback' => $query->where(fn (Builder $query): Builder => $query->whereNull('company_feedback')->orWhere('company_feedback', '')),
                        'with_academic_feedback' => $query->whereNotNull('academic_feedback')->where('academic_feedback', '!=', ''),
                        'without_academic_feedback' => $query->where(fn (Builder $query): Builder => $query->whereNull('academic_feedback')->orWhere('academic_feedback', '')),
                        default => $query,
                    };
                }),
        ];
    }

    protected function applyDateRangeFilter(Builder $query, array $data, string $column): Builder
    {
        return $query
            ->when(
                $data['from'] ?? null,
                fn (Builder $query, $date): Builder => $query->whereDate($column, '>=', Carbon::parse($date)->toDateString())
            )
            ->when(
                $data['until'] ?? null,
                fn (Builder $query, $date): Builder => $query->whereDate($column, '<=', Carbon::parse($date)->toDateString())
            );
    }

    protected function dateRangeIndicators(array $data): array
    {
        $indicators = [];

        if (! empty($data['from'])) {
            $indicators[] = Indicator::make(__('From Date').': '.Carbon::parse($data['from'])->toDateString())
                ->removeField('from');
        }

        if (! empty($data['until'])) {
            $indicators[] = Indicator::make(__('Until Date').': '.Carbon::parse($data['until'])->toDateString())
                ->removeField('until');
        }

        return $indicators;
    }

    protected function reportCompanyOptions(): array
    {
        return Company::query()
            ->with('translations')
            ->whereHas('studentCompanies', function (Builder $query): void {
                $this->applyStudentCompanyVisibilityScope($query);

                if (filled($this->filters['student_id'] ?? null)) {
                    $query->where('student_id', $this->filters['student_id']);
                }

                if (filled($this->filters['student_company_id'] ?? null)) {
                    $query->whereKey($this->filters['student_company_id']);
                }
            })
            ->orderBy('id')
            ->get()
            ->pluck('name', 'id')
            ->toArray();
    }

    protected function getTableActions(): array
    {
        return [
            InfoAction::make('info')
                ->label('')
                ->visible(fn() => auth()->user()->can('StudentReport Info')),

            ViewAction::make('view')
                ->label('')
                ->tooltip(__('View Details'))
                ->modalHeading(__('Report Details'))
                ->form(fn(StudentReport $record) => [
                    Grid::make(1)->schema([
                        RichEditor::make('report_text')
                            ->label(__('Report Text'))
                            ->default($record->report_text)
                            ->disabled()
                            ->toolbarButtons([]),

                        RichEditor::make('company_feedback')
                            ->label(__('Company Feedback'))
                            ->default($record->company_feedback)
                            ->disabled()
                            ->toolbarButtons([]),

                        RichEditor::make('academic_feedback')
                            ->label(__('Academic Feedback'))
                            ->default($record->academic_feedback)
                            ->disabled()
                            ->toolbarButtons([]),
                    ]),
                ])
                ->modalSubmitAction(false) // إخفاء زر الحفظ لأنها شاشة عرض فقط
                ->visible(fn() => auth()->user()->can('StudentReport View')), // تعديل الصلاحية

            DeleteAction::make('delete')
                ->label('')
                ->tooltip(__('Delete'))
                ->action(function (StudentReport $record) { // تعديل نوع الموديل
                    $record->delete();
                    Toaster::success(__('Report deleted successfully'));
                })
                ->visible(fn() => auth()->user()->can('StudentReport Delete')), // تعديل الصلاحية
        ];
    }

    public function getTableBulkAction(): array
    {
        return [
            BulkActionGroup::make([
                BulkAction::make('delete')
                    ->label(__('Delete Selected'))
                    ->icon('solar-trash-bin-trash-bold-duotone')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn(Collection $records) => $records->each->delete())
                    ->after(fn() => Toaster::success(__('Selected records deleted successfully')))
                    ->visible(fn() => auth()->user()->can('StudentReport Delete')),
            ]),
        ];
    }

    public function render()
    {
        // 🌟 تعديل مسار العرض ليتوافق مع التقارير
        return view('ppuds::livewire.pages.student-company.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Attendance Reports'), 'url' => '#'], // تعديل العناوين
            ],
        ]);
    }
}
