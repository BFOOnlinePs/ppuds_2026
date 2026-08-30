<?php

namespace Modules\PPUDS\Livewire\Pages\Supervisor;

use App\View\Components\AppLayout;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Modules\Core\Filament\Tables\Columns\UserColumn;
use Modules\PPUDS\Entities\FieldVisit;
use Modules\PPUDS\Entities\StudentCompany;
use Spatie\Permission\Models\Role;

class Index extends Component implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => User::query()
                ->with(['roles', 'media'])
                ->where(function (Builder $query) {
                    $query
                        ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', $this->supervisorRoles()))
                        ->orWhereHas('supervisedRegistrations');
                })
            )
            ->columns([
                UserColumn::make('name')
                    ->label(__('Name'))
                    ->user(fn (User $record) => $record)
                    ->subtitle(fn (User $record): ?string => $record->name_en ?: $record->email)
                    ->linksToSupervisor()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label(__('Email'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('phone')
                    ->label(__('Phone'))
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('roles.name')
                    ->label(__('Roles'))
                    ->badge()
                    ->separator(',')
                    ->searchable(),

                TextColumn::make('supervised_students_count')
                    ->label(__('Supervised Students Count'))
                    ->getStateUsing(fn (User $record): int => $this->supervisedStudentsCount($record))
                    ->alignCenter()
                    ->color('primary'),

                TextColumn::make('field_visits_count')
                    ->label(__('Field Visits Count'))
                    ->getStateUsing(fn (User $record): int => FieldVisit::query()->where('supervisor_id', $record->id)->count())
                    ->alignCenter()
                    ->color('primary'),
            ])
            ->filters($this->getTableFilters(), layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(3)
            ->headerActions($this->getTableHeaderActions())
            ->actions($this->getTableActions())
            ->bulkActions([]);
    }

    protected function getTableFilters(): array
    {
        return [
            SelectFilter::make('roles')
                ->label(__('Roles'))
                ->relationship('roles', 'name', fn (Builder $query) => $query->whereIn('name', $this->supervisorRoles()))
                ->multiple()
                ->preload()
                ->searchable(),

            Filter::make('supervisor_details')
                ->label(__('Search Details'))
                ->form([
                    TextInput::make('name')->label(__('Name')),
                    TextInput::make('email')->label(__('Email')),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when(
                            $data['name'],
                            fn (Builder $query) => $query->where('name', 'like', '%'.$data['name'].'%')
                        )
                        ->when(
                            $data['email'],
                            fn (Builder $query) => $query->where('email', 'like', '%'.$data['email'].'%')
                        );
                })
                ->columns(2),
        ];
    }

    protected function getTableHeaderActions(): array
    {
        return [
            Action::make('createSupervisor')
                ->label(__('Add Supervisor'))
                ->icon('heroicon-o-user-plus')
                ->color('primary')
                ->modalHeading(__('Add Supervisor'))
                ->modalSubmitActionLabel(__('Save'))
                ->form([
                    Grid::make(2)->schema([
                        TextInput::make('name')
                            ->label(__('Name'))
                            ->required()
                            ->maxLength(255),

                        TextInput::make('name_en')
                            ->label(__('Name (English)'))
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label(__('Email'))
                            ->email()
                            ->required()
                            ->unique('users', 'email')
                            ->maxLength(255),

                        TextInput::make('phone')
                            ->label(__('Phone'))
                            ->numeric()
                            ->required()
                            ->maxLength(255),
                    ]),
                ])
                ->visible(fn (): bool => $this->canCreateSupervisor())
                ->action(function (array $data): void {
                    abort_unless($this->canCreateSupervisor(), 403);

                    DB::transaction(function () use ($data): void {
                        $data['password'] = Hash::make((string) $data['phone']);

                        $user = User::create($data);
                        $user->assignRole(Role::findOrCreate(UserRole::PRACTICAL_TRAINING_SUPERVISOR->value, 'web'));
                        $user->generateAvatar();
                    });

                    Toaster::success(__('Supervisor created successfully'));
                }),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            ViewAction::make('view')
                ->form(fn (User $record) => [
                    Grid::make(2)->schema([
                        TextInput::make('name')
                            ->label(__('Name'))
                            ->default($record->name)
                            ->disabled(),

                        TextInput::make('email')
                            ->label(__('Email'))
                            ->default($record->email)
                            ->disabled(),

                        TextInput::make('phone')
                            ->label(__('Phone'))
                            ->default($record->phone)
                            ->disabled(),

                        TextInput::make('roles')
                            ->label(__('Roles'))
                            ->default($record->roles->pluck('name')->implode(', '))
                            ->disabled(),

                        TextInput::make('supervised_students_count')
                            ->label(__('Supervised Students Count'))
                            ->default($this->supervisedStudentsCount($record))
                            ->disabled(),

                        TextInput::make('field_visits_count')
                            ->label(__('Field Visits Count'))
                            ->default(FieldVisit::query()->where('supervisor_id', $record->id)->count())
                            ->disabled(),
                    ]),
                ])
                ->modalSubmitAction(false)
                ->visible(fn () => auth()->user()->can('Supervisor View')),

            Action::make('details')
                ->label('')
                ->icon('heroicon-o-user')
                ->url(fn (User $record) => route('supervisors.details', $record))
                ->visible(fn () => auth()->user()->can('Supervisor Details List')),
        ];
    }

    protected function canCreateSupervisor(): bool
    {
        $user = auth()->user();

        return (bool) (
            $user?->can('Supervisor Create')
            || $user?->can('User Create')
            || $user?->hasRole(UserRole::SUPER_ADMIN->value)
        );
    }

    protected function supervisedStudentsCount(User $supervisor): int
    {
        return $this->supervisedStudentCompaniesQuery($supervisor->id)
            ->distinct('student_id')
            ->count('student_id');
    }

    protected function supervisedStudentCompaniesQuery(int $supervisorId): Builder
    {
        return StudentCompany::query()
            ->whereHas('registration', fn (Builder $query) => $query->where('supervisor_id', $supervisorId));
    }

    protected function supervisorRoles(): array
    {
        return [
            UserRole::PRACTICAL_TRAINING_SUPERVISOR->value,
            'Academic Supervisor',
            'University Supervisor',
        ];
    }

    public function render()
    {
        return view('ppuds::livewire.pages.supervisor.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('University Supervisors List'), 'url' => route('supervisors.index')],
            ],
        ]);
    }
}
