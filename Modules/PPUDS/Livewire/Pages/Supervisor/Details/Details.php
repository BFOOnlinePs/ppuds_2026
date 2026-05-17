<?php

namespace Modules\PPUDS\Livewire\Pages\Supervisor\Details;

use App\View\Components\AppLayout;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Livewire;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Core\Entities\User;
use Modules\PPUDS\Entities\FieldVisit;
use Modules\PPUDS\Entities\StudentCompany;

class Details extends Component implements HasForms, HasInfolists
{
    use InteractsWithForms;
    use InteractsWithInfolists;

    public ?array $data = [];

    public int $userId;

    public function mount(User $user)
    {
        $this->userId = $user->id;

        $this->form->fill($this->userRecord->toArray());
    }

    #[Computed]
    public function userRecord()
    {
        return User::with([
            'roles',
            'media',
        ])->findOrFail($this->userId);
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->userRecord)
            ->schema([
                \Filament\Infolists\Components\Grid::make(5)
                    ->schema([
                        ImageEntry::make('avatar')
                            ->label('')
                            ->getStateUsing(fn ($record) => $record->getAvatarUrlAttribute()),

                        TextEntry::make('name'),

                        TextEntry::make('email'),

                        TextEntry::make('phone'),
                    ]),
            ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->model($this->userRecord)
            ->schema([
                Grid::make(3)
                    ->schema([
                        Tabs::make('tabs')
                            ->tabs([
                                Tabs\Tab::make('personal-information')
                                    ->label(__('Personal Information'))
                                    ->icon('heroicon-o-user')
                                    ->schema([
                                        Grid::make(3)
                                            ->schema([
                                                Grid::make(1)
                                                    ->schema([
                                                        TextInput::make('name')
                                                            ->label(__('Name'))
                                                            ->disabled()
                                                            ->required(),

                                                        TextInput::make('name_en')
                                                            ->label(__('Name (English)'))
                                                            ->disabled(),

                                                        TextInput::make('email')
                                                            ->label(__('Email'))
                                                            ->email()
                                                            ->disabled()
                                                            ->required(),

                                                        TextInput::make('phone')
                                                            ->label(__('Phone'))
                                                            ->disabled(),

                                                        TextInput::make('roles_text')
                                                            ->label(__('Roles'))
                                                            ->default($this->userRecord->roles->pluck('name')->map(fn ($role) => __($role))->implode(', '))
                                                            ->disabled()
                                                            ->dehydrated(false),

                                                        Grid::make(2)
                                                            ->schema([
                                                                TextInput::make('supervised_students_count')
                                                                    ->label(__('Supervised Students Count'))
                                                                    ->default($this->supervisedStudentsCount())
                                                                    ->disabled()
                                                                    ->dehydrated(false),

                                                                TextInput::make('field_visits_count')
                                                                    ->label(__('Field Visits Count'))
                                                                    ->default($this->fieldVisitsCount())
                                                                    ->disabled()
                                                                    ->dehydrated(false),
                                                            ]),
                                                    ])
                                                    ->columnSpan(2),

                                                Grid::make(1)
                                                    ->schema([
                                                        SpatieMediaLibraryFileUpload::make('cover_photo')
                                                            ->disk('media')
                                                            ->collection('cover_photo')
                                                            ->imageEditor()
                                                            ->alignCenter(),

                                                        SpatieMediaLibraryFileUpload::make('avatar')
                                                            ->disk('media')
                                                            ->collection('avatar')
                                                            ->image()
                                                            ->imageEditor()
                                                            ->avatar()
                                                            ->alignCenter(),
                                                    ])
                                                    ->columnSpan(1),
                                            ]),
                                    ]),

                                Tabs\Tab::make('supervised-students')
                                    ->label(__('Supervised Students'))
                                    ->icon('heroicon-o-academic-cap')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                Livewire::make(
                                                    \Modules\PPUDS\Livewire\Pages\Supervisor\Details\StudentCompany\Index::class,
                                                    [
                                                        'supervisorId' => $this->userId,
                                                    ]
                                                )
                                                    ->columnSpanFull()
                                                    ->lazy(),
                                            ]),
                                    ]),

                                Tabs\Tab::make('field-visits')
                                    ->label(__('Field Visits By Supervisor'))
                                    ->icon('solar-clipboard-list-bold-duotone')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                Livewire::make(
                                                    \Modules\PPUDS\Livewire\Pages\Supervisor\Details\FieldVisit\Index::class,
                                                    [
                                                        'supervisorId' => $this->userId,
                                                    ]
                                                )
                                                    ->columnSpanFull()
                                                    ->lazy(),
                                            ]),
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    protected function supervisedStudentsCount(): int
    {
        return $this->supervisedStudentCompaniesQuery()
            ->distinct('student_id')
            ->count('student_id');
    }

    protected function fieldVisitsCount(): int
    {
        return FieldVisit::query()->where('supervisor_id', $this->userId)->count();
    }

    protected function supervisedStudentCompaniesQuery(): Builder
    {
        return StudentCompany::query()
            ->whereHas('registration', fn (Builder $query) => $query->where('supervisor_id', $this->userId));
    }

    public function save()
    {
        abort_unless(auth()->user()?->can('User Update'), 403);

        $this->validate();

        $this->form->model($this->userRecord)->saveRelationships();

        Toaster::success(__('Saved successfully'));

        return redirect()->route('supervisors.details', $this->userId);
    }

    public function render()
    {
        return view('ppuds::livewire.pages.supervisor.details.details')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('University Supervisors List'), 'url' => route('supervisors.index')],
                ['title' => __('University Supervisor Details'), 'url' => route('supervisors.details', $this->userId)],
            ],
        ]);
    }
}
