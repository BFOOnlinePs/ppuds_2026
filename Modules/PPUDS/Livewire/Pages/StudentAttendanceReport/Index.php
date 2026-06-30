<?php

namespace Modules\PPUDS\Livewire\Pages\StudentAttendanceReport;

use App\View\Components\AppLayout;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\InfoAction;
use Modules\Core\Filament\Forms\Components\ViewAction;
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
        return $record->studentAttendance?->studentCompany?->student?->user_display_html ?? '---';
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
            // يمكنك إضافة فلاتر الجدول هنا
        ];
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
