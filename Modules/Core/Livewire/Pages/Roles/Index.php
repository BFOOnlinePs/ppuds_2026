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
use Modules\Core\Filament\Forms\Components\EditAction;
use Illuminate\Database\Eloquent\Model;

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
            ->actions([
                EditAction::make('edit') 
                    ->label(__('Edit'))
                    ->form($this->getFormSchema())
                    ->slideOver()
                    ->mountUsing(function (\Filament\Forms\Form $form, Model $record) {
                        $form->fill([
                            'name' => $record->name,
                            'permissions' => $record->permissions->pluck('id')->toArray(),
                        ]);
                    })
                    ->action(function (Model $record, array $data) {
                        $record->update(['name' => $data['name']]);
                        $record->permissions()->sync($data['permissions'] ?? []);
                    }),
            ])
            ->filters([])
            ->bulkActions([]);
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('name')
                ->label(__('Role Name'))
                ->sortable()
                ->searchable(), // إضافة البحث قد تكون مفيدة هنا
            
            TextColumn::make('permissions.name')
                ->label(__('Permissions'))
                ->wrap()
                ->badge(), // عرض الصلاحيات كشريط (Badge) يعطي مظهر أفضل (اختياري)
        ];
    }

    protected function getTableHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('Create Role'))
                ->form($this->getFormSchema())
                ->action(fn(array $data) => $this->saveRole($data))
                ->slideOver(), // فتح الفورم من الجانب يعطي تجربة مستخدم أفضل (اختياري)
        ];
    }

    // ==========================================
    // Form & Actions Logic
    // ==========================================

    protected function getFormSchema(): array
    {
        $allPermissionIds = Permission::pluck('id')->toArray();

        return [
            // القسم الأول: تفاصيل الصلاحية
            Section::make(__('Role Details'))
                ->schema([
                    TextInput::make('name')
                        ->label(__('Role Name'))
                        ->required()
                        ->maxLength(50)
                        ->columnSpan(1),

                    Checkbox::make('select_all')
                        ->label(__('Select All Permissions'))
                        ->reactive()
                        ->afterStateUpdated(
                            fn(bool $state, callable $set) =>
                            $set('permissions', $state ? $allPermissionIds : [])
                        )
                        ->columnSpan(1),
                ])->columns(2), // ترتيب الحقول بجانب بعضها

            // القسم الثاني: شبكة الصلاحيات
            Section::make(__('Assign Permissions'))
                ->schema([
                    Grid::make([
                        'default' => 1,
                        'sm'      => 2,
                        'md'      => 2,
                        'lg'      => 2,
                        'xl'      => 2,
                    ])->schema($this->getPermissionSections()),
                ]),
        ];
    }

    /**
     * استخراج وبناء أقسام الصلاحيات بناءً على الموديول
     */
    private function getPermissionSections(): array
    {
        $allPermissions = Permission::all();
        $grouped        = $allPermissions->groupBy('module_name');

        return $grouped->map(function ($permissions, $module) {
            $opts = $permissions->pluck('name', 'id')->toArray();
            $ids  = array_keys($opts);
            $key  = 'select_all_' . str_replace(' ', '_', strtolower($module));

            return Section::make(__($module))
                ->columnSpan(1)
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
    }

    protected function saveRole(array $data): void
    {
        $role = Role::create([
            'name' => $data['name'],
        ]);

        if (!empty($data['permissions'])) {
            $role->permissions()->sync($data['permissions']);
        }

        // Filament يتعامل مع إشعارات الـ Actions تلقائياً إذا استخدمت Notification::make()
        // \Filament\Notifications\Notification::make()
        //     ->title('Role created successfully')
        //     ->success()
        //     ->send();
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