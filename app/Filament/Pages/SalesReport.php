<?php

namespace App\Filament\Pages;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\ExportService;
use App\Services\StoreContext;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class SalesReport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static string $view = 'filament.pages.sales-report';

    protected static ?string $navigationGroup = 'Reports';

    protected static ?string $navigationLabel = 'Sales Report';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission('view_sales_reports') ?? false;
    }

    public $startDate;

    public $endDate;

    public $storeId = null;

    public $paymentMethod = null;

    protected $listeners = ['store-switched' => 'handleStoreSwitch'];

    public function mount(): void
    {
        $this->startDate = now()->startOfMonth()->format('Y-m-d');
        $this->endDate = now()->format('Y-m-d');
        $this->storeId = StoreContext::getCurrentStoreId();
    }

    public function handleStoreSwitch($storeId): void
    {
        $this->storeId = $storeId;
        $this->dispatch('$refresh');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                DatePicker::make('startDate')
                    ->label('Start Date')
                    ->required(),
                DatePicker::make('endDate')
                    ->label('End Date')
                    ->required(),
                Select::make('storeId')
                    ->label('Store')
                    ->options(\App\Models\Store::pluck('name', 'id'))
                    ->placeholder('All Stores'),
                Select::make('paymentMethod')
                    ->label('Payment Method')
                    ->options([
                        'cash' => 'Cash',
                        'upi' => 'UPI',
                        'card' => 'Card',
                        'esewa' => 'eSewa',
                        'khalti' => 'Khalti',
                    ])
                    ->placeholder('All Methods'),
            ])
            ->columns(4);
    }

    public function getSalesData()
    {
        $query = Sale::query()
            ->whereBetween('date', [$this->startDate, $this->endDate])
            ->where('status', 'completed');

        if ($this->storeId) {
            $query->where('store_id', $this->storeId);
        }

        if ($this->paymentMethod) {
            $query->where('payment_method', $this->paymentMethod);
        }

        return [
            'total_sales' => $query->sum('total_amount'),
            'total_transactions' => $query->count(),
            'total_items_sold' => SaleItem::whereIn('sale_id', $query->pluck('id'))->sum('quantity'),
            'average_sale' => $query->avg('total_amount'),
            'total_tax' => $query->sum('tax_amount'),
            'total_discount' => $query->sum('discount_amount'),
        ];
    }

    public function getSalesByPaymentMethod()
    {
        $query = Sale::query()
            ->whereBetween('date', [$this->startDate, $this->endDate])
            ->where('status', 'completed');

        if ($this->storeId) {
            $query->where('store_id', $this->storeId);
        }

        return $query
            ->select('payment_method', DB::raw('SUM(total_amount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('payment_method')
            ->get();
    }

    public function getTopProducts()
    {
        $saleIds = Sale::query()
            ->whereBetween('date', [$this->startDate, $this->endDate])
            ->where('status', 'completed')
            ->when($this->storeId, fn ($q) => $q->where('store_id', $this->storeId))
            ->pluck('id');

        return SaleItem::query()
            ->whereIn('sale_id', $saleIds)
            ->select('product_name', DB::raw('SUM(quantity) as total_quantity'), DB::raw('SUM(total) as total_revenue'))
            ->groupBy('product_name')
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get();
    }

    public function getDailySales()
    {
        $query = Sale::query()
            ->whereBetween('date', [$this->startDate, $this->endDate])
            ->where('status', 'completed');

        if ($this->storeId) {
            $query->where('store_id', $this->storeId);
        }

        return $query
            ->select('date', DB::raw('SUM(total_amount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    /**
     * Export report to Excel
     */
    public function exportToExcel()
    {
        $salesData = $this->getSalesData();
        $paymentMethods = $this->getSalesByPaymentMethod();
        $topProducts = $this->getTopProducts();
        $dailySales = $this->getDailySales();

        $data = [];

        // Add summary
        $data[] = ['SALES REPORT'];
        $data[] = ['Period', $this->startDate.' to '.$this->endDate];
        if ($this->storeId) {
            $store = \App\Models\Store::find($this->storeId);
            $data[] = ['Store', $store?->name ?? 'Unknown'];
        }
        $data[] = [''];

        // Add summary metrics
        $data[] = ['SUMMARY METRICS'];
        $data[] = ['Total Sales', '₹'.number_format($salesData['total_sales'], 2)];
        $data[] = ['Total Transactions', $salesData['total_transactions']];
        $data[] = ['Total Items Sold', $salesData['total_items_sold']];
        $data[] = ['Average Sale', '₹'.number_format($salesData['average_sale'], 2)];
        $data[] = ['Total Tax', '₹'.number_format($salesData['total_tax'], 2)];
        $data[] = ['Total Discount', '₹'.number_format($salesData['total_discount'], 2)];
        $data[] = [''];

        // Add payment methods
        $data[] = ['SALES BY PAYMENT METHOD'];
        $data[] = ['Payment Method', 'Count', 'Total Amount'];
        foreach ($paymentMethods as $method) {
            $data[] = [
                ucfirst($method->payment_method),
                $method->count,
                '₹'.number_format($method->total, 2),
            ];
        }
        $data[] = [''];

        // Add top products
        $data[] = ['TOP 10 PRODUCTS'];
        $data[] = ['Product Name', 'Quantity Sold', 'Total Revenue'];
        foreach ($topProducts as $product) {
            $data[] = [
                $product->product_name,
                $product->total_quantity,
                '₹'.number_format($product->total_revenue, 2),
            ];
        }
        $data[] = [''];

        // Add daily sales
        $data[] = ['DAILY SALES'];
        $data[] = ['Date', 'Transactions', 'Total Amount'];
        foreach ($dailySales as $day) {
            $data[] = [
                $day->date,
                $day->count,
                '₹'.number_format($day->total, 2),
            ];
        }

        $filename = 'sales_report_'.$this->startDate.'_to_'.$this->endDate;

        return app(ExportService::class)->downloadExcel($data, $filename);
    }
}
