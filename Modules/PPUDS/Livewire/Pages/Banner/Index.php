<?php

namespace Modules\PPUDS\Livewire\Pages\Banner;

use App\View\Components\AppLayout;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Core\Filament\Forms\Components\CreateAction;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\InfoAction;
use Modules\PPUDS\Entities\Banner;

class Index extends Component implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Banner::query()->with(['media', 'translations', 'createdBy'])->latest())
            ->heading(__('Banners'))
            ->emptyStateHeading(__('No banners found'))
            ->emptyStateActions([
                CreateAction::make()
                    ->label(__('Add Banner'))
                    ->visible(fn () => auth()->user()->can('Banner Create')),
            ])
            ->columns([
                ImageColumn::make('image')
                    ->label('')
                    ->getStateUsing(fn (Banner $record) => $record->getImageAttribute())
                    ->height(48)
                    ->width(72)
                    ->extraImgAttributes(['class' => 'rounded-md object-cover']),

                TextColumn::make('name_ar')
                    ->label(__('Name (Arabic)'))
                    ->getStateUsing(fn (Banner $record) => $record->translate('ar')?->name)
                    ->limit(30)
                    ->placeholder('-'),

                TextColumn::make('name_en')
                    ->label(__('Name (English)'))
                    ->getStateUsing(fn (Banner $record) => $record->translate('en')?->name)
                    ->limit(30)
                    ->placeholder('-'),

                TextColumn::make('url_ar')
                    ->label(__('Link (Arabic)'))
                    ->getStateUsing(fn (Banner $record) => $record->translate('ar')?->url)
                    ->limit(40)
                    ->placeholder('-'),

                TextColumn::make('url_en')
                    ->label(__('Link (English)'))
                    ->getStateUsing(fn (Banner $record) => $record->translate('en')?->url)
                    ->limit(40)
                    ->placeholder('-'),

                IconColumn::make('active')
                    ->label(__('Status'))
                    ->boolean(),

                TextColumn::make('createdBy.name')
                    ->label(__('Created By'))
                    ->placeholder(__('System'))
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime('Y-m-d')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions($this->getTableActions())
            ->headerActions([
                CreateAction::make('create')
                    ->label(__('Add Banner'))
                    ->form($this->getFormSchema())
                    ->action(function (array $data) {
                        $this->saveBanner(Banner::create($this->onlyModelData($data)), $data);

                        Toaster::success(__('Banner created successfully'));
                    })
                    ->visible(fn () => auth()->user()->can('Banner Create')),
            ])
            ->bulkActions($this->getTableBulkAction());
    }

    protected function getTableActions(): array
    {
        return [
            InfoAction::make('info')
                ->label('')
                ->visible(fn () => auth()->user()->can('Banner Info')),

            EditAction::make('edit')
                ->form(fn (Banner $record) => $this->getFormSchema($record))
                ->mountUsing(function (Forms\ComponentContainer $form, Banner $record) {
                    $form->fill([
                        'name_ar' => $record->translate('ar')?->name,
                        'name_en' => $record->translate('en')?->name,
                        'url_ar' => $record->translate('ar')?->url,
                        'url_en' => $record->translate('en')?->url,
                        'active' => $record->active,
                    ]);
                })
                ->action(function (Banner $record, array $data) {
                    $record->update($this->onlyModelData($data));

                    $this->saveBanner($record, $data);

                    Toaster::success(__('Banner updated successfully'));
                })
                ->visible(fn () => auth()->user()->can('Banner Update')),

            DeleteAction::make('delete')
                ->action(function ($record) {
                    $this->authorize('Banner Delete');
                    $record->delete();
                    Toaster::success(__('Banner deleted successfully'));
                })
                ->visible(fn () => auth()->user()->can('Banner Delete')),
        ];
    }

    public function getTableBulkAction(): array
    {
        return [
            BulkActionGroup::make([
                BulkAction::make('delete')
                    ->label(__('Delete'))
                    ->requiresConfirmation()
                    ->visible(fn () => auth()->user()->can('Banner Delete'))
                    ->action(fn (Collection $records) => $records->each->delete()),
            ]),
        ];
    }

    protected function getFormSchema(?Banner $record = null): array
    {
        return [
            Grid::make(1)->schema([
                Grid::make(2)->schema([
                    TextInput::make('name_ar')
                        ->label(__('Name (Arabic)'))
                        ->required()
                        ->columnSpan(1),

                    TextInput::make('name_en')
                        ->label(__('Name (English)'))
                        ->required()
                        ->columnSpan(1),

                    TextInput::make('url_ar')
                        ->label(__('Link (Arabic)'))
                        ->url()
                        ->required()
                        ->columnSpan(1),

                    TextInput::make('url_en')
                        ->label(__('Link (English)'))
                        ->url()
                        ->required()
                        ->columnSpan(1),
                ]),

                Toggle::make('active')
                    ->label(__('Active'))
                    ->default(true)
                    ->onColor('success'),

                ...($record ? [
                    Placeholder::make('current_image')
                        ->label('')
                        ->content(fn () => view('core::components.image', ['url' => $record->getImageAttribute()])),
                ] : []),

                // A plain FileUpload (not SpatieMediaLibraryFileUpload) because the
                // latter sets dehydrated(false), so its value never reaches the
                // action's $data. storeFiles(false) keeps the state as a
                // TemporaryUploadedFile, which is what Banner::addImage() expects.
                FileUpload::make('banner_image')
                    ->label(__('Image'))
                    ->image()
                    ->storeFiles(false)
                    ->required(! $record)
                    ->imageResizeMode('cover')
                    ->imageCropAspectRatio('16:9')
                    ->maxSize(10000),
            ]),
        ];
    }

    private function onlyModelData(array $data): array
    {
        return array_intersect_key($data, array_flip((new Banner)->getFillable()));
    }

    private function saveBanner(Banner $banner, array $data): void
    {
        if (! empty($data['name_ar']) || ! empty($data['url_ar'])) {
            $banner->translateOrNew('ar')->fill([
                'name' => $data['name_ar'] ?? null,
                'url' => $data['url_ar'] ?? null,
            ]);
        }

        if (! empty($data['name_en']) || ! empty($data['url_en'])) {
            $banner->translateOrNew('en')->fill([
                'name' => $data['name_en'] ?? null,
                'url' => $data['url_en'] ?? null,
            ]);
        }

        $banner->save();

        if (! empty($data['banner_image'])) {
            $banner->addImage($data['banner_image']);
        }
    }

    public function render()
    {
        return view('ppuds::livewire.pages.banner.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Banners'), 'url' => route('banners.index')],
            ],
        ]);
    }
}
