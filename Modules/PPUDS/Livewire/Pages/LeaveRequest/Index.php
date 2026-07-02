<?php

namespace Modules\PPUDS\Livewire\Pages\LeaveRequest;

use App\View\Components\AppLayout;
use Filament\Forms;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
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
use Modules\PPUDS\Support\ScopesStudentCompanyVisibility;

class Index extends Component implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;
    use ScopesStudentCompanyVisibility;

    public ?array $filters = [];

    public function table(Table $table): Table
    {
        return $table
            ->query(fn() => LeaveRequest::query()
                ->with([
                    'studentCompany.student',
                    'studentCompany.company',
                    'media',
                    'companySupervisor',
                    'universitySupervisor',
                ])
                ->where($this->filters)
                ->whereHas(
                    'studentCompany',
                    fn (Builder $studentCompanyQuery): Builder => $this->applyStudentCompanyVisibilityScope($studentCompanyQuery)
                )
                ->latest())
            ->columns([
                TextColumn::make('studentCompany.student.name')
                    ->label(__('Student'))
                    ->searchable()
                    ->action(fn (LeaveRequest $record) => $this->mountTableAction('view', (string) $record->getKey()))
                    ->disabledClick(fn (): bool => ! auth()->user()?->can('LeaveRequest View'))
                    ->color(fn (): ?string => auth()->user()?->can('LeaveRequest View') ? 'primary' : null)
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
                TextColumn::make('attachment_file')
                    ->label(__('Attachment'))
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? __('Download') : '-')
                    ->url(fn (LeaveRequest $record): ?string => $record->attachment_file)
                    ->openUrlInNewTab()
                    ->toggleable(),

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
                        $attachmentFile = $this->pullAttachmentFile($data);

                        if (! $this->canAccessStudentCompanyRecord((int) $data['student_company_id'])) {
                            abort(403);
                        }

                        $data['created_by'] = auth()->id();

                        $data['company_approval'] = LeaveRequestStatus::PENDING ?? LeaveRequestStatus::APPROVED;
                        $data['university_approval'] = LeaveRequestStatus::PENDING ?? LeaveRequestStatus::APPROVED;

                        $leaveRequest = LeaveRequest::create($data);

                        if ($attachmentFile !== null) {
                            $leaveRequest->addAttachment($attachmentFile);
                        }

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
                    abort_unless($this->canAccessStudentCompanyRecord($record->studentCompany), 403);

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
                    abort_unless($this->canAccessStudentCompanyRecord($record->studentCompany), 403);

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
                ->mountUsing(fn (Forms\ComponentContainer $form, LeaveRequest $record) => $this->fillViewForm($form, $record))
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
                    abort_unless($this->canAccessStudentCompanyRecord($record->studentCompany), 403);

                    $attachmentFile = $this->pullAttachmentFile($data);

                    $record->update($data);

                    if ($attachmentFile !== null) {
                        $record->addAttachment($attachmentFile);
                    }

                    Toaster::success(__('Leave Request updated successfully'));
                })
                ->visible(fn() => auth()->user()->can('LeaveRequest Update')),

            DeleteAction::make('delete')
                ->action(function ($record) {
                    $this->authorize('LeaveRequest Delete');
                    abort_unless($this->canAccessStudentCompanyRecord($record->studentCompany), 403);
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
                            ->options(StudentCompany::with('student', 'company')
                                ->tap(fn (Builder $query) => $this->applyStudentCompanyVisibilityScope($query))
                                ->get()
                                ->mapWithKeys(function ($item) {
                                    return [$item->id => ($item->student?->name ?? __('Unknown Student')) . ' - ' . ($item->company?->name ?? __('No Company'))];
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

                        Placeholder::make('current_attachment')
                            ->label(__('Current Uploaded Files'))
                            ->content(fn (?LeaveRequest $record): HtmlString|string => $this->attachmentLink($record))
                            ->visible(fn (?LeaveRequest $record): bool => ! $isCreate && $record?->getFirstMedia(LeaveRequest::ATTACHMENT_COLLECTION) !== null)
                            ->columnSpanFull(),

                        FileUpload::make('attachment_file')
                            ->label(__('Attachment (Image/File)'))
                            ->storeFiles(false)
                            ->acceptedFileTypes([
                                'application/pdf',
                                'image/jpeg',
                                'image/png',
                                'image/webp',
                            ])
                            ->rules(['mimes:pdf,jpg,jpeg,png,webp'])
                            ->maxSize(5120)
                            ->visible(! $isView)
                            ->columnSpanFull(),
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

    protected function fillViewForm(Forms\ComponentContainer $form, LeaveRequest $record): void
    {
        $form->fill($record->attributesToArray());
    }

    protected function pullAttachmentFile(array &$data): mixed
    {
        $file = $data['attachment_file'] ?? null;

        unset($data['attachment_file']);

        if (is_array($file) && empty($file)) {
            return null;
        }

        return $file ?: null;
    }

    protected function attachmentLink(?LeaveRequest $record): HtmlString|string
    {
        $media = $record?->getFirstMedia(LeaveRequest::ATTACHMENT_COLLECTION);

        if ($media === null) {
            return '-';
        }

        $url = e($media->getUrl());
        $fileName = e($media->file_name);

        return new HtmlString("<a href=\"{$url}\" target=\"_blank\" rel=\"noopener\" class=\"text-primary-600 hover:underline\">{$fileName}</a>");
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
