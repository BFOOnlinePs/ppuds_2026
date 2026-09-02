<?php

namespace Modules\Core\Livewire\Pages\MediaLibrary;

use App\View\Components\AppLayout;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Core\Entities\MediaAsset;
use Modules\Core\Filament\Forms\Components\CreateAction;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The media library lists the media table itself, so every file the system
 * already holds is here — avatars, receipts, banners — next to the files the
 * admin uploads straight into the library. Those uploads hang off MediaAsset,
 * which exists only because Spatie always needs an owning model.
 */
class Index extends Component implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Media::query()->latest('id'))
            ->heading(__('Media Library'))
            ->description(fn (): string => $this->storageSummary())
            ->emptyStateHeading(__('No files found'))
            ->emptyStateIcon('solar-gallery-bold-duotone')
            ->emptyStateActions([
                $this->uploadAction(),
            ])
            ->columns([
                Stack::make([
                    ImageColumn::make('preview')
                        ->label('')
                        ->getStateUsing(fn (Media $record) => $this->previewUrl($record))
                        ->height(160)
                        ->extraImgAttributes(['class' => 'w-full rounded-lg object-contain']),

                    TextColumn::make('file_name')
                        ->label(__('File Name'))
                        ->weight('bold')
                        ->limit(32)
                        ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(
                            fn (Builder $inner): Builder => $inner
                                ->where('file_name', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%")
                                ->orWhere('custom_properties->alt_text', 'like', "%{$search}%")
                        )),

                    TextColumn::make('details')
                        ->label('')
                        ->getStateUsing(fn (Media $record): string => collect([
                            $this->dimensions($record),
                            Number::fileSize((int) $record->size, precision: 1),
                        ])->filter()->implode(' · '))
                        ->size('xs')
                        ->color('gray'),

                    TextColumn::make('source')
                        ->label(__('Source'))
                        ->getStateUsing(fn (Media $record): string => $this->source($record))
                        ->size('xs')
                        ->color('gray'),
                ])->space(2),
            ])
            ->contentGrid(['sm' => 2, 'md' => 3, 'xl' => 4])
            ->filters($this->getTableFilters(), layout: FiltersLayout::AboveContent)
            ->actions($this->getTableActions())
            ->headerActions([
                $this->uploadAction(),
            ])
            ->bulkActions($this->getTableBulkAction());
    }

    /** "Files: 12 · Storage Used: 4.2 MB" under the page heading. */
    protected function storageSummary(): string
    {
        $stats = Media::query()
            ->selectRaw('count(*) as files, coalesce(sum(size), 0) as bytes')
            ->first();

        return __('Files') . ': ' . (int) ($stats?->files ?? 0)
            . ' · ' . __('Storage Used') . ': ' . Number::fileSize((int) ($stats?->bytes ?? 0), precision: 1);
    }

    /**
     * The thumbnail when one was generated, the original otherwise. Media
     * owned by other models has its own conversions, so nothing is assumed
     * here beyond what the row itself reports.
     */
    protected function previewUrl(Media $media): ?string
    {
        if (! $this->isImage($media)) {
            return null;
        }

        return $media->hasGeneratedConversion('thumb')
            ? $media->getUrl('thumb')
            : $media->getUrl();
    }

    protected function isImage(Media $media): bool
    {
        return Str::startsWith((string) $media->mime_type, 'image/');
    }

    /**
     * Library uploads carry their dimensions as custom properties; files that
     * arrived through another form are measured on disk instead.
     */
    protected function dimensions(Media $media): ?string
    {
        $width = $media->getCustomProperty('width');
        $height = $media->getCustomProperty('height');

        if (! $width || ! $height) {
            if (! $this->isImage($media)) {
                return null;
            }

            $path = $media->getPath();
            $size = is_file($path) ? @getimagesize($path) : null;

            if (! $size) {
                return null;
            }

            [$width, $height] = $size;
        }

        return $width . '×' . $height;
    }

    /** Where the file came from: the library itself, or the record that owns it. */
    protected function source(Media $media): string
    {
        return $media->model_type === MediaAsset::class
            ? __('Media Library')
            : class_basename((string) $media->model_type) . ' · ' . $media->collection_name;
    }

    protected function uploadAction(): CreateAction
    {
        return CreateAction::make('upload')
            ->label(__('Upload Files'))
            ->modalHeading(__('Upload Files'))
            ->form([
                // A plain FileUpload (not SpatieMediaLibraryFileUpload) because the
                // latter sets dehydrated(false), so its value never reaches the
                // action's $data. storeFiles(false) keeps the state as a
                // TemporaryUploadedFile, which is what MediaAsset::addFile() expects.
                FileUpload::make('files')
                    ->label(__('Files'))
                    ->multiple()
                    ->storeFiles(false)
                    ->required()
                    ->maxSize(20000),

                TextInput::make('alt_text')
                    ->label(__('Alt Text'))
                    ->maxLength(255),
            ])
            ->action(function (array $data) {
                foreach ((array) $data['files'] as $file) {
                    MediaAsset::create()->addFile($file, $data['alt_text'] ?? null);
                }

                Toaster::success(__('Files uploaded successfully'));
            })
            ->visible(fn () => auth()->user()->can('Media Library Create'));
    }

    protected function getTableFilters(): array
    {
        return [
            SelectFilter::make('type')
                ->label(__('File Type'))
                ->options([
                    'image' => __('Images'),
                    'video' => __('Videos'),
                    'document' => __('Documents'),
                ])
                ->native(false)
                ->query(fn (Builder $query, array $data): Builder => $query->when(
                    $data['value'],
                    function (Builder $q, string $type): Builder {
                        if ($type === 'image') {
                            return $q->where('mime_type', 'like', 'image/%');
                        }

                        if ($type === 'video') {
                            return $q->where('mime_type', 'like', 'video/%');
                        }

                        return $q->where('mime_type', 'not like', 'image/%')
                            ->where('mime_type', 'not like', 'video/%');
                    }
                )),

            SelectFilter::make('collection_name')
                ->label(__('Collection'))
                ->options(fn (): array => Media::query()
                    ->select('collection_name')
                    ->distinct()
                    ->orderBy('collection_name')
                    ->pluck('collection_name', 'collection_name')
                    ->all())
                ->native(false)
                ->searchable(),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            ViewAction::make('view')
                ->tooltip(__('View Full Image'))
                ->modalHeading(fn (Media $record): string => $record->file_name)
                ->modalContent(fn (Media $record) => view('core::livewire.pages.media-library.preview', [
                    'media' => $record,
                    'dimensions' => $this->dimensions($record),
                    'isImage' => $this->isImage($record),
                    'source' => $this->source($record),
                ]))
                ->modalWidth('5xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(__('Close')),

            EditAction::make('edit')
                ->tooltip(__('Edit Alt Text'))
                ->form([
                    TextInput::make('alt_text')
                        ->label(__('Alt Text'))
                        ->maxLength(255),
                ])
                ->fillForm(fn (Media $record): array => [
                    'alt_text' => $record->getCustomProperty('alt_text'),
                ])
                ->action(function (Media $record, array $data) {
                    $record->setCustomProperty('alt_text', $data['alt_text'] ?? null);
                    $record->save();

                    Toaster::success(__('File updated successfully'));
                })
                ->visible(fn () => auth()->user()->can('Media Library Update')),

            DeleteAction::make('delete')
                ->action(function (Media $record) {
                    $this->authorize('Media Library Delete');
                    $record->delete();

                    Toaster::success(__('File deleted successfully'));
                })
                ->visible(fn () => auth()->user()->can('Media Library Delete')),
        ];
    }

    public function getTableBulkAction(): array
    {
        return [
            BulkActionGroup::make([
                BulkAction::make('delete')
                    ->label(__('Delete'))
                    ->icon('solar-trash-bin-trash-bold-duotone')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn () => auth()->user()->can('Media Library Delete'))
                    ->action(fn (Collection $records) => $records->each->delete())
                    ->after(fn () => Toaster::success(__('Selected records deleted successfully'))),
            ]),
        ];
    }

    public function render()
    {
        return view('core::livewire.pages.media-library.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Media Library'), 'url' => route('media.index')],
            ],
        ]);
    }
}
