<?php

namespace App\Services;

use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Collection;

class ExportService
{
    /**
     * Export data to Excel format
     */
    public function exportToExcel(Collection $data, array $headers, string $filename): string
    {
        $export = new class($data, $headers) implements \Maatwebsite\Excel\Concerns\FromCollection, \Maatwebsite\Excel\Concerns\WithHeadings, \Maatwebsite\Excel\Concerns\WithStyles, \Maatwebsite\Excel\Concerns\ShouldAutoSize {
            private $data;
            private $headers;

            public function __construct($data, $headers)
            {
                $this->data = $data;
                $this->headers = $headers;
            }

            public function collection()
            {
                return $this->data;
            }

            public function headings(): array
            {
                return $this->headers;
            }

            public function styles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet)
            {
                return [
                    1 => ['font' => ['bold' => true], 'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2E8F0']]],
                ];
            }
        };

        $filePath = storage_path('app/exports/' . $filename);
        
        // Ensure directory exists
        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }

        Excel::store($export, 'exports/' . $filename);

        return $filePath;
    }

    /**
     * Export data to CSV format
     */
    public function exportToCSV(Collection $data, array $headers, string $filename): string
    {
        $export = new class($data, $headers) implements \Maatwebsite\Excel\Concerns\FromCollection, \Maatwebsite\Excel\Concerns\WithHeadings {
            private $data;
            private $headers;

            public function __construct($data, $headers)
            {
                $this->data = $data;
                $this->headers = $headers;
            }

            public function collection()
            {
                return $this->data;
            }

            public function headings(): array
            {
                return $this->headers;
            }
        };

        $filePath = storage_path('app/exports/' . $filename);
        
        // Ensure directory exists
        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }

        Excel::store($export, 'exports/' . $filename, null, \Maatwebsite\Excel\Excel::CSV);

        return $filePath;
    }

    /**
     * Download Excel file
     */
    public function downloadExcel(Collection $data, array $headers, string $filename)
    {
        $export = new class($data, $headers) implements \Maatwebsite\Excel\Concerns\FromCollection, \Maatwebsite\Excel\Concerns\WithHeadings, \Maatwebsite\Excel\Concerns\WithStyles, \Maatwebsite\Excel\Concerns\ShouldAutoSize {
            private $data;
            private $headers;

            public function __construct($data, $headers)
            {
                $this->data = $data;
                $this->headers = $headers;
            }

            public function collection()
            {
                return $this->data;
            }

            public function headings(): array
            {
                return $this->headers;
            }

            public function styles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet)
            {
                return [
                    1 => ['font' => ['bold' => true], 'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2E8F0']]],
                ];
            }
        };

        return Excel::download($export, $filename);
    }

    /**
     * Download CSV file
     */
    public function downloadCSV(Collection $data, array $headers, string $filename)
    {
        $export = new class($data, $headers) implements \Maatwebsite\Excel\Concerns\FromCollection, \Maatwebsite\Excel\Concerns\WithHeadings {
            private $data;
            private $headers;

            public function __construct($data, $headers)
            {
                $this->data = $data;
                $this->headers = $headers;
            }

            public function collection()
            {
                return $this->data;
            }

            public function headings(): array
            {
                return $this->headers;
            }
        };

        return Excel::download($export, $filename, \Maatwebsite\Excel\Excel::CSV);
    }

    /**
     * Generate filename with timestamp
     */
    public function generateFilename(string $prefix, string $extension = 'xlsx'): string
    {
        return $prefix . '_' . now()->format('Y-m-d_His') . '.' . $extension;
    }

    /**
     * Clean old export files (older than 24 hours)
     */
    public function cleanOldExports(): int
    {
        $exportPath = storage_path('app/exports');
        
        if (!file_exists($exportPath)) {
            return 0;
        }

        $files = glob($exportPath . '/*');
        $deleted = 0;
        $threshold = now()->subDay()->timestamp;

        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $threshold) {
                unlink($file);
                $deleted++;
            }
        }

        return $deleted;
    }
}
