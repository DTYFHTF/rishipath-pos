<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PriceListExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    protected array $rows;

    protected string $generatedAt;

    protected bool $showCost;

    public function __construct(array $rows, string $generatedAt, bool $showCost = false)
    {
        $this->rows = $rows;
        $this->generatedAt = $generatedAt;
        $this->showCost = $showCost;
    }

    public function title(): string
    {
        return 'Price List ' . date('d-M-Y', strtotime($this->generatedAt));
    }

    public function headings(): array
    {
        $headings = ['S.N.', 'Category', 'Product', 'Pack Size'];
        if ($this->showCost) {
            $headings[] = 'Cost Price (NPR)';
        }
        $headings[] = 'Wholesale Price (NPR)';
        $headings[] = 'MRP (NPR)';

        return $headings;
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font'      => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
                'fill'      => ['fillType' => 'solid', 'startColor' => ['rgb' => '2C6E49']],
                'alignment' => ['horizontal' => 'center'],
            ],
        ];
    }
}
