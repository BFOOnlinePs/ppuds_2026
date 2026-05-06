<?php

namespace Modules\PPUDS\Livewire\Pages\StudentAttendance;

use App\View\Components\AppLayout;
use Filament\Forms;
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
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Core\Enums\UserRole;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\InfoAction;
use Modules\Core\Filament\Forms\Components\MapPicker;
use Modules\Core\Filament\Forms\Components\Textarea;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Modules\PPUDS\Entities\Major;
use Modules\PPUDS\Entities\StudentAttendance;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Enums\AttendanceStatus;
use Modules\PPUDS\Services\PpudsNotificationService;

class Index extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public array $filters = [];

    public function table(Table $table)
    {
        return $table
            ->query(fn () => StudentAttendance::query()->with(['studentCompany', 'studentReport'])
                ->where($this->filters)
                ->when(auth()->user()->hasRole(UserRole::STUDENT->value), fn ($query) => $query->whereHas('studentCompany', fn ($studentCompanyQuery) => $studentCompanyQuery->where('student_id', auth()->id()))))
            ->columns([
                TextColumn::make('studentCompany.student.name')
                    ->label(__('Student Name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('studentCompany.company.name')
                    ->label(__('Company Name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('attendance_date')
                    ->label(__('Date'))
                    ->date()
                    ->sortable(),

                TextColumn::make('check_in')
                    ->label(__('Check In'))
                    ->time('H:i A'),

                TextColumn::make('check_out')
                    ->label(__('Check Out'))
                    ->time('H:i A')
                    ->placeholder('---'),

                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn (AttendanceStatus $state): string => $state->getLabel())
                    ->color(AttendanceStatus::class),

                TextColumn::make('check_in_latitude')
                    ->label(__('Location'))
                    ->icon('heroicon-m-map-pin')
                    ->color('primary')
                    ->formatStateUsing(fn () => __('View Map'))
                    ->url(fn ($record) => "https://www.google.com/maps?q={$record->check_in_latitude},{$record->check_in_longitude}")
                    ->openUrlInNewTab(),

                TextColumn::make('description')
                    ->label(__('Notes'))
                    ->limit(20)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters($this->getTableFilters())
            ->actions(
                $this->getTableActions()
            )
            ->headerActions([
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
                                ->when(auth()->user()->hasRole('Student'), fn ($query) => $query->where('student_id', auth()->id()))
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
                        if (auth()->user()->hasRole('Student') && ! StudentCompany::whereKey($data['student_company_id'])->where('student_id', auth()->id())->exists()) {
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

                    }),

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
            Filter::make('reference_code')
                ->label(__('Reference Code')),
            Filter::make('name')
                ->label(__('Name')),
        ];
    }

    public function getTableBulkAction(): array
    {
        return [
            BulkActionGroup::make([
                BulkAction::make('delete')
                    ->label(__('Delete'))
                    ->requiresConfirmation()
                    ->visible(fn () => auth()->user()->can('StudentAttendance Delete'))
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
                ->visible(fn ($record) => $record->check_out === null),
            Action::make('report')
                    // Removed ->model() as it was causing confusion
                ->button()
                ->label(__('Report'))
                ->url(fn($record) => route('student-attendances.report', $record))
                ->visible(fn (StudentAttendance $record) =>
                    ($record->check_out !== null && $record->studentReport === null) && (auth()->user()->can('StudentAttendance Report List'))
                ),
            InfoAction::make('info')
                ->label('')
                ->visible(fn () => auth()->user()->can('StudentAttendance Info')),
            ViewAction::make('view')
                ->form(function (Forms\Form $form, $record) {
                    return $form->schema([
                        TextInput::make('name')
                            ->label(__('Name'))
                            ->default($record->name)
                            ->disabled(),
                        TextInput::make('website')
                            ->label(__('Website'))
                            ->default($record->website)
                            ->disabled(),
                        TextInput::make('category.name')
                            ->label(__('Category'))
                            ->default($record->category->name)
                            ->disabled(),
                        Textarea::make('description')
                            ->default($record->description)
                            ->disabled(),
                    ]);
                })
                ->modalSubmitAction(false)
                ->visible(fn () => auth()->user()->can('StudentAttendance View')),
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
                ->visible(fn () => auth()->user()->can('StudentAttendance Update')),

            DeleteAction::make('delete')
                ->action(function ($record) {
                    $this->authorize('StudentAttendance Delete');
                    $record->delete();
                    Toaster::success(__('Attendance deleted successfully'));
                })
                ->visible(fn () => auth()->user()->can('StudentAttendance Delete')),
        ];
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
}
