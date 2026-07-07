<?php

namespace Modules\PPUDS\Livewire\Pages\StudentAttendance;

use App\View\Components\AppLayout;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Actions\Action;
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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Livewire\Component;
use Maatwebsite\Excel\Excel as WriterType;
use Masmerise\Toaster\Toaster;
use Modules\Branch\Entities\Branch;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\InfoAction;
use Modules\Core\Filament\Forms\Components\MapPicker;
use Modules\Core\Filament\Forms\Components\Textarea;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Modules\Core\Interfaces\ExcelServiceInterface;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\Major;
use Modules\PPUDS\Entities\StudentAttendance;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Entities\StudentReport;
use Modules\PPUDS\Enums\AttendanceStatus;
use Modules\PPUDS\Exports\TodayAbsentStudentsExport;
use Modules\PPUDS\Livewire\Concerns\SearchesStudentAttendanceRelationships;
use Modules\PPUDS\Services\PpudsNotificationService;
use Modules\PPUDS\Settings\GeneralSettings;
use Modules\PPUDS\Support\HasSupervisorFilter;
use Modules\PPUDS\Support\ScopesStudentCompanyVisibility;

class Index extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;
    use SearchesStudentAttendanceRelationships;
    use HasSupervisorFilter;
    use ScopesStudentCompanyVisibility;

    public function table(Table $table)
    {
        return $table
            ->query(fn () => $this->studentAttendanceTableQuery())
            ->columns([
                TextColumn::make('student_number')
                    ->label(__('Student Number'))
                    ->getStateUsing(fn (Model $record): ?string => $this->studentNumberColumnState($record))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $this->todayAbsentStudentsFilterIsActive()
                        ? $this->applyStudentSearchToStudentCompanyQuery($query, $search)
                        : $this->applyStudentSearchToAttendanceQuery($query, $search))
                    ->placeholder('---'),

                TextColumn::make('student_name')
                    ->label(__('Student Name'))
                    ->getStateUsing(fn (Model $record): HtmlString|string => $this->studentDisplayColumnState($record))
                    ->html()
                    ->url(fn (Model $record): ?string => $this->studentDetailsUrl($record))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $this->todayAbsentStudentsFilterIsActive()
                        ? $this->applyStudentSearchToStudentCompanyQuery($query, $search)
                        : $this->applyStudentSearchToAttendanceQuery($query, $search)),

                TextColumn::make('student_phone')
                    ->label(__('Phone'))
                    ->getStateUsing(fn (Model $record): ?string => $this->studentPhoneColumnState($record))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $this->todayAbsentStudentsFilterIsActive()
                        ? $this->applyStudentSearchToStudentCompanyQuery($query, $search)
                        : $this->applyStudentSearchToAttendanceQuery($query, $search))
                    ->placeholder('---'),

                TextColumn::make('company_name')
                    ->label(__('Company Name'))
                    ->getStateUsing(fn (Model $record): ?string => $record instanceof StudentCompany
                        ? $record->company?->name
                        : $record->studentCompany?->company?->name)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $this->todayAbsentStudentsFilterIsActive()
                        ? $this->applyCompanySearchToStudentCompanyQuery($query, $search)
                        : $this->applyCompanySearchToAttendanceQuery($query, $search)),

                TextColumn::make('attendance_date')
                    ->label(__('Date'))
                    ->getStateUsing(fn (Model $record) => $record instanceof StudentCompany
                        ? now()->toDateString()
                        : $record->attendance_date)
                    ->date()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $this->todayAbsentStudentsFilterIsActive()
                        ? $query->orderBy('student_id', $direction)
                        : $query->orderBy('attendance_date', $direction)),

                TextColumn::make('check_in')
                    ->label(__('Check In'))
                    ->time('H:i A')
                    ->placeholder('---'),

                TextColumn::make('check_out')
                    ->label(__('Check Out'))
                    ->time('H:i A')
                    ->placeholder('---'),

                TextColumn::make('attendance_status')
                    ->label(__('Status'))
                    ->getStateUsing(fn (Model $record): mixed => $record instanceof StudentCompany ? __('Absent') : $record->status)
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => $state instanceof AttendanceStatus ? $state->getLabel() : (string) ($state ?: '---'))
                    ->color(fn (mixed $state): string => $state instanceof AttendanceStatus ? $state->getColor() : 'danger'),

                TextColumn::make('check_in_latitude')
                    ->label(__('Location'))
                    ->icon('heroicon-m-map-pin')
                    ->color('primary')
                    ->formatStateUsing(fn ($state) => filled($state) ? __('View Map') : '---')
                    ->url(fn ($record) => filled($record->check_in_latitude) && filled($record->check_in_longitude)
                        ? "https://www.google.com/maps?q={$record->check_in_latitude},{$record->check_in_longitude}"
                        : null)
                    ->openUrlInNewTab(),

                TextColumn::make('work_range_distance')
                    ->label(__('Distance From Work'))
                    ->getStateUsing(fn (Model $record): ?string => $this->workRangeDistanceColumnState($record))
                    ->placeholder('---')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('description')
                    ->label(__('Notes'))
                    ->limit(20)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters($this->getTableFilters(), layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(6)
            ->actions(
                $this->getTableActions()
            )
            ->headerActions([
                Action::make('today_present_students')
                    ->label(__('Today Present Students'))
                    ->icon('heroicon-o-user-group')
                    ->color('primary')
                    ->action(fn () => $this->showTodayPresentStudents())
                    ->visible(fn () => auth()->user()->can('StudentAttendance View List')),

                Action::make('export_today_absent_students')
                    ->label(__('Export Today Absentees'))
                    ->icon('heroicon-m-arrow-down-tray')
                    ->color('success')
                    ->action(fn () => app(ExcelServiceInterface::class)->download(
                        new TodayAbsentStudentsExport($this->todayAbsentStudentsQuery(), now()->toDateString()),
                        $this->todayAbsentStudentsExportFilename(),
                        WriterType::XLSX
                    ))
                    ->visible(fn () => auth()->user()->can('StudentAttendance View List')),

                Action::make('check_in')
                    ->label(__('Check In'))
                    ->modalWidth(MaxWidth::SixExtraLarge)
                    ->requiresConfirmation()
                    ->modalHeading(__('Confirm Check In'))
                    ->modalDescription(__('Please allow browser location access to proceed.'))
                    ->form([
                        MapPicker::make('location')
                            ->label(__('Location'))
                            ->default(['lat' => 32.2211, 'lng' => 35.2544]) // إبقاء نابلس كاحتياط
                            ->defaultLocation(32.2211, 35.2544)
                            ->zoom(15)
                            ->draggable(false)
                            ->clickable(false)
                            ->fixedLocationMarker()
                            ->showMyLocationButton(true)
                            ->showZoomControl(false)
                            ->showFullscreenControl(false)
                            ->live(),

                        Select::make('student_company_id')
                            ->label(__('Student Company'))
                            ->options(StudentCompany::with(['company', 'branch', 'registration', 'student', 'student.studentProfile'])
                                ->tap(fn (Builder $query) => $this->applyStudentCompanyVisibilityScope($query))
                                ->get()
                                ->mapWithKeys(function ($item) {
                                    $number = $item->student->studentProfile?->student_number ?? __('No Number');
                                    $name = $item->student->name ?? __('No Name');
                                    $company = $item->company->name ?? __('No Company');
                                    $branch = $item->branch->name ?? __('No Branch');

                                    return [$item->id => "{$number} - {$name} - {$company} - {$branch}"];
                                }))
                            ->searchable()
                            ->preload()
                            ->required(),

                        Textarea::make('description')
                            ->label(__('Description')),
                    ])
                    ->action(function (array $data) {
                        if (! $this->canAccessStudentCompanyRecord((int) $data['student_company_id'])) {
                            abort(403);
                        }

                        if (empty($data['location']['lat']) || empty($data['location']['lng'])) {
                            Toaster::success('The location could not be determined. Please check permissions.');

                            return;
                        }

                        $attendance = StudentAttendance::create([
                            'student_company_id' => $data['student_company_id'],
                            'attendance_date' => now()->toDateString(),
                            'check_in' => now(),
                            'check_in_latitude' => $data['location']['lat'],
                            'check_in_longitude' => $data['location']['lng'],
                            'status' => AttendanceStatus::UNDETERMINED,
                            'description' => $data['description'],
                            'created_by' => auth()->user()->id,
                        ]);

                        if (auth()->user()?->hasRole(UserRole::STUDENT->value)) {
                            app(PpudsNotificationService::class)->attendanceCheckedIn($attendance);
                        }

                        Toaster::success('Checked In Successfully');

                    })
                    ->visible(fn () => auth()->user()->can('StudentAttendance Create')),

                //                CreateAction::make('create')
                //                    ->label(__('Add Major'))
                //                    ->form([
                //                        Section::make(__('Attendance Information'))
                //                            ->schema([
                //                                Grid::make(2)->schema([
                //                                    // اختيار الطالب المرتبط بالشركة
                //                                    Select::make('student_company_id')
                //                                        ->label(__('Student'))
                //                                        ->relationship('studentCompany.student', 'name') // تأكد من اسم العلاقة في الموديل
                //                                        ->searchable()
                //                                        ->preload()
                //                                        ->required(),
                //
                //                                    // تاريخ الحضور
                //                                    DatePicker::make('attendance_date')
                //                                        ->label(__('Attendance Date'))
                //                                        ->default(now())
                //                                        ->required(),
                //                                ]),
                //
                //                                Grid::make(2)->schema([
                //                                    // وقت الدخول
                //                                    DateTimePicker::make('check_in')
                //                                        ->label(__('Check In Time'))
                //                                        ->seconds(false)
                //                                        ->default(now()),
                //
                //                                    // وقت الخروج
                //                                    DateTimePicker::make('check_out')
                //                                        ->label(__('Check Out Time'))
                //                                        ->seconds(false),
                //                                ]),
                //
                //                                // حالة الحضور من الـ Enum
                //                                Select::make('status')
                //                                    ->label(__('Status'))
                //                                    ->options(AttendanceStatus::options())
                //                                    ->default(AttendanceStatus::UNDETERMINED->value)
                //                                    ->required()
                //                                    ->native(false),
                //
                //                                // الملاحظات
                //                                Textarea::make('description')
                //                                    ->label(__('Description'))
                //                                    ->rows(3)
                //                                    ->columnSpanFull(),
                //                            ]),
                //
                //                        Section::make(__('Location Data'))
                //                            ->description(__('GPS coordinates for check-in and check-out'))
                //                            ->collapsed() // جعلها مطوية لأنها غالباً تُعبأ تلقائياً
                //                            ->schema([
                //                                Grid::make(2)->schema([
                //                                    TextInput::make('check_in_latitude')->numeric()->label(__('In Latitude')),
                //                                    TextInput::make('check_in_longitude')->numeric()->label(__('In Longitude')),
                //                                    TextInput::make('check_out_latitude')->numeric()->label(__('Out Latitude')),
                //                                    TextInput::make('check_out_longitude')->numeric()->label(__('Out Longitude')),
                //                                ]),
                //                            ]),
                //                    ])
                //                    ->visible(fn() => auth()->user()->can('StudentAttendance Create'))
            ])
            ->bulkActions($this->getTableBulkAction());
    }

    protected function getTableFilters(): array
    {
        return [
            Filter::make('attendance_date')
                ->label(__('Attendance Date'))
                ->form([
                    DatePicker::make('from')
                        ->label(__('From Date'))
                        ->default(now()->toDateString())
                        ->format('Y-m-d')
                        ->displayFormat('Y-m-d')
                        ->native(false),
                    DatePicker::make('until')
                        ->label(__('Until Date'))
                        ->default(now()->toDateString())
                        ->format('Y-m-d')
                        ->displayFormat('Y-m-d')
                        ->native(false),
                ])
                ->columns(2)
                ->query(function (Builder $query, array $data): Builder {
                    if ($this->todayAbsentStudentsFilterIsActive() || $this->todayPresentStudentsFilterIsActive()) {
                        return $query;
                    }

                    return $query
                        ->when(
                            $data['from'] ?? null,
                            fn (Builder $query, $date): Builder => $query->whereDate('attendance_date', '>=', Carbon::parse($date)->toDateString())
                        )
                        ->when(
                            $data['until'] ?? null,
                            fn (Builder $query, $date): Builder => $query->whereDate('attendance_date', '<=', Carbon::parse($date)->toDateString())
                        );
                })
                ->indicateUsing(function (array $data): array {
                    if ($this->todayAbsentStudentsFilterIsActive() || $this->todayPresentStudentsFilterIsActive()) {
                        return [];
                    }

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
                }),

            Filter::make('today_absent_students')
                ->label(__('Today Absentees')),

            Filter::make('today_present_students')
                ->label(__('Today Present Students'))
                ->query(function (Builder $query, array $data): Builder {
                    if (empty($data['isActive']) || $this->todayAbsentStudentsFilterIsActive()) {
                        return $query;
                    }

                    return $query
                        ->whereDate('attendance_date', now()->toDateString())
                        ->whereNotNull('check_in');
                }),

            Filter::make('student')
                ->label(__('Student'))
                ->form([
                    TextInput::make('search')
                        ->label(__('Number / Name'))
                        ->live(debounce: 500),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    if (empty($data['search'])) {
                        return $query;
                    }

                    if ($this->todayAbsentStudentsFilterIsActive()) {
                        return $this->applyStudentSearchToStudentCompanyQuery($query, $data['search']);
                    }

                    return $query->whereHas('studentCompany', function (Builder $query) use ($data) {
                        return $this->applyStudentSearchToStudentCompanyQuery($query, $data['search']);
                    });
                }),

            SelectFilter::make('company_id')
                ->label(__('Company'))
                ->options(fn (): array => Company::query()
                    ->tap(fn (Builder $query) => $this->applyCompanyVisibilityScope($query))
                    ->get()
                    ->pluck('name', 'id')
                    ->toArray())
                ->searchable()
                ->preload()
                ->query(function (Builder $query, array $data): Builder {
                    if (empty($data['value'])) {
                        return $query;
                    }

                    if ($this->todayAbsentStudentsFilterIsActive()) {
                        return $query->where('company_id', $data['value']);
                    }

                    return $query->whereHas(
                        'studentCompany',
                        fn (Builder $studentCompanyQuery): Builder => $studentCompanyQuery->where('company_id', $data['value'])
                    );
                }),

            SelectFilter::make('supervisor_id')
                ->label(__('Supervisor'))
                ->options(fn (): array => $this->supervisorFilterOptions())
                ->searchable()
                ->preload()
                ->query(function (Builder $query, array $data): Builder {
                    return $this->applySupervisorFilter(
                        $query,
                        $data,
                        $this->todayAbsentStudentsFilterIsActive()
                            ? 'registration'
                            : 'studentCompany.registration'
                    );
                }),

            SelectFilter::make('status')
                ->label(__('Status'))
                ->options(AttendanceStatus::options())
                ->native(false)
                ->query(function (Builder $query, array $data): Builder {
                    if (empty($data['value']) || $this->todayAbsentStudentsFilterIsActive()) {
                        return $query;
                    }

                    return $query->where('status', $data['value']);
                }),

            Filter::make('check_out_status')
                ->label(__('Check Out'))
                ->form([
                    Select::make('status')
                        ->label(__('Check Out'))
                        ->options([
                            'checked_out' => __('Checked Out'),
                            'pending' => __('Pending Check Out'),
                        ])
                        ->native(false),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    if ($this->todayAbsentStudentsFilterIsActive()) {
                        return $query;
                    }

                    return match ($data['status'] ?? null) {
                        'checked_out' => $query->whereNotNull('check_out'),
                        'pending' => $query->whereNull('check_out'),
                        default => $query,
                    };
                }),

            Filter::make('outside_work_range')
                ->label(__('Outside Work Range'))
                ->form([
                    Forms\Components\Toggle::make('isActive')
                        ->label(__('Outside Work Range')),

                    TextInput::make('distance_meters')
                        ->label(__('Allowed Range (meters)'))
                        ->numeric()
                        ->minValue(1)
                        ->default(200),

                    Select::make('location_type')
                        ->label(__('Location Type'))
                        ->options([
                            'check_in' => __('Check In Location'),
                            'check_out' => __('Check Out Location'),
                        ])
                        ->default('check_in')
                        ->native(false),
                ])
                ->columns(3)
                ->query(function (Builder $query, array $data): Builder {
                    if (empty($data['isActive']) || $this->todayAbsentStudentsFilterIsActive()) {
                        return $query;
                    }

                    return $this->applyOutsideWorkRangeFilter(
                        $query,
                        $this->outsideWorkRangeDistanceMeters($data['distance_meters'] ?? null),
                        $this->outsideWorkRangeLocationType($data['location_type'] ?? null),
                    );
                })
                ->indicateUsing(function (array $data): array {
                    if (empty($data['isActive'])) {
                        return [];
                    }

                    return [
                        Indicator::make(__('Outside Work Range').': '.$this->outsideWorkRangeDistanceMeters($data['distance_meters'] ?? null).' '.__('meters')),
                    ];
                }),
        ];
    }

    public function getTableBulkAction(): array
    {
        return [
            BulkActionGroup::make([
                BulkAction::make('delete')
                    ->label(__('Delete'))
                    ->requiresConfirmation()
                    ->visible(fn () => auth()->user()->can('StudentAttendance Delete') && ! $this->todayAbsentStudentsFilterIsActive())
                    ->action(fn (Collection $records) => $records->each->delete()),
            ]),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            Action::make('check_out')
                ->button()
                ->label(__('Check Out'))
                ->form([
                    MapPicker::make('location')
                        ->label(__('Location'))
                        ->default(['lat' => 32.2211, 'lng' => 35.2544])
                        ->defaultLocation(32.2211, 35.2544)
                        ->zoom(15)
                        ->draggable(false)
                        ->clickable(false)
                        ->fixedLocationMarker()
                        ->showMyLocationButton(true)
                        ->showZoomControl(false)
                        ->showFullscreenControl(false)
                        ->live(),
                ])
                ->action(function (array $data, StudentAttendance $record) {
                    $record->update([
                        'check_out' => now(),
                        'check_out_latitude' => $data['location']['lat'],
                        'check_out_longitude' => $data['location']['lng'],
                    ]);

                    if (auth()->user()?->hasRole(UserRole::STUDENT->value)) {
                        app(PpudsNotificationService::class)->attendanceCheckedOut($record->refresh());
                    }

                    Toaster::success('Checked Out Successfully');
                })
                ->visible(fn (Model $record) => ! $this->isTodayAbsentStudentRecord($record) && $record instanceof StudentAttendance && $record->check_out === null && (
                    auth()->user()->can('StudentAttendance Create')
                    || auth()->user()->can('StudentAttendance Update')
                )),
            Action::make('report')
                    // Removed ->model() as it was causing confusion
                ->button()
                ->label(__('Report'))
                ->url(fn (Model $record): ?string => $record instanceof StudentAttendance ? route('student-attendances.report', $record) : null)
                ->visible(fn (Model $record) => ! $this->isTodayAbsentStudentRecord($record)
                    && $record instanceof StudentAttendance
                    && ($record->check_out !== null && $record->studentReport === null)
                    && (auth()->user()->can('StudentAttendance Report List'))
                ),
            InfoAction::make('info')
                ->label('')
                ->visible(fn (Model $record) => ! $this->isTodayAbsentStudentRecord($record) && auth()->user()->can('StudentAttendance Info')),
            ViewAction::make('view')
                ->modalHeading(__('Attendance Details'))
                ->modalWidth(MaxWidth::FourExtraLarge)
                ->form(function (Forms\Form $form, StudentAttendance $record) {
                    $record->loadMissing([
                        'studentCompany.company',
                        'studentCompany.branch',
                        'studentCompany.student.studentProfile',
                        'studentReport.media',
                    ]);

                    $report = $record->studentReport;

                    return $form->schema([
                        Forms\Components\Section::make(__('Attendance Information'))
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        TextInput::make('student_name')
                                            ->label(__('Student Name'))
                                            ->default($record->studentCompany?->student?->name ?? '---')
                                            ->disabled(),

                                        TextInput::make('student_number')
                                            ->label(__('Student Number'))
                                            ->default($record->studentCompany?->student?->studentProfile?->student_number ?? '---')
                                            ->disabled(),

                                        TextInput::make('company_name')
                                            ->label(__('Company Name'))
                                            ->default($record->studentCompany?->company?->name ?? '---')
                                            ->disabled(),

                                        TextInput::make('branch_name')
                                            ->label(__('Branch'))
                                            ->default($record->studentCompany?->branch?->name ?? '---')
                                            ->disabled(),

                                        TextInput::make('attendance_date_view')
                                            ->label(__('Date'))
                                            ->default($record->attendance_date?->format('Y-m-d') ?? '---')
                                            ->disabled(),

                                        TextInput::make('attendance_status_view')
                                            ->label(__('Status'))
                                            ->default($record->status instanceof AttendanceStatus ? $record->status->getLabel() : ($record->status ?: '---'))
                                            ->disabled(),

                                        TextInput::make('check_in_view')
                                            ->label(__('Check In'))
                                            ->default($record->check_in?->format('H:i A') ?? '---')
                                            ->disabled(),

                                        TextInput::make('check_out_view')
                                            ->label(__('Check Out'))
                                            ->default($record->check_out?->format('H:i A') ?? '---')
                                            ->disabled(),
                                    ]),

                                Textarea::make('attendance_description')
                                    ->label(__('Notes'))
                                    ->default($record->description ?: '---')
                                    ->disabled()
                                    ->columnSpanFull(),
                            ]),

                        Forms\Components\Section::make(__('Daily Report'))
                            ->schema($report ? [
                                Forms\Components\RichEditor::make('report_text_view')
                                    ->label(__('Report Text'))
                                    ->default($report->report_text)
                                    ->disabled()
                                    ->toolbarButtons([])
                                    ->columnSpanFull(),

                                Forms\Components\RichEditor::make('company_feedback_view')
                                    ->label(__('Company Feedback'))
                                    ->default($report->company_feedback)
                                    ->disabled()
                                    ->toolbarButtons([])
                                    ->columnSpanFull(),

                                Forms\Components\RichEditor::make('academic_feedback_view')
                                    ->label(__('Academic Feedback'))
                                    ->default($report->academic_feedback)
                                    ->disabled()
                                    ->toolbarButtons([])
                                    ->columnSpanFull(),

                                Forms\Components\Placeholder::make('report_attachments_view')
                                    ->label(__('Report Attachments'))
                                    ->content(fn (): HtmlString => $this->reportAttachmentsHtml($report))
                                    ->columnSpanFull(),
                            ] : [
                                Forms\Components\Placeholder::make('missing_report')
                                    ->label(__('Daily Report'))
                                    ->content(__('No daily report has been submitted yet.')),
                            ]),
                    ]);
                })
                ->modalSubmitAction(false)
                ->visible(fn (Model $record) => ! $this->isTodayAbsentStudentRecord($record) && auth()->user()->can('StudentAttendance View')),
            EditAction::make('edit')
                ->form(function (Major $record) {
                    return [
                        TextInput::make('reference_code')
                            ->label(__('Reference Code'))
                            ->required()
                            ->default($record->reference_code)
                            ->maxLength(255)
                            ->unique(Major::class, 'reference_code', ignoreRecord: true),

                        TextInput::make('name')
                            ->label(__('Name'))
                            ->required()
                            ->default($record->name)
                            ->maxLength(255),

                        Textarea::make('description')
                            ->default($record->description)
                            ->label(__('Description')),
                    ];
                })
                ->action(function (Major $record, array $data) {
                    $record->update($data);
                    Toaster::success(__('Major updated successfully'));
                })
                ->visible(fn (Model $record) => ! $this->isTodayAbsentStudentRecord($record) && auth()->user()->can('StudentAttendance Update')),

            DeleteAction::make('delete')
                ->action(function ($record) {
                    $this->authorize('StudentAttendance Delete');
                    $record->delete();
                    Toaster::success(__('Attendance deleted successfully'));
                })
                ->visible(fn (Model $record) => ! $this->isTodayAbsentStudentRecord($record) && auth()->user()->can('StudentAttendance Delete')),
        ];
    }

    protected function reportAttachmentsHtml(StudentReport $report): HtmlString
    {
        $attachments = $report->getFileReportItems();

        if ($attachments->isEmpty()) {
            return new HtmlString('<span class="text-sm text-gray-500">---</span>');
        }

        $items = $attachments
            ->map(function (array $attachment): string {
                $url = e($attachment['url'] ?? '#');
                $name = e($attachment['name'] ?? __('Attachment'));
                $preview = '';

                if ($attachment['is_image'] ?? false) {
                    $preview = <<<HTML
                        <img src="{$url}" alt="{$name}" class="h-16 w-16 rounded object-cover ring-1 ring-gray-200 dark:ring-gray-700">
                    HTML;
                }

                return <<<HTML
                    <a href="{$url}" target="_blank" rel="noopener noreferrer" class="flex items-center gap-3 rounded-md border border-gray-200 bg-white p-2 text-sm text-primary-600 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:bg-gray-800">
                        {$preview}
                        <span class="truncate">{$name}</span>
                    </a>
                HTML;
            })
            ->implode('');

        return new HtmlString('<div class="grid gap-2 sm:grid-cols-2">'.$items.'</div>');
    }

    public function render()
    {
        return view('ppuds::livewire.pages.student-attendance.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Companies List'), 'url' => route('majors.index')],
            ],
        ]);
    }

    protected function studentAttendanceTableQuery(): Builder
    {
        if ($this->todayAbsentStudentsFilterIsActive()) {
            return $this->todayAbsentStudentsQuery();
        }

        return StudentAttendance::query()->with([
            'studentCompany.branch',
            'studentCompany.company',
            'studentCompany.student.media',
            'studentCompany.student.studentProfile',
            'studentReport',
        ])
            ->whereHas(
                'studentCompany',
                fn (Builder $studentCompanyQuery): Builder => $this->applyStudentCompanyVisibilityScope($studentCompanyQuery)
            );
    }

    protected function todayAbsentStudentsQuery(): Builder
    {
        $settings = app(GeneralSettings::class);
        $today = now()->toDateString();
        $companyId = data_get($this->getTableFilterState('company_id'), 'value');
        $supervisorId = data_get($this->getTableFilterState('supervisor_id'), 'value');
        $studentSearch = data_get($this->getTableFilterState('student'), 'search');

        return StudentCompany::query()
            ->with([
                'branch.translations',
                'company.translations',
                'department.translations',
                'registration.course.translations',
                'registration.supervisor',
                'student.media',
                'student.studentProfile.major.translations',
            ])
            ->whereNotNull('company_id')
            ->whereHas('registration', fn (Builder $query): Builder => $query
                ->where('year', $settings->year)
                ->where('semester', $settings->semester_type->value))
            ->whereDoesntHave('attendances', fn (Builder $query): Builder => $query
                ->whereDate('attendance_date', $today)
                ->whereNotNull('check_in'))
            ->tap(fn (Builder $query) => $this->applyStudentCompanyVisibilityScope($query))
            ->when(
                filled($companyId),
                fn (Builder $query): Builder => $query->where('company_id', $companyId)
            )
            ->when(
                filled($supervisorId),
                fn (Builder $query): Builder => $query->whereHas(
                    'registration',
                    fn (Builder $registrationQuery): Builder => $registrationQuery->where('supervisor_id', (int) $supervisorId)
                )
            )
            ->when(
                filled($studentSearch),
                fn (Builder $query): Builder => $this->applyStudentSearchToStudentCompanyQuery($query, $studentSearch)
            )
            ->orderBy('student_id');
    }

    protected function todayAbsentStudentsFilterIsActive(): bool
    {
        return (bool) data_get($this->getTableFilterState('today_absent_students'), 'isActive', false);
    }

    protected function todayPresentStudentsFilterIsActive(): bool
    {
        return (bool) data_get($this->getTableFilterState('today_present_students'), 'isActive', false);
    }

    protected function applyOutsideWorkRangeFilter(Builder $query, int $distanceMeters, string $locationType): Builder
    {
        $attendanceTable = (new StudentAttendance())->getTable();
        $branchTable = (new Branch())->getTable();
        $latitudeColumn = $locationType === 'check_out' ? 'check_out_latitude' : 'check_in_latitude';
        $longitudeColumn = $locationType === 'check_out' ? 'check_out_longitude' : 'check_in_longitude';

        return $query
            ->whereNotNull("{$attendanceTable}.{$latitudeColumn}")
            ->whereNotNull("{$attendanceTable}.{$longitudeColumn}")
            ->whereHas('studentCompany.branch', function (Builder $branchQuery) use ($attendanceTable, $branchTable, $latitudeColumn, $longitudeColumn, $distanceMeters): Builder {
                return $branchQuery
                    ->whereNotNull("{$branchTable}.latitude")
                    ->whereNotNull("{$branchTable}.longitude")
                    ->whereRaw(
                        $this->distanceFromBranchSql($attendanceTable, $branchTable, $latitudeColumn, $longitudeColumn).' > ?',
                        [$distanceMeters]
                    );
            });
    }

    protected function distanceFromBranchSql(string $attendanceTable, string $branchTable, string $latitudeColumn, string $longitudeColumn): string
    {
        return "(
            6371000 * ACOS(
                LEAST(
                    1,
                    GREATEST(
                        -1,
                        COS(RADIANS({$attendanceTable}.{$latitudeColumn}))
                        * COS(RADIANS({$branchTable}.latitude))
                        * COS(RADIANS({$branchTable}.longitude) - RADIANS({$attendanceTable}.{$longitudeColumn}))
                        + SIN(RADIANS({$attendanceTable}.{$latitudeColumn}))
                        * SIN(RADIANS({$branchTable}.latitude))
                    )
                )
            )
        )";
    }

    protected function outsideWorkRangeDistanceMeters(mixed $value): int
    {
        $distance = (int) $value;

        return $distance > 0 ? $distance : 200;
    }

    protected function outsideWorkRangeLocationType(mixed $value): string
    {
        return $value === 'check_out' ? 'check_out' : 'check_in';
    }

    public function showTodayPresentStudents(): void
    {
        $today = now()->toDateString();

        $this->tableFilters = array_replace($this->tableFilters ?? [], [
            'attendance_date' => [
                'from' => $today,
                'until' => $today,
            ],
            'today_absent_students' => [
                'isActive' => false,
            ],
            'today_present_students' => [
                'isActive' => true,
            ],
        ]);

        $this->handleTableFilterUpdates();
    }

    protected function isTodayAbsentStudentRecord(Model $record): bool
    {
        return $record instanceof StudentCompany;
    }

    protected function studentDisplayColumnState(Model $record): HtmlString|string
    {
        return $this->studentFromRecord($record)?->getUserDisplayHtmlAttribute() ?? '---';
    }

    protected function studentNumberColumnState(Model $record): ?string
    {
        return $this->studentFromRecord($record)?->studentProfile?->student_number;
    }

    protected function studentPhoneColumnState(Model $record): ?string
    {
        return $this->studentFromRecord($record)?->phone;
    }

    protected function workRangeDistanceColumnState(Model $record): ?string
    {
        if (! $record instanceof StudentAttendance) {
            return null;
        }

        $branch = $record->studentCompany?->branch;

        if (
            blank($record->check_in_latitude)
            || blank($record->check_in_longitude)
            || blank($branch?->latitude)
            || blank($branch?->longitude)
        ) {
            return null;
        }

        $distance = $this->distanceInMeters(
            (float) $record->check_in_latitude,
            (float) $record->check_in_longitude,
            (float) $branch->latitude,
            (float) $branch->longitude,
        );

        return number_format($distance).' '.__('meters');
    }

    protected function distanceInMeters(float $latitudeA, float $longitudeA, float $latitudeB, float $longitudeB): int
    {
        $earthRadius = 6371000;
        $latitudeDelta = deg2rad($latitudeB - $latitudeA);
        $longitudeDelta = deg2rad($longitudeB - $longitudeA);

        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($latitudeA)) * cos(deg2rad($latitudeB))
            * sin($longitudeDelta / 2) ** 2;

        return (int) round($earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    protected function studentDetailsUrl(Model $record): ?string
    {
        $student = $this->studentFromRecord($record);

        if (! $student || ! auth()->user()->can('Student Details List')) {
            return null;
        }

        return route('students.details', $student->id);
    }

    protected function studentFromRecord(Model $record): ?User
    {
        if ($record instanceof StudentCompany) {
            return $record->student;
        }

        if ($record instanceof StudentAttendance) {
            return $record->studentCompany?->student;
        }

        return null;
    }

    protected function todayAbsentStudentsExportFilename(): string
    {
        return 'today-absent-students-'.now()->format('Y-m-d-His').'.xlsx';
    }
}
