<?php

namespace App\Notifications;

use App\Models\BulkOrderInquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewBulkOrderInquiry extends Notification
{
    use Queueable;

    public function __construct(
        protected BulkOrderInquiry $inquiry
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New Bulk Order Inquiry',
            'message' => "{$this->inquiry->name} submitted a bulk order inquiry" .
                ($this->inquiry->company_name ? " from {$this->inquiry->company_name}" : ''),
            'inquiry_id' => $this->inquiry->id,
            'products_count' => is_array($this->inquiry->products) ? count($this->inquiry->products) : 0,
            'total_quantity' => $this->inquiry->total_quantity,
            'city' => $this->inquiry->shipping_city,
            'store' => $this->inquiry->retailStore?->store_name,
        ];
    }
}
