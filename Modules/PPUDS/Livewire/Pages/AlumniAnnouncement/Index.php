<?php

namespace Modules\PPUDS\Livewire\Pages\AlumniAnnouncement;

use App\View\Components\AppLayout;
use Filament\Forms;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
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
use Modules\PPUDS\Entities\AlumniAnnouncement;
use Modules\PPUDS\Enums\AlumniAnnouncementCategory;

class Index extends Component implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => AlumniAnnouncement::query()
                ->with('createdBy')
                ->orderByDesc('is_pinned')
                ->latest('published_at'))
            ->columns([
                ImageColumn::make('image')
                    ->label('')
                    ->getStateUsing(fn (AlumniAnnouncement $record) => $record->getImageAttribute())
                    ->height(48)
                    ->width(72)
                    ->extraImgAttributes(['class' => 'rounded-md object-cover']),

                TextColumn::make('name')
                    ->label(__('Title'))
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->limit(48)
                    ->description(fn (AlumniAnnouncement $record) => str($record->content)->stripTags()->limit(90)),

                TextColumn::make('category')
                    ->label(__('Category'))
                    ->badge(),

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
                    ->getStateUsing(fn (AlumniAnnouncement $record) => $this->isActive($record))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),
            ])
            ->filters($this->getTableFilters())
            ->actions($this->getTableActions())
            ->headerActions([
                CreateAction::make('create')
                    ->label(__('Add Alumni Announcement'))
                    ->form($this->getFormSchema())
                    ->action(function (array $data) {
                        $data['created_by'] = auth()->id();

                        $announcement = AlumniAnnouncement::create($data);

                        if (isset($data['alumni_announcement_image'])) {
                            $announcement->addMedia($data['alumni_announcement_image'])->toMediaCollection('alumni_announcement_image');
                        }

                        Toaster::success(__('Alumni announcement created successfully'));
                    })
                    ->visible(fn () => auth()->user()->can('Alumni Announcement Create')),
            ])
            ->bulkActions($this->getTableBulkAction());
    }

    protected function getTableFilters(): array
    {
        return [
            SelectFilter::make('category')
                ->label(__('Category'))
                ->options(AlumniAnnouncementCategory::class),
        ];
    }

    public function getTableBulkAction(): array
    {
        return [
            BulkActionGroup::make([
                BulkAction::make('delete')
                    ->label(__('Delete'))
                    ->requiresConfirmation()
                    ->visible(fn () => auth()->user()->can('Alumni Announcement Delete'))
                    ->action(fn (Collection $records) => $records->each->delete()),
            ]),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            InfoAction::make('info')
                ->label('')
                ->visible(fn () => auth()->user()->can('Alumni Announcement Info')),

            ViewAction::make('view')
                ->form(function (Forms\Form $form, $record) {
                    return $form->schema([
                        Section::make(__('Announcement Information'))
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('Title'))
                                    ->default($record->name)
                                    ->disabled(),

                                TextInput::make('category_label')
                                    ->label(__('Category'))
                                    ->default($record->category?->getLabel() ?? '-')
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
                                    ->collection('alumni_announcement_image')
                                    ->disabled()
                                    ->downloadable(),
                            ]),
                    ]);
                })
                ->modalSubmitAction(false)
                ->visible(fn () => auth()->user()->can('Alumni Announcement View')),

            EditAction::make('edit')
                ->form($this->getFormSchema())
                ->mountUsing(function (Forms\ComponentContainer $form, AlumniAnnouncement $record) {
                    $form->fill([
                        'name' => $record->name,
                        'category' => $record->category?->value,
                        'content' => $record->content,
                        'published_at' => $record->published_at,
                        'expires_at' => $record->expires_at,
                        'is_pinned' => $record->is_pinned,
                    ]);
                })
                ->action(function (AlumniAnnouncement $record, array $data) {
                    $record->update($data);

                    if (isset($data['alumni_announcement_image'])) {
                        $record->addMedia($data['alumni_announcement_image'])->toMediaCollection('alumni_announcement_image');
                    }

                    Toaster::success(__('Alumni announcement updated successfully'));
                })
                ->visible(fn () => auth()->user()->can('Alumni Announcement Update')),

            DeleteAction::make('delete')
                ->action(function ($record) {
                    $this->authorize('Alumni Announcement Delete');
                    $record->delete();
                    Toaster::success(__('Alumni announcement deleted successfully'));
                })
                ->visible(fn () => auth()->user()->can('Alumni Announcement Delete')),
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

                        Select::make('category')
                            ->label(__('Category'))
                            ->options(AlumniAnnouncementCategory::class)
                            ->required(),

                        Textarea::make('content')
                            ->label(__('Content'))
                            ->required()
                            ->rows(7),

                        SpatieMediaLibraryFileUpload::make('alumni_announcement_image')
                            ->label(__('Attachment / Image'))
                            ->collection('alumni_announcement_image')
                            ->image(),
                    ]),

                Section::make(__('Settings'))
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->columnSpan(['default' => 1, 'lg' => 1])
                    ->schema([
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

    private function isActive(AlumniAnnouncement $announcement): bool
    {
        return $announcement->published_at?->lte(now())
            && ($announcement->expires_at === null || $announcement->expires_at->gte(now()));
    }

    public function render()
    {
        return view('ppuds::livewire.pages.alumni-announcement.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Alumni Announcements'), 'url' => route('alumni-announcements.index')],
            ],
        ]);
    }
}
