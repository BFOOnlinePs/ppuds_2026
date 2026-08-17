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
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
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
use Illuminate\Support\Str;

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
                TableAction::make('permissions')
                    ->label(__('View Permissions'))
                    ->icon('heroicon-o-shield-check')
                    ->color('gray')
                    ->modalHeading(fn (Role $record): string => __('Permissions').': '.__($record->name))
                    ->modalContent(fn (Role $record) => view('core::filament.tables.columns.role-permissions-details', [
                        'summary' => $this->getRolePermissionSummary($record),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('Close'))
                    ->modalWidth('5xl'),

                EditAction::make('edit')
                    ->label(__('Edit'))
                    ->form($this->getFormSchema())
                    ->slideOver()
                    ->visible(fn () => auth()->user()->can('Roles And Permissions Update'))
                    ->mountUsing(function (\Filament\Forms\Form $form, Model $record) {
                        $form->fill([
                            'name' => $record->name,
                            'permissions' => $record->permissions->pluck('id')->toArray(),
                        ]);
                    })
                    ->action(function (Model $record, array $data) {
                        $this->authorize('Roles And Permissions Update');

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
                ->formatStateUsing(fn (string $state): string => __($state))
                ->sortable()
                ->searchable()
                ->width('220px'),

            ViewColumn::make('permissions_summary')
                ->label(__('Permission Groups'))
                ->getStateUsing(fn (Role $record): array => $this->getRolePermissionSummary($record, 12))
                ->view('core::filament.tables.columns.permission-summary'),
        ];
    }

    protected function getTableHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('Create Role'))
                ->form($this->getFormSchema())
                ->action(fn(array $data) => $this->saveRole($data))
                ->visible(fn () => auth()->user()->can('Roles And Permissions Create'))
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
                ])
                ->columns(2), // ترتيب الحقول بجانب بعضها

            // القسم الثاني: شبكة الصلاحيات
            Section::make(__('Assign Permissions'))
                ->description(__('Permissions are grouped by feature. Open only the group you want to edit.'))
                ->collapsible()
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
        $allPermissions = Permission::query()->get();
        $grouped = $allPermissions->groupBy(fn (Permission $permission) => $this->resolvePermissionModule($permission));

        return $grouped
            ->sortKeysUsing(fn (string $a, string $b) => strnatcasecmp($this->translateModuleName($a), $this->translateModuleName($b)))
            ->map(function ($permissions, $module) {
            $permissions = $permissions->sortBy(fn (Permission $permission) => sprintf(
                '%03d-%s',
                $this->permissionSortWeight($permission->name),
                $permission->name
            ));

            $opts = $permissions
                ->mapWithKeys(fn (Permission $permission) => [
                    $permission->id => $this->translatePermissionName($permission->name),
                ])
                ->toArray();
            $ids  = array_keys($opts);
            $key  = 'select_all_' . Str::slug($module, '_');

            return Section::make($this->translateModuleName($module))
                ->description(count($ids).' '.__('Permissions'))
                ->collapsible()
                ->collapsed()
                ->columnSpan(1)
                ->schema([
                    Checkbox::make($key)
                        ->label(__('Select All Permissions'))
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
                        ->bulkToggleable()
                        ->columns([
                            'default' => 1,
                            'md' => 2,
                        ]),
                ]);
        })->values()->toArray();
    }

    private function resolvePermissionModule(Permission $permission): string
    {
        [$module] = $this->splitPermissionName($permission->name);

        return $module ?: ($permission->module_name ?: __('Other'));
    }

    private function getRolePermissionSummary(Role $role, ?int $groupLimit = null): array
    {
        $groups = $role->permissions
            ->groupBy(fn (Permission $permission) => $this->resolvePermissionModule($permission))
            ->map(function ($permissions, string $module): array {
                $permissions = $permissions->sortBy(fn (Permission $permission) => sprintf(
                    '%03d-%s',
                    $this->permissionSortWeight($permission->name),
                    $permission->name
                ));

                return [
                    'label' => $this->translateModuleName($module),
                    'count' => $permissions->count(),
                    'actions' => $permissions
                        ->map(fn (Permission $permission): string => $this->translatePermissionActionFromName($permission->name))
                        ->values()
                        ->toArray(),
                ];
            })
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $visibleGroups = $groupLimit ? $groups->take($groupLimit) : $groups;

        return [
            'total' => $role->permissions->count(),
            'group_count' => $groups->count(),
            'groups' => $visibleGroups->values()->toArray(),
            'more_groups' => $groupLimit ? max($groups->count() - $groupLimit, 0) : 0,
        ];
    }

    private function translatePermissionActionFromName(string $permission): string
    {
        [, $action] = $this->splitPermissionName($permission);

        return $action ? $this->translatePermissionAction($action) : $this->translatePermissionName($permission);
    }

    private function permissionSortWeight(string $permission): int
    {
        [, $action] = $this->splitPermissionName($permission);

        return [
            'View List' => 10,
            'List' => 11,
            'Create' => 20,
            'View' => 30,
            'Info' => 40,
            'View Details' => 50,
            'Details' => 51,
            'Details List' => 52,
            'Update' => 60,
            'Delete' => 70,
            'Submit' => 80,
            'Report List' => 90,
            'Print' => 100,
            'Invoice' => 110,
            'Sync' => 120,
        ][$action] ?? 999;
    }

    private function splitPermissionName(string $permission): array
    {
        $actions = [
            'View Details',
            'Details List',
            'View List',
            'Report List',
            'Branch Pricings',
            'CompanyApprove',
            'UniversityApprove',
            'CompanyFeedback',
            'AcademicFeedback',
            'Create',
            'Update',
            'Delete',
            'View',
            'Info',
            'Details',
            'Submit',
            'Sync',
            'Print',
            'Invoice',
            'List',
        ];

        foreach ($actions as $action) {
            if (Str::endsWith($permission, ' '.$action)) {
                return [trim(Str::beforeLast($permission, ' '.$action)), $action];
            }
        }

        return [$permission, null];
    }

    private function translatePermissionName(string $permission): string
    {
        if (! str_starts_with(app()->getLocale(), 'ar')) {
            return Str::headline($permission);
        }

        [$module, $action] = $this->splitPermissionName($permission);
        $moduleLabel = $this->translateModuleName($module);
        $actionLabel = $action ? $this->translatePermissionAction($action) : null;

        return $actionLabel ? "{$moduleLabel} - {$actionLabel}" : $moduleLabel;
    }

    private function translateModuleName(?string $module): string
    {
        $module = trim((string) $module);
        $module = preg_replace('/\s+Management$/', '', $module);

        if (! str_starts_with(app()->getLocale(), 'ar')) {
            return Str::headline($module);
        }

        return [
            'Addon' => 'الإضافات',
            'Alumni Announcement' => 'إعلانات الخريجين',
            'Alumni Report' => 'تقرير الخريجين',
            'Announcement' => 'الإعلانات',
            'Appointment' => 'المواعيد',
            'Attribute' => 'الخصائص',
            'Banner' => 'البنرات',
            'Branch' => 'الفروع',
            'Brand' => 'العلامات التجارية',
            'Category' => 'التصنيفات',
            'City' => 'المدن',
            'Clinic Setting' => 'إعدادات العيادة',
            'Clinic Survey Question' => 'أسئلة استبيانات العيادة',
            'Company' => 'الشركات',
            'Company Category' => 'تصنيفات الشركات',
            'Company Department' => 'أقسام الشركات',
            'Content' => 'المحتوى',
            'Country' => 'الدول',
            'Coupon' => 'الكوبونات',
            'Course' => 'المساقات',
            'Currency' => 'العملات',
            'Customer' => 'العملاء',
            'Customer Program' => 'برامج العملاء',
            'Dashboard Statistics Verification' => 'التحقق من إحصائيات الداشبورد',
            'Delivery Pricing' => 'تسعير التوصيل',
            'Delivery Zone' => 'مناطق التوصيل',
            'Disease' => 'الأمراض',
            'District' => 'المناطق',
            'Faq' => 'الأسئلة الشائعة',
            'FieldVisit' => 'الزيارات الميدانية',
            'Food' => 'الأطعمة',
            'Food Category' => 'تصنيفات الأطعمة',
            'Food Item' => 'عناصر الطعام',
            'General Settings' => 'الإعدادات العامة',
            'GeoLocation' => 'المواقع الجغرافية',
            'Governorate' => 'المحافظات',
            'Label' => 'الوسوم المرئية',
            'LeaveRequest' => 'طلبات الإذن والمغادرة',
            'Loyalty Rules' => 'قواعد الولاء',
            'Loyalty Tiers' => 'مستويات الولاء',
            'Major' => 'التخصصات',
            'Marketing' => 'التسويق',
            'Note' => 'الملاحظات',
            'Offer' => 'العروض',
            'Order' => 'الطلبات',
            'Page' => 'الصفحات',
            'Product' => 'المنتجات',
            'Program' => 'البرامج',
            'Program Category' => 'تصنيفات البرامج',
            'Program Details' => 'تفاصيل البرامج',
            'Program Instruction' => 'تعليمات البرامج',
            'Program Type Of Meal' => 'أنواع وجبات البرامج',
            'Registration' => 'التسجيلات',
            'Report' => 'التقارير',
            'Roles And Permissions' => 'الأدوار والصلاحيات',
            'Room' => 'الغرف',
            'Setting' => 'الإعدادات',
            'Settings' => 'الإعدادات',
            'Student' => 'الطلاب',
            'StudentAttendance' => 'الحضور والمغادرة',
            'StudentCompany' => 'تدريب الطلاب في الشركات',
            'StudentReport' => 'التقارير اليومية',
            'Sync Company Supervisors' => 'مزامنة مشرفي الشركات',
            'Sync System Data' => 'مزامنة بيانات النظام',
            'Subscription' => 'الاشتراكات',
            'Survey' => 'الاستبيانات',
            'Tag' => 'الوسوم',
            'Type Of Meal' => 'أنواع الوجبات',
            'User' => 'المستخدمون',
            'WorkExperience' => 'الخبرات العملية',
        ][$module] ?? Str::headline($module);
    }

    private function translatePermissionAction(string $action): string
    {
        return [
            'AcademicFeedback' => 'ملاحظات المشرف الأكاديمي',
            'Branch Pricings' => 'تسعير الفروع',
            'CompanyApprove' => 'موافقة الشركة',
            'CompanyFeedback' => 'ملاحظات الشركة',
            'Create' => 'إضافة',
            'Delete' => 'حذف',
            'Details' => 'تفاصيل',
            'Details List' => 'قائمة التفاصيل',
            'Info' => 'معلومات',
            'Invoice' => 'فاتورة',
            'List' => 'قائمة',
            'Print' => 'طباعة',
            'Report List' => 'قائمة التقارير',
            'Submit' => 'تسليم',
            'Sync' => 'مزامنة',
            'UniversityApprove' => 'موافقة الجامعة',
            'Update' => 'تعديل',
            'View' => 'عرض',
            'View Details' => 'عرض التفاصيل',
            'View List' => 'عرض القائمة',
        ][$action] ?? Str::headline($action);
    }

    protected function saveRole(array $data): void
    {
        $this->authorize('Roles And Permissions Create');

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
