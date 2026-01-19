<?php

declare(strict_types=1);

namespace App\Jobs\Exports;

use App\Enums\OrderType;
use App\Enums\RefundStatus;
use App\Models\Filters\Filter;
use App\Models\Filters\OrderFilter;
use App\Models\Order;
use App\Support\CsvWriter;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Example Export Job for Orders
 * 
 * This demonstrates a complete export implementation with:
 * - Complex data relationships
 * - Financial calculations
 * - Filtering and searching
 * - Chunked processing for large datasets
 */
class Orders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $userId;
    public Filter $filter;
    protected CsvWriter $csvWriter;
    public array $params;
    public string $exportId;

    public function __construct(
        CsvWriter $csvWriter,
        int $userId,
        array $params,
        string $exportId
    ) {
        $this->csvWriter = $csvWriter;
        $this->userId = $userId;
        $this->params = $params;
        $this->exportId = $exportId;
        $this->filter = new OrderFilter(Request::create('', 'GET', $params));
    }

    public function handle(): void
    {
        try {
            $csvContent = $this->csvWriter->write($this->header(), $this->data());

            $environment = app()->environment('production') ? 'production' : 'staging';
            $domain = tenant()?->domains->first()?->domain ?? 'default';
            $fileName = $this->csvFileName().'.csv';
            $path = $environment.'/'.$domain.'/csv/'.$fileName;

            Storage::put($path, $csvContent, ['ACL' => 'public-read']);
            $url = Storage::url($path);

            Cache::put("export:{$this->userId}:{$this->exportId}", [
                'status' => 'success',
                'url' => $url,
            ], now()->addHours(24));
        } catch (\Throwable $exception) {
            Log::error($exception);
            Cache::put("export:{$this->userId}:{$this->exportId}", [
                'status' => 'failed',
                'message' => $exception->getMessage(),
            ], now()->addHours(24));

            throw $exception;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error($exception);

        Cache::put("export:{$this->userId}:{$this->exportId}", [
            'status' => 'failed',
            'message' => $exception->getMessage(),
        ], now()->addHours(24));
    }

    protected function csvFileName(): string
    {
        $fileName = '';

        if (data_get($this->params, 'date_range') !== null) {
            $fileName .= Str::replace(' ', '-', data_get($this->params, 'date_range'));
        }

        if (empty($fileName)) {
            $fileName = Carbon::now()->format('Y-m-d');
        }

        return sprintf('orders-%s-%s', $fileName, Str::random(5));
    }

    protected function header(): array
    {
        return [
            'Order No.',
            'Buyer',
            'Tier Pricing',
            'Order Type',
            'Date of Payment',
            'Payment Method',
            'Status',
            'Fulfillment Status',
            'Registration Amt',
            'Merchandise Amt',
            'Donation Amt',
            'Sub Total',
            'Processing fee',
            'GST',
            'Refund Amt',
            'Total Amt (SGD)',
            'Gateway Fee',
            'Net Amount',
            'No. of Particiapnts',
            'No. of Merchandise',
        ];
    }

    protected function data(): array
    {
        $data = [];

        Order::query()
            ->with(['category', 'user', 'tier', 'participants', 'addOns', 'refunds' => function ($query) {
                $query->where('status', RefundStatus::Completed);
            }])
            ->applySearch(data_get($this->params, 'search'), ['order_number', 'user.name', 'user.email'])
            ->when(data_get($this->params, 'sort'), fn (Builder $builder) => $builder->sortable(), fn (Builder $builder) => $builder->latest())
            ->chunkById(1000, function (Collection $collection) use (&$data) {
                $collection->each(function (Order $order) use (&$data) {
                    // Get gateway fee from payment_meta
                    $paymentMeta = $order->payment_meta ?? [];
                    $gatewayFeeDollars = 0.0;
                    if (isset($paymentMeta['gateway_fee'])) {
                        $gatewayFeeDollars = (float) data_get($paymentMeta, 'gateway_fee', 0);
                    }
                    $gatewayFeeCents = (int) ($gatewayFeeDollars * 100);
                    $netAmount = $order->total_amount - $gatewayFeeCents;

                    // Count participants and merchandise
                    $participantCount = $order->participants->count();
                    $merchandiseCount = $order->addOns->count();

                    // Format amounts as numbers (divide by 100 to convert from cents)
                    $formatAmount = fn (int $amount) => $amount / 100;
                    $formatRefundAmount = fn (int $amount) => $amount > 0 ? -($amount / 100) : 0;
                    $formatGatewayFee = fn (int $amount) => $amount > 0 ? -($amount / 100) : 0;

                    $data[] = [
                        $order->order_number,
                        $order->user?->email ?? '',
                        $order->tier?->name ?? '',
                        $order->types_label,
                        $order->paid_at ? $order->paid_at->format('Y/n/j H:i') : '',
                        $order->payment_method?->label() ?? '',
                        $order->status->label(),
                        $order->fulfillment_status?->label() ?? '',
                        $formatAmount($order->category_price_amount),
                        $formatAmount($order->add_on_amount),
                        $formatAmount($order->donation_amount),
                        $formatAmount($order->subtotal_amount),
                        $formatAmount($order->processing_fee),
                        $formatAmount($order->gst_amount),
                        $formatRefundAmount($order->totalRefundedAmount()),
                        $formatAmount($order->total_amount),
                        $formatGatewayFee($gatewayFeeCents),
                        $formatAmount($netAmount),
                        $participantCount,
                        $merchandiseCount,
                    ];
                });
            });

        return $data;
    }
}
