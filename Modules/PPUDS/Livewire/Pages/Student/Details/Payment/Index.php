<?php

namespace Modules\PPUDS\Livewire\Pages\Student\Details\Payment;

use App\View\Components\AppLayout;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\InfoAction;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\Course;
use Modules\PPUDS\Entities\Payment;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Enums\SemesterType;
use Modules\PPUDS\Enums\TrainingStatus;
use Modules\PPUDS\Settings\GeneralSettings;

class Index extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public ?int $studentId = null;

    public function mount(?int $studentId = null)
    {
        $this->studentId = $studentId;
        dd(Payment::whereHas('studentCompany', fn ($q) => $q->where('student_id', $this->studentId))->get());
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Payment::query()->with(['studentCompany', 'currency', 'supervisor', 'createdBy'])->whereHas('studentCompany', fn ($q) => $q->where('student_id', $this->studentId)))
            ->columns([
                TextColumn::make('branch.name')
                    ->label(__('Branch'))
                    ->toggleable()
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->sortable(),


                TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime('Y-m-d')
                    ->icon('solar-clock-circle-bold-duotone')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters($this->getTableFilters(), layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(5)
//            ->actions($this->getTableActions())
            ->headerActions([
                \Modules\Core\Filament\Forms\Components\CreateAction::make('create')
                    ->label(__('Add Payment'))
                    ->form(function ($record, $form){
                        return $form->schema([
                            Select::make('student_company_id')
                                ->label(__('Student Company'))
                                ->options(function () {
                                    return StudentCompany::with(['student', 'company', 'branch'])
                                    ->get()
                                        ->mapWithKeys(function ($item) {
                                            $studentName = $item->student->name ?? __('Unknown Student');
                                            $companyName = $item->company->name ?? __('Unknown Company');
                                            $branch      = $item->branch->name  ?? __('Unknown Branch');

                                            return [$item->id => "{$studentName} - {$companyName} - {$branch}"];
                                        });
                                })
                                ->searchable()
                                ->preload(),

                            TextInput::make('payment_value')
                                ->label(__('Payment Value'))
                                ->numeric()
                                ->required(),
                        ]);
                    })
                    ->using(function (array $data){
                        $data['created_by'] = auth()->user()->id;
                        return Payment::create($data);
                    })
                    ->visible(fn () => auth()->user()->can('StudentCompany Create')),
            ])
            ->bulkActions($this->getTableBulkAction());
    }

    protected function getTableFilters(): array
    {
        return [
            // فلتر الحالة
            SelectFilter::make('status')
                ->label(__('Training Status'))
                ->options(TrainingStatus::class)
                ->native(false),

            SelectFilter::make('company_id')
                ->label(__('Company'))
                ->options(Company::get()->pluck('name', 'id'))
                ->searchable()
                ->preload(),

            SelectFilter::make('course')
                ->label(__('Course'))
                ->options(Course::get()->pluck('name', 'id'))
                ->query(function (Builder $query, array $data) {
                    return $query->when($data['value'], function ($q, $courseId) {
                        $q->whereHas('studentCompany.registration', fn ($regQ) => $regQ->where('course_id', $courseId));
                    });
                })
                ->searchable(),

            Filter::make('year')
                ->form([
                    TextInput::make('year')
                        ->label(__('Academic Year'))
                        ->prefixIcon('solar-calendar-search-bold-duotone')
                        ->numeric()
                        ->default(app(GeneralSettings::class)->year)
                        ->placeholder(date('Y')),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query->when(
                        $data['year'],
                        fn (Builder $q, $year) => $q->whereHas('studentCompany.registration', fn ($regQ) => $regQ->where('year', $year))
                    );
                }),

            Filter::make('semester_type')
                ->form([
                    Select::make('semester_type')
                        ->label(__('Semester Type'))
                        ->options(SemesterType::options())
                        ->default(app(GeneralSettings::class)->semester_type->value),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query->when(
                        $data['semester_type'],
                        fn (Builder $q, $semester_type) => $q->whereHas('studentCompany.registration', fn ($regQ) => $regQ->where('semester', $semester_type))
                    );
                }),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            InfoAction::make('info')
                ->label('')
                ->visible(fn () => auth()->user()->can('Major Info')),
            ViewAction::make('view')
                ->label('')
                ->tooltip(__('View Details'))
                ->form(fn (StudentCompany $record) => [
                    Grid::make(2)->schema([
                        TextInput::make('student_name')
                            ->label(__('Student'))
                            ->default($record->registration?->student?->name)
                            ->disabled()
                            ->prefixIcon('solar-user-id-bold-duotone'),

                        TextInput::make('company_name')
                            ->label(__('Company'))
                            ->default($record->company?->name)
                            ->disabled()
                            ->prefixIcon('solar-city-bold-duotone'),

                        TextInput::make('status')
                            ->label(__('Status'))
                            ->default($record->status?->getLabel())
                            ->disabled()
                            ->prefixIcon('solar-flag-bold-duotone'),

                        TextInput::make('course_name')
                            ->label(__('Course'))
                            ->default($record->registration?->course?->name)
                            ->disabled()
                            ->prefixIcon('solar-book-bold-duotone'),
                    ]),
                ])
                ->modalSubmitAction(false)
                ->visible(fn () => auth()->user()->can('StudentCompany View')), // تأكد من اسم الصلاحية

            EditAction::make('edit')
                ->label('')
                ->tooltip(__('Edit'))
                ->url(fn (StudentCompany $record) => route('student-companies.edit', $record->id)) // تأكد من اسم الراوت
                ->visible(fn () => auth()->user()->can('StudentCompany Update')),

            DeleteAction::make('delete')
                ->label('')
                ->tooltip(__('Delete'))
                ->action(function ($record) {
                    $record->delete();
                    Toaster::success(__('Student company record deleted successfully'));
                })
                ->visible(fn () => auth()->user()->can('StudentCompany Delete')),
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
                    ->action(fn (Collection $records) => $records->each->delete())
                    ->after(fn () => Toaster::success(__('Selected records deleted successfully'))),
            ]),
        ];
    }

    public function render()
    {
        return view('ppuds::livewire.pages.student.details.student-company.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Student Companies'), 'url' => route('student-companies.index')],
            ],
        ]);
    }
}
