<?php

namespace Modules\Core\Interfaces;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Excel as WriterType;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

interface ExcelServiceInterface
{
    public function download(object $export, string $filename, ?string $writerType = null, array $headers = []): BinaryFileResponse;

    public function downloadRows(iterable $rows, string $filename, array $headings = [], ?string $writerType = null, array $headers = []): BinaryFileResponse;

    public function store(object $export, string $path, ?string $disk = null, ?string $writerType = null, mixed $diskOptions = []): bool|PendingDispatch;

    public function storeRows(iterable $rows, string $path, array $headings = [], ?string $disk = null, ?string $writerType = null, mixed $diskOptions = []): bool|PendingDispatch;

    public function queue(object $export, string $path, ?string $disk = null, ?string $writerType = null, mixed $diskOptions = []): PendingDispatch;

    public function raw(object $export, string $writerType = WriterType::XLSX): string;

    public function rawRows(iterable $rows, array $headings = [], string $writerType = WriterType::XLSX): string;

    public function import(object $import, string|UploadedFile $filePath, ?string $disk = null, ?string $readerType = null): mixed;

    public function queueImport(ShouldQueue $import, string|UploadedFile $filePath, ?string $disk = null, ?string $readerType = null): PendingDispatch;

    public function toArray(object $import, string|UploadedFile $filePath, ?string $disk = null, ?string $readerType = null): array;

    public function toCollection(object $import, string|UploadedFile $filePath, ?string $disk = null, ?string $readerType = null): Collection;
}
