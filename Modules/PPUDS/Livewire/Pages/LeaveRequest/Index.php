<?php

namespace Modules\PPUDS\Livewire\Pages\LeaveRequest;

use App\View\Components\AppLayout;
use Filament\Forms;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
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
use Modules\Core\Filament\Forms\Components\CreateAction;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\InfoAction;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Modules\PPUDS\Entities\LeaveRequest;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Enums\LeaveRequestStatus;
use Modules\PPUDS\Enums\LeaveRequestType;

class Index extends Component implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn() => LeaveRequest::query()->latest())
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
                        $data['created_by'] = auth()->id();

                        $data['company_approval'] = LeaveRequestStatus::APPROVED;
                        $data['university_approval'] = LeaveRequestStatus::APPROVED;

                        LeaveRequest::create($data);
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

    // دالة مساعدة لبناء الفورم (لتجنب التكرار بين الإضافة والتعديل والعرض)
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
                            ->disabled($isView) // معطل في وضع العرض
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

//                        SpatieMediaLibraryFileUpload::make('attachment')
//                            ->label(__('Attachment'))
//                            ->collection('leave_requests')
//                            ->downloadable()
//                            ->openable()
//                            ->disabled($isView)
//                            ->columnSpanFull(),
                    ]),
                ]),

            // قسم الموافقات (يظهر فقط في التعديل والعرض، وليس عند الإنشاء الجديد)
            Section::make(__('Approvals & Status'))
                ->visible(!$isCreate)
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('company_approval')
                            ->label(__('Company Approval'))
                            ->options(LeaveRequestStatus::class)
                            ->native(false)
                            ->disabled($isView),

                        Select::make('university_approval')
                            ->label(__('University Approval'))
                            ->options(LeaveRequestStatus::class)
                            ->native(false)
                            ->disabled($isView),

                        Textarea::make('rejection_reason')
                            ->label(__('Rejection Reason (if any)'))
                            ->columnSpanFull()
                            ->disabled($isView),
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
