<?php

namespace Modules\PPUDS\Livewire\Pages\Announcement;

use App\View\Components\AppLayout;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder; // ضروري للفلتر
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Masmerise\Toaster\Toaster;
use Modules\Core\Filament\Forms\Components\CreateAction;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\InfoAction;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Modules\PPUDS\Entities\Announcement;
use Modules\PPUDS\Entities\Major;
use Modules\Core\Enums\UserRole;

class Index extends Component implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn() => Announcement::query()
                ->with('createdBy')
                ->orderByDesc('is_pinned')
                ->latest('published_at'))
            ->columns([
                ImageColumn::make('image')
                    ->label('')
                    ->getStateUsing(fn (Announcement $record) => $record->getImageAttribute())
                    ->height(48)
                    ->width(72)
                    ->extraImgAttributes(['class' => 'rounded-md object-cover']),

                TextColumn::make('name')
                    ->label(__('Title'))
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->limit(48)
                    ->description(fn (Announcement $record) => str($record->content)->stripTags()->limit(90)),

                TextColumn::make('target_roles')
                    ->label(__('Target Roles'))
                    ->badge()
                    ->separator(',')
                    ->formatStateUsing(fn(string $state): string => UserRole::tryFrom($state)?->getLabel() ?? $state)
                    ->color(fn(string $state): string => UserRole::tryFrom($state)?->getColor() ?? 'gray')
                    ->searchable(),

                TextColumn::make('createdBy.name')
                    ->label(__('Publisher'))
                    ->placeholder(__('System'))
                    ->toggleable(),

                TextColumn::make('published_at')
                    ->label(__('Published At'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

                TextColumn::make('expires_at')
                    ->label(__('Expiry Date'))
                    ->dateTime('Y-m-d H:i')
                    ->placeholder(__('No Expiry'))
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_pinned')
                    ->label(__('Pinned'))
                    ->boolean()
                    ->trueColor('warning')
                    ->falseColor('gray'),

                IconColumn::make('is_active')
                    ->label(__('Status'))
                    ->getStateUsing(fn (Announcement $record) => $this->isActive($record))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),
            ])
            ->filters($this->getTableFilters())
            ->actions(
                $this->getTableActions()
            )
            ->headerActions([
                CreateAction::make('create')
                    ->label(__('Add Announcement'))
                    ->form($this->getFormSchema())
                    ->action(function (array $data) {
                        $data['created_by'] = auth()->id();
                        // التأكد من تنظيف الفلاتر إذا لم يكن الطالب ضمن الخيارات
                        if (!isset($data['target_roles']) || !in_array(UserRole::STUDENT->value, $data['target_roles'])) {
                            $data['filters'] = null;
                        }
                        $announcement = Announcement::create($data);

                        if (isset($data['announcement_image'])) {
                            $announcement->addMedia($data['announcement_image'])->toMediaCollection('announcement_image');
                        }

                        Toaster::success(__('Announcement created successfully'));
                    })
                    ->visible(fn() => auth()->user()->can('Announcement Create'))
            ])
            ->bulkActions($this->getTableBulkAction());
    }

    protected function getTableFilters(): array
    {
        return [
            // تعديل: فلتر مخصص للبحث داخل JSON Array
            SelectFilter::make('target_roles')
                ->label(__('Target Role'))
                ->options(UserRole::class)
                ->query(function (Builder $query, array $data) {
                    if (!empty($data['value'])) {
                        // نبحث هل القيمة المختارة موجودة داخل مصفوفة الأدوار في الداتابيز
                        return $query->whereJsonContains('target_roles', $data['value']);
                    }
                    return $query;
                }),
        ];
    }

    public function getTableBulkAction(): array
    {
        return [
            BulkActionGroup::make([
                BulkAction::make('delete')
                    ->label(__('Delete'))
                    ->requiresConfirmation()
                    ->visible(fn() => auth()->user()->can('Announcement Delete'))
                    ->action(fn(Collection $records) => $records->each->delete()),
            ])
        ];
    }

    protected function getTableActions(): array
    {
        return [
            InfoAction::make('info')
                ->label('')
                ->visible(fn() => auth()->user()->can('Announcement Info')),

            ViewAction::make('view')
                ->form(function (Forms\Form $form, $record) {
                    return $form->schema([
                        Section::make(__('Announcement Information'))
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('Title'))
                                    ->default($record->name)
                                    ->disabled(),

                                // عرض الأدوار كنص مفصول بفواصل
                                TextInput::make('target_roles_display')
                                    ->label(__('Target Roles'))
                                    ->default(function () use ($record) {
                                        if (empty($record->target_roles)) return '';
                                        return collect($record->target_roles)
                                            ->map(fn($role) => UserRole::tryFrom($role)?->getLabel())
                                            ->implode(', ');
                                    })
                                    ->disabled(),

                                TextInput::make('major_name')
                                    ->label(__('Specific Major'))
                                    ->default(function () use ($record) {
                                        if (isset($record->filters['major_id'])) {
                                            return Major::find($record->filters['major_id'])?->name;
                                        }
                                        return __('All Majors');
                                    })
                                    // يظهر فقط إذا كان "الطالب" ضمن الأدوار المستهدفة
                                    ->visible(fn() => is_array($record->target_roles) && in_array(UserRole::STUDENT->value, $record->target_roles))
                                    ->disabled(),

                                DateTimePicker::make('published_at')
                                    ->label(__('Published At'))
                                    ->default($record->published_at)
                                    ->disabled(),

                                Toggle::make('is_pinned')
                                    ->label(__('Pinned'))
                                    ->default($record->is_pinned)
                                    ->disabled(),

                                Textarea::make('content')
                                    ->label(__('Content'))
                                    ->default($record->content)
                                    ->columnSpanFull()
                                    ->disabled(),

                                SpatieMediaLibraryFileUpload::make('image')
                                    ->label(__('Attachment / Image'))
                                    ->collection('announcement_image')
                                    ->disabled()
                                    ->downloadable(),
                            ])
                    ]);
                })
                ->modalSubmitAction(false)
                ->visible(fn() => auth()->user()->can('Announcement View')),

            Action::make('details')
                ->label('')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn (Announcement $record) => route('announcements.details', $record))
                ->visible(fn() => auth()->user()->can('Announcement Details')),

            EditAction::make('edit')
                ->form($this->getFormSchema())
                ->mountUsing(function (Forms\ComponentContainer $form, Announcement $record) {
                    $form->fill([
                        'name' => $record->name,
                        'content' => $record->content,
                        'target_roles' => $record->target_roles,
                        'filters' => $record->filters,
                        'published_at' => $record->published_at,
                        'expires_at' => $record->expires_at,
                        'is_pinned' => $record->is_pinned,
                    ]);
                })
                ->action(function (Announcement $record, array $data) {
                    if (!isset($data['target_roles']) || !in_array(UserRole::STUDENT->value, $data['target_roles'])) {
                        $data['filters'] = null;
                    }

                    $record->update($data);

                    if (isset($data['announcement_image'])) {
                        $record->addMedia($data['announcement_image'])->toMediaCollection('announcement_image');
                    }

                    Toaster::success(__('Announcement updated successfully'));
                })
                ->visible(fn() => auth()->user()->can('Announcement Update')),

            DeleteAction::make('delete')
                ->action(function ($record) {
                    $this->authorize('Announcement Delete');
                    $record->delete();
                    Toaster::success(__('Announcement deleted successfully'));
                })
                ->visible(fn() => auth()->user()->can('Announcement Delete'))
        ];
    }

    protected function getFormSchema(): array
    {
        return [
            Grid::make(['default' => 1, 'lg' => 3])->schema([
                Section::make(__('Content'))
                    ->icon('heroicon-o-megaphone')
                    ->columnSpan(['default' => 1, 'lg' => 2])
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Title'))
                            ->required()
                            ->maxLength(255),

                        Textarea::make('content')
                            ->label(__('Content'))
                            ->required()
                            ->rows(7),

                        SpatieMediaLibraryFileUpload::make('announcement_image')
                            ->label(__('Attachment / Image'))
                            ->collection('announcement_image')
                            ->image(),
                    ]),

                Section::make(__('Settings & Targeting'))
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->columnSpan(['default' => 1, 'lg' => 1])
                    ->schema([
                        Select::make('target_roles')
                            ->label(__('Target Roles'))
                            ->options(UserRole::class)
                            ->required()
                            ->multiple() // السماح باختيار أكثر من دور
                            ->live()
                            ->afterStateUpdated(fn(Forms\Set $set) => $set('filters.major_id', null)),

                        Select::make('filters.major_id')
                            ->label(__('Specific Major'))
                            ->options(Major::get()->pluck('name', 'id')) // تأكد من استدعاء get() قبل pluck
                            ->searchable()
                            ->placeholder(__('All Majors'))
                            ->visible(function (Forms\Get $get) {
                                $roles = $get('target_roles');
                                // نتأكد أنها مصفوفة وتحتوي على قيمة الطالب
                                return is_array($roles) && in_array(UserRole::STUDENT->value, $roles);
                            }),

                        DateTimePicker::make('published_at')
                            ->label(__('Publish Date'))
                            ->default(now())
                            ->required(),

                        DateTimePicker::make('expires_at')
                            ->label(__('Expiry Date'))
                            ->minDate(now()),

                        Toggle::make('is_pinned')
                            ->label(__('Pin to Top'))
                            ->onColor('success'),
                    ]),
            ]),
        ];
    }

    private function isActive(Announcement $announcement): bool
    {
        return $announcement->published_at?->lte(now())
            && ($announcement->expires_at === null || $announcement->expires_at->gte(now()));
    }

    public function render()
    {
        return view('ppuds::livewire.pages.announcement.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Announcements List'), 'url' => route('announcements.index')],
            ]
        ]);
    }
}
