<?php

namespace Modules\Core\Services;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Excel as WriterType;
use Maatwebsite\Excel\Exporter;
use Maatwebsite\Excel\Importer;
use Modules\Core\Exports\IterableExport;
use Modules\Core\Interfaces\ExcelServiceInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExcelService implements ExcelServiceInterface
{
    public function __construct(
        protected Exporter $exporter,
        protected Importer $importer,
    ) {}

    public function handle() {}

    public function download(object $export, string $filename, ?string $writerType = null, array $headers = []): BinaryFileResponse
    {
        return $this->exporter->download($export, $filename, $writerType, $headers);
    }

    public function downloadRows(iterable $rows, string $filename, array $headings = [], ?string $writerType = null, array $headers = []): BinaryFileResponse
    {
        return $this->download(new IterableExport($rows, $headings), $filename, $writerType, $headers);
    }

    public function store(object $export, string $path, ?string $disk = null, ?string $writerType = null, mixed $diskOptions = []): bool|PendingDispatch
    {
        return $this->exporter->store($export, $path, $disk, $writerType, $diskOptions);
    }

    public function storeRows(iterable $rows, string $path, array $headings = [], ?string $disk = null, ?string $writerType = null, mixed $diskOptions = []): bool|PendingDispatch
    {
        return $this->store(new IterableExport($rows, $headings), $path, $disk, $writerType, $diskOptions);
    }

    public function queue(object $export, string $path, ?string $disk = null, ?string $writerType = null, mixed $diskOptions = []): PendingDispatch
    {
        return $this->exporter->queue($export, $path, $disk, $writerType, $diskOptions);
    }

    public function raw(object $export, string $writerType = WriterType::XLSX): string
    {
        return $this->exporter->raw($export, $writerType);
    }

    public function rawRows(iterable $rows, array $headings = [], string $writerType = WriterType::XLSX): string
    {
        return $this->raw(new IterableExport($rows, $headings), $writerType);
    }

    public function import(object $import, string|UploadedFile $filePath, ?string $disk = null, ?string $readerType = null): mixed
    {
        return $this->importer->import($import, $filePath, $disk, $readerType);
    }

    public function queueImport(ShouldQueue $import, string|UploadedFile $filePath, ?string $disk = null, ?string $readerType = null): PendingDispatch
    {
        return $this->importer->queueImport($import, $filePath, $disk, $readerType);
    }

    public function toArray(object $import, string|UploadedFile $filePath, ?string $disk = null, ?string $readerType = null): array
    {
        return $this->importer->toArray($import, $filePath, $disk, $readerType);
    }

    public function toCollection(object $import, string|UploadedFile $filePath, ?string $disk = null, ?string $readerType = null): Collection
    {
        return $this->importer->toCollection($import, $filePath, $disk, $readerType);
    }
}
