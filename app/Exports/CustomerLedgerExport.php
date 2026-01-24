<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CustomerLedgerExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    protected array $customerData;

    protected array $ledgerEntries;

    protected array $summary;

    protected ?string $startDate;

    protected ?string $endDate;

    public function __construct(array $customerData, array $ledgerEntries, array $summary, ?string $startDate, ?string $endDate)
    {
        $this->customerData = $customerData;
        $this->ledgerEntries = $ledgerEntries;
        $this->summary = $summary;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function title(): string
    {
        return 'Customer Ledger';
    }

    public function headings(): array
    {
        return [
            ['Customer Ledger Statement'],
            ['Customer: ' . $this->customerData['name']],
            ['Period: ' . ($this->startDate ?? 'N/A') . ' to ' . ($this->endDate ?? 'N/A')],
            [],
            ['Date', 'Reference', 'Description', 'Type', 'Debit (₹)', 'Credit (₹)', 'Balance (₹)', 'Status'],
        ];
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->ledgerEntries as $entry) {
            $rows[] = [
                $entry['date'],
                $entry['reference'] ?? '-',
                $entry['description'],
                ucwords(str_replace('_', ' ', $entry['type'])),
                $entry['debit'] > 0 ? number_format($entry['debit'], 2) : '-',
                $entry['credit'] > 0 ? number_format($entry['credit'], 2) : '-',
                number_format($entry['balance'], 2),
                ucfirst($entry['status']),
            ];
        }

        // Add summary row
        $rows[] = [];
        $rows[] = [
            '', '', '', 'TOTAL:',
            number_format($this->summary['total_debit'], 2),
            number_format($this->summary['total_credit'], 2),
            number_format($this->summary['current_balance'], 2),
            '',
        ];
        $rows[] = [];
        $rows[] = ['Summary'];
        $rows[] = ['Total Debit', number_format($this->summary['total_debit'], 2)];
        $rows[] = ['Total Credit', number_format($this->summary['total_credit'], 2)];
        $rows[] = ['Net Amount', number_format($this->summary['net_amount'], 2)];
        $rows[] = ['Current Balance', number_format($this->summary['current_balance'], 2)];
        $rows[] = ['Outstanding', number_format($this->summary['outstanding'], 2)];

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 16]],
            2 => ['font' => ['bold' => true]],
            3 => ['font' => ['italic' => true]],
            5 => ['font' => ['bold' => true], 'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E5E7EB'],
            ]],
        ];
    }
}
