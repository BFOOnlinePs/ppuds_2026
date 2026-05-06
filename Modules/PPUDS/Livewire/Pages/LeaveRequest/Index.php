<?php

namespace Modules\PPUDS\Livewire\Pages\LeaveRequest;

use App\View\Components\AppLayout;
use Filament\Forms;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\Action; // تم إضافة هذا الكلاس
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Core\Enums\UserRole;
use Modules\Core\Filament\Forms\Components\CreateAction;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\InfoAction;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Modules\PPUDS\Entities\LeaveRequest;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Enums\LeaveRequestStatus;
use Modules\PPUDS\Enums\LeaveRequestType;
use Modules\PPUDS\Services\PpudsNotificationService;

class Index extends Component implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    public ?array $filters = [];

    public function table(Table $table): Table
    {
        return $table
            ->query(fn() => LeaveRequest::query()
                ->where($this->filters)
                ->when(auth()->user()->hasRole(UserRole::STUDENT->value), fn ($query) => $query->whereHas('studentCompany', fn ($studentCompanyQuery) => $studentCompanyQuery->where('student_id', auth()->id())))
                ->latest())
            ->columns([
                TextColumn::make('studentCompany.student.name')
                    ->label(__('Student'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label(__('Type'))
                    ->badge()
                    ->sortable(),

                TextColumn::make('start_at')
                    ->label(__('Start Date'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

                TextColumn::make('end_at')
                    ->label(__('End Date'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

                // يمكنك إضافة أعمدة لحالة الطلب هنا إذا أردت
                TextColumn::make('company_approval')
                    ->label(__('Company Status'))
                    ->badge()
                    ->sortable(),

                TextColumn::make('university_approval')
                    ->label(__('University Status'))
                    ->badge()
                    ->sortable(),
            ])
            ->filters($this->getTableFilters())
            ->actions(
                $this->getTableActions()
            )
            ->headerActions([
                CreateAction::make('create')
                    ->label(__('New Request'))
                    ->icon('heroicon-o-plus')
                    ->form($this->getFormSchema(isCreate: true))
                    ->action(function (array $data) {
                        if (auth()->user()->hasRole('Student') && ! StudentCompany::whereKey($data['student_company_id'])->where('student_id', auth()->id())->exists()) {
                            abort(403);
                        }

                        $data['created_by'] = auth()->id();

                        $data['company_approval'] = LeaveRequestStatus::PENDING ?? LeaveRequestStatus::APPROVED;
                        $data['university_approval'] = LeaveRequestStatus::PENDING ?? LeaveRequestStatus::APPROVED;

                        $leaveRequest = LeaveRequest::create($data);

                        app(PpudsNotificationService::class)->leaveRequestCreated($leaveRequest);

                        Toaster::success(__('Leave Request created successfully'));
                    })
                    ->visible(fn() => auth()->user()->can('LeaveRequest Create'))
            ])
            ->bulkActions($this->getTableBulkAction());
    }

    protected function getTableFilters(): array
    {
        return [
            SelectFilter::make('type')
                ->label(__('Type'))
                ->options(LeaveRequestType::class),
        ];
    }

    public function getTableBulkAction(): array
    {
        return [
            BulkActionGroup::make([
                BulkAction::make('delete')
                    ->label(__('Delete'))
                    ->requiresConfirmation()
                    ->visible(fn() => auth()->user()->can('LeaveRequest Delete'))
                    ->action(fn(Collection $records) => $records->each->delete()),
            ])
        ];
    }

    protected function getTableActions(): array
    {
        return [
            // --- أكشن قرار الشركة ---
            Action::make('company_decision')
                ->label(__('Company Decision'))
                ->icon('heroicon-o-building-office')
                ->color('primary')
                ->form([
                    Select::make('company_approval')
                        ->label(__('Status'))
                        ->options(LeaveRequestStatus::class)
                        ->required()
                        ->native(false),
                    Textarea::make('company_supervisor_comment')
                        ->label(__('Supervisor Comment'))
                        ->rows(3),
                ])
                ->action(function (LeaveRequest $record, array $data) {
                    $record->update([
                        'company_approval' => $data['company_approval'],
                        'company_supervisor_comment' => $data['company_supervisor_comment'],
                        'company_supervisor_id' => auth()->id(), // توثيق تلقائي للشخص
                    ]);

                    if ($record->wasChanged('company_approval')) {
                        app(PpudsNotificationService::class)->leaveRequestDecisionUpdated($record->refresh(), 'company_approval');
                    }

                    Toaster::success(__('Company decision recorded successfully'));
                })
                // يظهر فقط إذا كان المستخدم يملك هذه الصلاحية
                ->visible(fn() => auth()->user()->can('LeaveRequest CompanyApprove')),

            // --- أكشن قرار الجامعة ---
            Action::make('university_decision')
                ->label(__('University Decision'))
                ->icon('heroicon-o-academic-cap')
                ->color('success')
                ->form([
                    Select::make('university_approval')
                        ->label(__('Status'))
                        ->options(LeaveRequestStatus::class)
                        ->required()
                        ->native(false),
                    Textarea::make('university_supervisor_comment')
                        ->label(__('Supervisor Comment'))
                        ->rows(3),
                ])
                ->action(function (LeaveRequest $record, array $data) {
                    $record->update([
                        'university_approval' => $data['university_approval'],
                        'university_supervisor_comment' => $data['university_supervisor_comment'],
                        'university_supervisor_id' => auth()->id(), // توثيق تلقائي للشخص
                    ]);

                    if ($record->wasChanged('university_approval')) {
                        app(PpudsNotificationService::class)->leaveRequestDecisionUpdated($record->refresh(), 'university_approval');
                    }

                    Toaster::success(__('University decision recorded successfully'));
                })
                // يظهر فقط إذا كان المستخدم يملك هذه الصلاحية
                ->visible(fn() => auth()->user()->can('LeaveRequest UniversityApprove')),

            InfoAction::make('info')
                ->label('')
                ->visible(fn() => auth()->user()->can('LeaveRequest Info')),

            ViewAction::make('view')
                ->form(function (Forms\Form $form, $record) {
                    return $form->schema($this->getFormSchema(isView: true));
                })
                ->modalSubmitAction(false)
                ->visible(fn() => auth()->user()->can('LeaveRequest View')),

            EditAction::make('edit')
                ->form(function (LeaveRequest $record) {
                    return $this->getFormSchema(isEdit: true);
                })
                ->mountUsing(function (Forms\ComponentContainer $form, LeaveRequest $record) {
                    $form->fill($record->toArray());
                })
                ->action(function (LeaveRequest $record, array $data) {
                    $record->update($data);
                    Toaster::success(__('Leave Request updated successfully'));
                })
                ->visible(fn() => auth()->user()->can('LeaveRequest Update')),

            DeleteAction::make('delete')
                ->action(function ($record) {
                    $this->authorize('LeaveRequest Delete');
                    $record->delete();
                    Toaster::success(__('Leave Request deleted successfully'));
                })
                ->visible(fn() => auth()->user()->can('LeaveRequest Delete'))
        ];
    }

    protected function getFormSchema(bool $isCreate = false, bool $isEdit = false, bool $isView = false): array
    {
        return [
            Section::make(__('Request Details'))
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('student_company_id')
                            ->label(__('Student Training'))
                            ->options(StudentCompany::with('student', 'company')->when(auth()->user()->hasRole('Student'), function ($query) {
                                $query->whereHas('student', function ($q) {
                                    $q->where('id', auth()->id());
                                });
                            })->get()->mapWithKeys(function ($item) {
                                return [$item->id => $item->student->name . ' - ' . $item->company->name];
                            }))
                            ->searchable()
                            ->required()
                            ->disabled($isView)
                            ->columnSpanFull(),

                        Select::make('type')
                            ->label(__('Leave Type'))
                            ->options(LeaveRequestType::class)
                            ->required()
                            ->native(false)
                            ->disabled($isView),

                        Grid::make(2)->schema([
                            DateTimePicker::make('start_at')
                                ->label(__('Start Date'))
                                ->required()
                                ->seconds(false)
                                ->disabled($isView),

                            DateTimePicker::make('end_at')
                                ->label(__('End Date'))
                                ->required()
                                ->seconds(false)
                                ->after('start_at')
                                ->disabled($isView),
                        ])->columnSpanFull(),

                        Textarea::make('reason')
                            ->label(__('Reason'))
                            ->required()
                            ->columnSpanFull()
                            ->disabled($isView),
                    ]),
                ]),

            // قسم التوثيق والموافقات (للقراءة فقط لتأكيد الرسمية)
            Section::make(__('Approvals & Documentation'))
                ->visible(!$isCreate) // لا يظهر عند إنشاء طلب جديد
                ->schema([
                    Grid::make(2)->schema([

                        // --- توثيق الشركة ---
                        Fieldset::make(__('Company Documentation'))
                            ->columnSpan(1)
                            ->schema([
                                Select::make('company_approval')
                                    ->label(__('Status'))
                                    ->options(LeaveRequestStatus::class)
                                    ->disabled() // معطل لأنه يعتمد على زر القرار فقط
                                    ->columnSpanFull(),

                                Select::make('company_supervisor_id')
                                    ->label(__('Authorized By'))
                                    ->relationship('companySupervisor', 'name') // استدعاء اسم المستخدم من العلاقة
                                    ->disabled()
                                    ->columnSpanFull(),

                                Textarea::make('company_supervisor_comment')
                                    ->label(__('Official Comment'))
                                    ->disabled()
                                    ->columnSpanFull(),
                            ]),

                        // --- توثيق الجامعة ---
                        Fieldset::make(__('University Documentation'))
                            ->columnSpan(1)
                            ->schema([
                                Select::make('university_approval')
                                    ->label(__('Status'))
                                    ->options(LeaveRequestStatus::class)
                                    ->disabled() // معطل لأنه يعتمد على زر القرار فقط
                                    ->columnSpanFull(),

                                Select::make('university_supervisor_id')
                                    ->label(__('Authorized By'))
                                    ->relationship('universitySupervisor', 'name') // استدعاء اسم المستخدم من العلاقة
                                    ->disabled()
                                    ->columnSpanFull(),

                                Textarea::make('university_supervisor_comment')
                                    ->label(__('Official Comment'))
                                    ->disabled()
                                    ->columnSpanFull(),
                            ]),
                    ]),
                ]),
        ];
    }

    public function render()
    {
        return view('ppuds::livewire.pages.leave-request.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Leave Requests'), 'url' => route('leave-requests.index')],
            ]
        ]);
    }
}
