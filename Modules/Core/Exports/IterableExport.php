<?php

namespace Modules\Core\Exports;

use Generator;
use Illuminate\Contracts\Support\Arrayable;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\WithHeadings;

class IterableExport implements FromGenerator, WithHeadings
{
    public function __construct(
        protected iterable $rows,
        protected array $headings = [],
    ) {}

    public function generator(): Generator
    {
        foreach ($this->rows as $row) {
            yield $row instanceof Arrayable ? $row->toArray() : $row;
        }
    }

    public function headings(): array
    {
        return $this->headings;
    }
}
