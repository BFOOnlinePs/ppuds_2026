<?php

namespace Modules\PPUDS\Livewire\Pages\FieldVisit;

use App\View\Components\AppLayout;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
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
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Core\Entities\User;
use Modules\PPUDS\Entities\FieldVisit;
use Modules\PPUDS\Entities\StudentCompany;

class Add extends Component implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;

    public ?array $data = [];

    public function mount()
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->model(FieldVisit::class)
            ->schema([
                Grid::make(['default' => 1, 'lg' => 3])
                ->schema([
                    Group::make()
                        ->columnSpan(['lg' => 2])
                        ->schema([
                            Section::make(__('Visit Details'))
                                ->icon('solar-document-add-bold-duotone')
                                ->schema([
                                    Select::make('student_company_id')
                                        ->label(__('Student Company'))
                                        ->required()
                                        ->searchable()
                                        ->preload()
                                        ->prefixIcon('solar-user-id-linear')
                                        ->options(function () {
                                            return StudentCompany::with(['registration.student', 'company'])
                                                ->get()
                                                ->mapWithKeys(function ($sc) {
                                                    $studentName = $sc->registration?->student?->name ?? 'Unknown Student';
                                                    $companyName = $sc->company?->name ?? 'Unknown Company';
                                                    return [$sc->id => "{$studentName} - {$companyName}"];
                                                });
                                        }),

                                    Select::make('supervisor_id')
                                        ->label(__('Supervisor'))
                                        ->required()
                                        ->searchable()
                                        ->preload()
                                        ->prefixIcon('solar-user-id-bold-duotone')
                                        ->options(User::pluck('name', 'id')),

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
        $this->authorize("FieldVisit Create");

        $this->validate();

        $data = $this->data;
        $data['created_by'] = auth()->id();

        FieldVisit::create($data);

        Toaster::success(__('Field visit record created successfully'));

        $this->redirect(route('field-visits.index'));
    }

    public function render()
    {
        return view('ppuds::livewire.pages.field-visit.add')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Field Visits'), 'url' => route('field-visits.index')],
                ['title' => __('New Field Visit'), 'url' => '#'],
            ]
        ]);
    }
}
