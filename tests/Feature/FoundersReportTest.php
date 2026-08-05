<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReportSchedule;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\ScheduledReportRun;
use App\Models\Store;
use App\Models\Terminal;
use App\Models\User;
use App\Services\ReportScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FoundersReportTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;

    protected Store $store;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::factory()->create(['country_code' => 'NP']);
        $this->store = Store::factory()->create(['organization_id' => $this->org->id]);

        $terminal = Terminal::create([
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'code' => 'T1',
            'name' => 'Counter 1',
            'active' => true,
        ]);

        $this->user = User::create([
            'organization_id' => $this->org->id,
            'name' => 'Cashier',
            'email' => 'cashier@test.local',
            'password' => bcrypt('secret'),
            'active' => true,
        ]);

        $this->terminalId = $terminal->id;
    }

    protected int $terminalId;

    private function sale(float $total, float $cost, string $paymentStatus = 'paid', ?string $at = null): Sale
    {
        $sale = Sale::create([
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'terminal_id' => $this->terminalId,
            'cashier_id' => $this->user->id,
            'receipt_number' => 'R-'.uniqid(),
            'invoice_number' => 'INV-'.uniqid(),
            'date' => $at ? now()->parse($at) : now(),
            'time' => now()->toTimeString(),
            'subtotal' => $total,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => $total,
            'payment_method' => $paymentStatus === 'paid' ? 'cash' : 'credit',
            'payment_status' => $paymentStatus,
            'amount_paid' => $paymentStatus === 'paid' ? $total : 0,
            'status' => 'completed',
        ]);

        $category = Category::firstOrCreate(
            ['organization_id' => $this->org->id, 'name' => 'Spices'],
            ['active' => true]
        );

        $product = Product::create([
            'organization_id' => $this->org->id,
            'category_id' => $category->id,
            'sku' => 'P-'.uniqid(),
            'name' => 'Cumin Seeds',
            'product_type' => 'simple',
            'unit_type' => 'weight',
            'active' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => $product->sku.'-1000',
            'pack_size' => 1000,
            'unit' => 'g',
            'cost_price' => $cost,
            'base_price' => $total,
            'selling_price_nepal' => $total,
            'active' => true,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_variant_id' => $variant->id,
            'product_name' => 'Cumin Seeds',
            'product_sku' => $variant->sku,
            'quantity' => 1,
            'unit' => 'g',
            'price_per_unit' => $total,
            'cost_price' => $cost,
            'subtotal' => $total,
            'discount_amount' => 0,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'total' => $total,
        ]);

        return $sale;
    }

    private function schedule(array $overrides = []): ReportSchedule
    {
        return ReportSchedule::create(array_merge([
            'name' => 'Founders Daily Report',
            'report_type' => 'founders',
            'frequency' => 'daily',
            'parameters' => ['period' => 'today'],
            'recipients' => ['info@shuddhidham.com'],
            'format' => 'pdf',
            'active' => true,
            'next_run_at' => now()->subMinute(),
        ], $overrides));
    }

    public function test_the_report_separates_paid_revenue_from_credit(): void
    {
        $this->sale(total: 1000, cost: 700);              // paid
        $this->sale(total: 5000, cost: 3000, paymentStatus: 'unpaid');  // credit

        $data = app(ReportScheduleService::class)
            ->reportDataFor($this->schedule());

        // Credit is a promise, not money in — it must not inflate revenue.
        $this->assertSame(1000.0, $data['summary']['revenue']);
        $this->assertSame(1, $data['summary']['transactions']);
        $this->assertSame(1, $data['summary']['credit_sales_today']);
        $this->assertSame(5000.0, $data['summary']['credit_amount_today']);
        $this->assertSame(5000.0, $data['summary']['total_outstanding']);
    }

    public function test_it_reports_gross_profit_and_margin(): void
    {
        $this->sale(total: 1000, cost: 700);

        $data = app(ReportScheduleService::class)
            ->reportDataFor($this->schedule());

        $this->assertSame(300.0, $data['summary']['gross_profit']);
        $this->assertSame(30.0, $data['summary']['margin_percent']);
    }

    public function test_it_lists_best_sellers(): void
    {
        $this->sale(total: 1000, cost: 700);
        $this->sale(total: 2000, cost: 1400);

        $data = app(ReportScheduleService::class)
            ->reportDataFor($this->schedule());

        $this->assertNotEmpty($data['top_products']);
        $this->assertSame('Cumin Seeds', $data['top_products'][0]['name']);
        $this->assertSame(3000.0, (float) $data['top_products'][0]['revenue']);
    }

    public function test_yesterdays_sales_are_excluded_from_a_today_report(): void
    {
        $old = $this->sale(total: 9999, cost: 5000);
        $old->forceFill(['created_at' => now()->subDays(2)])->saveQuietly();

        $data = app(ReportScheduleService::class)
            ->reportDataFor($this->schedule());

        $this->assertSame(0.0, $data['summary']['revenue']);
        $this->assertSame(0, $data['summary']['transactions']);
    }

    /** Messages captured by the array transport the test suite mails through. */
    private function sentMessages(): \Illuminate\Support\Collection
    {
        return app('mailer')->getSymfonyTransport()->messages();
    }

    public function test_running_the_schedule_sends_a_real_email_with_the_figures(): void
    {
        $this->sale(total: 1000, cost: 700);
        $schedule = $this->schedule();

        app(ReportScheduleService::class)->generateAndSendReport($schedule);

        $messages = $this->sentMessages();
        $this->assertCount(1, $messages, 'exactly one email should go out');

        $email = $messages->first()->getOriginalMessage();

        $this->assertSame('info@shuddhidham.com', $email->getTo()[0]->getAddress());
        $this->assertStringContainsString('Founders Daily Report', $email->getSubject());

        // Building the message renders the email view, so this also proves the
        // template does not blow up on real data.
        $this->assertNotEmpty($email->getHtmlBody());

        // The PDF is attached, not just generated.
        $attachments = $email->getAttachments();
        $this->assertCount(1, $attachments);
        $this->assertStringContainsString('pdf', strtolower($attachments[0]->getFilename()));

        $this->assertDatabaseHas('scheduled_report_runs', [
            'report_schedule_id' => $schedule->id,
            'status' => 'completed',
        ]);
    }

    public function test_every_valid_recipient_receives_it(): void
    {
        $this->sale(total: 1000, cost: 700);

        app(ReportScheduleService::class)->generateAndSendReport($this->schedule([
            'recipients' => ['info@shuddhidham.com', 'admin@shuddhidham.com'],
        ]));

        $this->assertCount(2, $this->sentMessages());
    }

    public function test_a_due_schedule_is_picked_up_and_rescheduled(): void
    {
        $this->sale(total: 1000, cost: 700);
        $schedule = $this->schedule();

        $processed = app(ReportScheduleService::class)->processDueSchedules();

        $this->assertSame(1, $processed);

        $schedule->refresh();
        $this->assertNotNull($schedule->last_run_at);
        $this->assertTrue($schedule->next_run_at->isFuture(), 'the schedule must move forward or it reruns forever');
    }

    public function test_an_invalid_recipient_is_skipped_without_failing_the_run(): void
    {
        $this->sale(total: 1000, cost: 700);
        $schedule = $this->schedule([
            'recipients' => ['not-an-email', 'info@shuddhidham.com'],
        ]);

        app(ReportScheduleService::class)->generateAndSendReport($schedule);

        // The good address still gets it; the bad one is skipped, not fatal.
        $this->assertCount(1, $this->sentMessages());

        $this->assertDatabaseHas('scheduled_report_runs', [
            'report_schedule_id' => $schedule->id,
            'status' => 'completed',
        ]);
    }

    public function test_the_pdf_template_renders_without_error(): void
    {
        $this->sale(total: 1000, cost: 700);

        $data = app(ReportScheduleService::class)
            ->reportDataFor($this->schedule());

        // The template previously referenced $sale->user, which does not exist
        // on Sale — it only blew up at render time.
        $html = view('reports.scheduled.template', [
            'schedule' => $this->schedule(),
            'reportData' => $data,
        ])->render();

        $this->assertStringContainsString('Founders Daily Report', $html);
        $this->assertStringContainsString('Gross Profit', $html);
        $this->assertStringContainsString('30.0%', $html);
    }

    public function test_an_unknown_report_type_fails_the_run_rather_than_silently_passing(): void
    {
        $schedule = $this->schedule(['report_type' => 'nonsense']);

        try {
            app(ReportScheduleService::class)->generateAndSendReport($schedule);
            $this->fail('an unknown report type should throw');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('Unknown report type', $e->getMessage());
        }

        $this->assertDatabaseHas('scheduled_report_runs', [
            'report_schedule_id' => $schedule->id,
            'status' => 'failed',
        ]);
        $this->assertSame(1, ScheduledReportRun::where('report_schedule_id', $schedule->id)->count());
    }
}
