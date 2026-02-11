<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SupplierLedgerExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    protected array $supplierData;

    protected array $ledgerEntries;

    protected array $summary;

    protected ?string $startDate;

    protected ?string $endDate;

    public function __construct(array $supplierData, array $ledgerEntries, array $summary, ?string $startDate, ?string $endDate)
    {
        $this->supplierData = $supplierData;
        $this->ledgerEntries = $ledgerEntries;
        $this->summary = $summary;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function title(): string
    {
        return 'Supplier Ledger';
    }

    public function headings(): array
    {
        return [
            ['Supplier Ledger Statement'],
            ['Supplier: ' . $this->supplierData['name']],
            ['Period: ' . ($this->startDate ?? 'N/A') . ' to ' . ($this->endDate ?? 'N/A')],
            [],
            ['Date', 'Reference', 'Description', 'Type', 'Paid (₹)', 'Payable (₹)', 'Balance (₹)', 'Status'],
        ];
    }

    public function array(): array
    {
        $data = [];

        foreach ($this->ledgerEntries as $entry) {
            $data[] = [
                $entry['date'],
                $entry['reference'] ?? '',
                $entry['description'],
                $entry['type'],
                number_format($entry['paid'] ?? $entry['debit'] ?? 0, 2),
                number_format($entry['payable'] ?? $entry['credit'] ?? 0, 2),
                number_format($entry['balance'], 2),
                ucfirst($entry['status']),
            ];
        }

        // Add summary rows
        $data[] = [];
        $data[] = ['', '', '', 'TOTAL', number_format($this->summary['total_debit'], 2), number_format($this->summary['total_credit'], 2), '', ''];
        $data[] = ['', '', '', 'Net Amount', '', '', number_format($this->summary['net_amount'], 2), ''];
        $data[] = ['', '', '', 'Current Balance (We Owe)', '', '', number_format($this->summary['current_balance'], 2), ''];

        return $data;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 16]],
            2 => ['font' => ['bold' => true]],
            3 => ['font' => ['italic' => true]],
            5 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E2E8F0'],
                ],
            ],
        ];
    }
}
