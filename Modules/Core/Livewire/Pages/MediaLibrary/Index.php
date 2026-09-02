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
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Core\Entities\MediaAsset;
use Modules\Core\Filament\Forms\Components\CreateAction;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The shared media library: every file the admin uploads on its own, listed
 * as a grid of cards with the full size original one click away.
 */
class Index extends Component implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => MediaAsset::query()->with(['media', 'createdBy'])->latest())
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
                        ->getStateUsing(fn (MediaAsset $record) => $record->preview_url)
                        ->height(160)
                        ->extraImgAttributes(['class' => 'w-full rounded-lg object-contain']),

                    TextColumn::make('file_name')
                        ->label(__('File Name'))
                        ->getStateUsing(fn (MediaAsset $record) => $record->file?->file_name)
                        ->weight('bold')
                        ->limit(32)
                        ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(
                            fn (Builder $inner): Builder => $inner
                                ->whereHas('media', fn (Builder $media): Builder => $media
                                    ->where('file_name', 'like', "%{$search}%")
                                    ->orWhere('name', 'like', "%{$search}%"))
                                ->orWhere('alt_text', 'like', "%{$search}%")
                        )),

                    TextColumn::make('details')
                        ->label('')
                        ->getStateUsing(fn (MediaAsset $record): string => collect([
                            $record->dimensions,
                            $record->file ? Number::fileSize($record->file->size, precision: 1) : null,
                        ])->filter()->implode(' · '))
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
            ->where('model_type', MediaAsset::class)
            ->selectRaw('count(*) as files, coalesce(sum(size), 0) as bytes')
            ->first();

        return __('Files') . ': ' . (int) ($stats?->files ?? 0)
            . ' · ' . __('Storage Used') . ': ' . Number::fileSize((int) ($stats?->bytes ?? 0), precision: 1);
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
                    $asset = MediaAsset::create(['alt_text' => $data['alt_text'] ?? null]);

                    $asset->addFile($file);
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
                    fn (Builder $q, string $type): Builder => $q->whereHas('media', function (Builder $media) use ($type): void {
                        if ($type === 'image') {
                            $media->where('mime_type', 'like', 'image/%');
                        } elseif ($type === 'video') {
                            $media->where('mime_type', 'like', 'video/%');
                        } else {
                            $media->where('mime_type', 'not like', 'image/%')
                                ->where('mime_type', 'not like', 'video/%');
                        }
                    })
                )),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            ViewAction::make('view')
                ->tooltip(__('View Full Image'))
                ->modalHeading(fn (MediaAsset $record): string => $record->file?->file_name ?? __('Media Library'))
                ->modalContent(fn (MediaAsset $record) => view('core::livewire.pages.media-library.preview', [
                    'asset' => $record,
                ]))
                ->modalWidth('5xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(__('Close')),

            EditAction::make('edit')
                ->form([
                    TextInput::make('alt_text')
                        ->label(__('Alt Text'))
                        ->maxLength(255),
                ])
                ->fillForm(fn (MediaAsset $record): array => ['alt_text' => $record->alt_text])
                ->action(function (MediaAsset $record, array $data) {
                    $record->update(['alt_text' => $data['alt_text'] ?? null]);

                    Toaster::success(__('File updated successfully'));
                })
                ->visible(fn () => auth()->user()->can('Media Library Update')),

            DeleteAction::make('delete')
                ->action(function (MediaAsset $record) {
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
