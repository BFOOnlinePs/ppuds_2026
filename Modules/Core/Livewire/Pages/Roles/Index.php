<?php

namespace Modules\Core\Livewire\Pages\Roles;

use App\View\Components\AppLayout;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Modules\Core\Filament\Forms\Components\CreateAction;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class Index extends Component implements HasTable, HasForms
{
    use InteractsWithTable, InteractsWithForms;
    protected function getTableQuery(): Builder
    {
        return Role::with('permissions');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns($this->getTableColumns())
            ->headerActions($this->getTableHeaderActions())
            ->actions([])
            ->filters([])
            ->bulkActions([]);
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('name')->label(__('Role Name'))->sortable(),
            TextColumn::make('permissions.name')
                ->label(__('Permissions'))
                // ->formatStateUsing(fn($state) => implode(', ', $state->pluck('name', 'id')->toArray()))
                ->wrap(),
        ];
    }

    protected function getTableHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('Create Role'))
                ->form($this->getFormSchema())
                ->action(fn(array $data) => $this->saveRole($data)),
        ];
    }

    protected function getFormSchema(): array
    {
        $allPermissions   = Permission::all();
        $allPermissionIds = $allPermissions->pluck('id')->toArray();
        $grouped          = $allPermissions->groupBy('module_name');

        // إنشاء الـ sections بجانب بعضها البعض
        $sections = $grouped->map(function ($permissions, $module) {
            $opts = $permissions->pluck('name', 'id')->toArray();
            $ids  = array_keys($opts);
            $key  = 'select_all_' . str_replace(' ', '_', strtolower($module));

            return Section::make(__($module))
                ->columnSpan(1) // كل section يأخذ عمود واحد
                ->schema([
                    Checkbox::make($key)
                        ->label(__('Select All in ' . $module))
                        ->reactive()
                        ->afterStateUpdated(function (bool $state, callable $set, callable $get) use ($ids) {
                            $current = $get('permissions') ?? [];
                            $set(
                                'permissions',
                                $state
                                    ? array_values(array_unique(array_merge($current, $ids)))
                                    : array_values(array_diff($current, $ids))
                            );
                        }),

                    CheckboxList::make('permissions')
                        ->label('')
                        ->options($opts)
                        ->columns(2),
                ]);
        })->values()->toArray();

        return [
            TextInput::make('name')
                ->label(__('Role Name'))
                ->required()
                ->maxLength(50),

            Checkbox::make('select_all')
                ->label(__('Select All Permissions'))
                ->reactive()
                ->afterStateUpdated(
                    fn(bool $state, callable $set) =>
                    $set('permissions', $state ? $allPermissionIds : [])
                ),

            Grid::make([
                'default' => 1,
                'sm' => 2,
                'md' => 2,
                'lg' => 2,
                'xl' => 2,
            ])->schema($sections),
        ];
    }


    protected function saveRole(array $data): void
    {
        // ✔️ الآن $data['name'] و $data['permissions'] موجودين
        $role = Role::create([
            'name' => $data['name'],
        ]);

        $role->permissions()->sync($data['permissions'] ?? []);

        // اختياري: إشعار نجاح
        // session()->flash('success', 'Role created successfully.');
    }

    public function render()
    {
        return view('core::livewire.pages.roles.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Roles And Permissions'), 'url' => route('settings')],
            ]
        ]);
    }
}
