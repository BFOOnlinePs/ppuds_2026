<?php

namespace Modules\PPUDS\Livewire\Pages\FieldVisit;

use App\View\Components\AppLayout;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Get;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Core\Entities\User;
use Modules\PPUDS\Entities\FieldVisit;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Support\ScopesStudentCompanyVisibility;

class Edit extends Component implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;
    use ScopesStudentCompanyVisibility;

    public ?array $data = [];
    public $record;

    public function mount($fieldVisit)
    {
        $this->record = FieldVisit::findOrFail($fieldVisit);
        $this->form->fill($this->record->toArray());
    }

    public function form(Form $form): Form
    {
        return $form
            ->model($this->record)
            ->schema([
                Grid::make(['default' => 1, 'lg' => 3])
                ->schema([
                    Group::make()
                        ->columnSpan(['lg' => 2])
                        ->schema([
                            Section::make(__('Visit Details'))
                                ->icon('solar-document-add-bold-duotone')
                                ->schema([
                                    Select::make('supervisor_id')
                                        ->label(__('Supervisor'))
                                        ->required()
                                        ->searchable()
                                        ->preload()
                                        ->prefixIcon('solar-user-id-bold-duotone')
                                        ->options(User::pluck('name', 'id'))
                                        ->live()
                                        ->afterStateUpdated(fn (Set $set): mixed => $set('student_company_id', null)),

                                    Select::make('student_company_id')
                                        ->label(__('Student Company'))
                                        ->required()
                                        ->searchable()
                                        ->preload()
                                        ->prefixIcon('solar-user-id-linear')
                                        ->options(fn (Get $get): array => $this->studentCompanyOptions($get('supervisor_id')))
                                        ->disabled(fn (Get $get): bool => blank($get('supervisor_id'))),

                                    TextInput::make('visiting_place')
                                        ->label(__('Visiting Place'))
                                        ->placeholder(__('Enter visiting place'))
                                        ->prefixIcon('solar-map-point-bold-duotone')
                                        ->maxLength(255),

                                    Grid::make(2)->schema([
                                        DatePicker::make('visit_date')
                                            ->label(__('Visit Date'))
                                            ->required()
                                            ->prefixIcon('solar-calendar-date-bold-duotone'),

                                        TimePicker::make('visit_time')
                                            ->label(__('Visit Time'))
                                            ->required()
                                            ->prefixIcon('solar-clock-circle-bold-duotone'),
                                    ]),

                                    TextInput::make('visit_duration')
                                        ->label(__('Duration (Minutes)'))
                                        ->numeric()
                                        ->required()
                                        ->minValue(1)
                                        ->suffix(__('Minutes'))
                                        ->prefixIcon('solar-hourglass-bold-duotone'),
                                ]),
                        ]),

                    Group::make()
                        ->columnSpan(['lg' => 1])
                        ->schema([
                            Section::make(__('Additional Info'))
                                ->icon('solar-notebook-bold-duotone')
                                ->schema([
                                    Textarea::make('notes')
                                        ->label(__('Notes'))
                                        ->rows(5)
                                        ->columnSpanFull(),
                                ]),
                        ]),
                ]),
            ])
            ->statePath('data');
    }

    protected function studentCompanyOptions(mixed $supervisorId): array
    {
        return StudentCompany::query()
            ->with(['registration.student', 'company'])
            ->tap(fn (Builder $query) => $this->applyStudentCompanyVisibilityScope($query))
            ->when(filled($supervisorId), function (Builder $query) use ($supervisorId): void {
                $query->whereHas('registration', function (Builder $registrationQuery) use ($supervisorId): void {
                    $registrationQuery->where('supervisor_id', (int) $supervisorId);
                });
            })
            ->get()
            ->mapWithKeys(function (StudentCompany $studentCompany): array {
                $studentName = $studentCompany->registration?->student?->name ?? 'Unknown Student';
                $companyName = $studentCompany->company?->name ?? 'Unknown Company';

                return [$studentCompany->id => "{$studentName} - {$companyName}"];
            })
            ->toArray();
    }

    protected function studentCompanyBelongsToSupervisor(int $studentCompanyId, int $supervisorId): bool
    {
        return StudentCompany::query()
            ->whereKey($studentCompanyId)
            ->whereHas('registration', function (Builder $registrationQuery) use ($supervisorId): void {
                $registrationQuery->where('supervisor_id', $supervisorId);
            })
            ->exists();
    }

    protected function messages(): array
    {
        return [
            'data.student_company_id.required' => __('Please select a student company.'),
            'data.supervisor_id.required' => __('Please select a supervisor.'),
            'data.visit_date.required' => __('The visit date is required.'),
            'data.visit_time.required' => __('The visit time is required.'),
            'data.visit_duration.required' => __('The duration is required.'),
        ];
    }

    public function save()
    {
        $this->authorize("FieldVisit Update");

        $this->validate();

        if (! $this->studentCompanyBelongsToSupervisor((int) $this->data['student_company_id'], (int) $this->data['supervisor_id'])) {
            $this->addError('data.student_company_id', __('The selected student does not belong to the selected supervisor.'));

            return;
        }

        $this->record->update($this->data);

        Toaster::success(__('Field visit record updated successfully'));

        $this->redirect(route('field-visits.index'));
    }

    public function render()
    {
        return view('ppuds::livewire.pages.field-visit.edit')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Field Visits'), 'url' => route('field-visits.index')],
                ['title' => __('Edit Field Visit'), 'url' => '#'],
            ]
        ]);
    }
}
