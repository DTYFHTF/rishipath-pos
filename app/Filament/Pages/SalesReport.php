<?php

namespace App\Filament\Pages;

use App\Models\Sale;
use App\Models\SaleItem;
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

    public function mount(): void
    {
        $this->startDate = now()->startOfMonth()->format('Y-m-d');
        $this->endDate = now()->format('Y-m-d');
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
                    ->relationship('store', 'name')
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
            ->columns(4)
            ->statePath('data');
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
            ->when($this->storeId, fn($q) => $q->where('store_id', $this->storeId))
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
}
