<?php

namespace Modules\Core\Traits;

use BackedEnum;
use DateTimeInterface;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Support\Contracts\HasLabel;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\Column;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Masmerise\Toaster\Toaster;
use Modules\Core\Filament\Tables\Columns\UserColumn;
use Modules\Core\Services\PdfService;
use Throwable;

/**
 * Adds a "Print PDF" header action to any Filament table page: the user picks
 * which of the table's own columns to print, and the current filters/search
 * are carried over to the printed document.
 *
 * Cell values are read back through the table's own column definitions, so a
 * page gets printing without restating its columns anywhere.
 */
trait PrintsTableReportPdf
{
    /**
     * mPDF renders the whole document in memory, so a filter that matches the
     * entire table would exhaust it. Pages may raise or lower this.
     */
    protected int $printPdfMaxRows = 2000;

    protected function printPdfAction(): Action
    {
        return Action::make('print_pdf')
            ->label(__('Print PDF'))
            ->icon('solar-printer-bold')
            ->color('info')
            ->modalHeading(__('Print PDF'))
            ->modalDescription(__('Select the columns you want to include in the printed report.'))
            ->modalSubmitActionLabel(__('Print'))
            ->modalWidth('2xl')
            ->form([
                CheckboxList::make('columns')
                    ->label(__('Columns To Print'))
                    ->options(fn (): array => $this->printablePdfColumnOptions())
                    ->default(fn (): array => $this->defaultPrintablePdfColumns())
                    ->columns(3)
                    ->bulkToggleable()
                    ->required(),

                Radio::make('orientation')
                    ->label(__('Page Orientation'))
                    ->options([
                        'L' => __('Landscape'),
                        'P' => __('Portrait'),
                    ])
                    ->default('L')
                    ->inline()
                    ->inlineLabel(false),
            ])
            ->action(fn (array $data) => $this->streamTableReportPdf(
                (array) ($data['columns'] ?? []),
                $data['orientation'] ?? 'L',
            ));
    }

    /** name => label, for every column the table declares. */
    protected function printablePdfColumnOptions(): array
    {
        return collect($this->getTable()->getColumns())
            ->mapWithKeys(fn (Column $column, string $name): array => [
                $name => $this->printablePdfColumnLabel($column, $name),
            ])
            ->all();
    }

    /** Pre-ticks the columns the table shows before any toggling. */
    protected function defaultPrintablePdfColumns(): array
    {
        return collect($this->getTable()->getColumns())
            ->reject(fn (Column $column): bool => $column->isToggledHiddenByDefault())
            ->keys()
            ->all();
    }

    protected function streamTableReportPdf(array $columnNames, string $orientation = 'L')
    {
        $columns = $this->printablePdfColumns($columnNames);

        if ($columns === []) {
            Toaster::error(__('Select at least one column to print.'));

            return null;
        }

        $query = $this->tableReportPdfQuery();
        $recordsCount = (clone $query)->count();

        if ($recordsCount > $this->printPdfMaxRows) {
            Toaster::error(__('The filtered results (:count) exceed the print limit of :max records. Please narrow your filter and try again.', [
                'count' => $recordsCount,
                'max' => $this->printPdfMaxRows,
            ]));

            return null;
        }

        return app(PdfService::class)->streamPdf(
            $this->tableReportPdfView(),
            [
                'title' => $this->tableReportPdfTitle(),
                'headings' => array_map(
                    fn (Column $column, string $name): string => $this->printablePdfColumnLabel($column, $name),
                    array_values($columns),
                    array_keys($columns),
                ),
                'rows' => $this->tableReportPdfRows($query, $columns),
            ],
            $this->tableReportPdfFilename(),
            ['orientation' => $orientation === 'P' ? 'P' : 'L'],
        );
    }

    /**
     * The records to print. Overridable by pages whose export query needs
     * trimming before it is fully hydrated.
     */
    protected function tableReportPdfQuery(): Builder
    {
        return $this->getTableQueryForExport();
    }

    protected function tableReportPdfRows(Builder $query, array $columns): Collection
    {
        return $query->get()->map(
            fn (Model $record): array => array_map(
                fn (Column $column): string => $this->printablePdfCellValue($column, $record),
                array_values($columns),
            )
        );
    }

    /** @return array<string, Column> the chosen columns, in table order */
    protected function printablePdfColumns(array $columnNames): array
    {
        return collect($this->getTable()->getColumns())
            ->only($columnNames)
            ->all();
    }

    protected function printablePdfColumnLabel(Column $column, string $name): string
    {
        $label = $column->getLabel();

        if ($label instanceof Htmlable) {
            $label = $label->toHtml();
        }

        $label = trim(strip_tags((string) $label));

        return $label !== '' ? $label : str($name)->afterLast('.')->headline()->toString();
    }

    /**
     * Reads one cell the way the table itself would, then flattens it to plain
     * text: HTML cells (avatars, badges, links) become their text content.
     */
    protected function printablePdfCellValue(Column $column, Model $record): string
    {
        $column->record($record);

        if ($column instanceof UserColumn) {
            return $this->normalisePrintableValue($column->resolveUser($record)?->name);
        }

        try {
            $state = $column->getState();
        } catch (Throwable) {
            return '—';
        }

        // Formatting an HTML column runs it through the sanitiser, which
        // expects a string; the raw state already carries the text we want.
        if (! (method_exists($column, 'isHtml') && $column->isHtml())) {
            try {
                $state = $column->formatState($state);
            } catch (Throwable) {
                // Fall back to the unformatted state.
            }
        }

        return $this->normalisePrintableValue($state);
    }

    protected function normalisePrintableValue(mixed $value): string
    {
        if ($value instanceof Htmlable) {
            $value = $value->toHtml();
        }

        if ($value instanceof HasLabel) {
            $value = $value->getLabel();
        }

        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        if ($value instanceof DateTimeInterface) {
            $value = $value->format('Y-m-d H:i');
        }

        if ($value instanceof Collection) {
            $value = $value->all();
        }

        if (is_array($value)) {
            $value = implode(', ', array_map(fn ($item): string => $this->normalisePrintableValue($item), $value));
        }

        if (is_bool($value)) {
            $value = $value ? __('Yes') : __('No');
        }

        $value = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $value), ENT_QUOTES, 'UTF-8')) ?? '');

        return $value !== '' ? $value : '—';
    }

    protected function tableReportPdfTitle(): string
    {
        return __('Report');
    }

    protected function tableReportPdfView(): string
    {
        return 'core::pdf.table-report';
    }

    protected function tableReportPdfFilename(): string
    {
        return str($this->tableReportPdfTitle())->slug()->toString().'-'.now()->format('Y-m-d-His').'.pdf';
    }
}
