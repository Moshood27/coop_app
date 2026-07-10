<?php

namespace App\Observers;

use App\Models\StoreOrder;
use App\Services\PushService;
use App\Services\LedgerService;
use Illuminate\Support\Facades\Log;

class StoreOrderObserver
{
    public function __construct(protected LedgerService $ledgerService)
    {}

    /**
     * Handle the StoreOrder "created" event.
     */
    public function created(StoreOrder $order): void
    {
        $this->handlePushNotification($order);

        if ($order->status === 'murabaha_active' && !$order->ledger_journal_id) {
            $this->recordMurabahahToLedger($order);
        }

        if ($order->status === 'completed' && !$order->ledger_journal_id) {
            $this->recordDirectSaleToLedger($order);
        }
    }

    /**
     * Handle the StoreOrder "updated" event.
     */
    public function updated(StoreOrder $order): void
    {
        if ($order->wasChanged('status')) {
            $this->handlePushNotification($order);

            if ($order->status === 'murabaha_active' && !$order->ledger_journal_id) {
                $this->recordMurabahahToLedger($order);
            }

            if ($order->status === 'completed' && !$order->ledger_journal_id) {
                $this->recordDirectSaleToLedger($order);
            }
        }
    }

    protected function recordMurabahahToLedger(StoreOrder $order): void
    {
        try {
            $journal = $this->ledgerService->recordMurabahahFinancing($order);
            $order->updateQuietly(['ledger_journal_id' => $journal->id]);
        } catch (\Exception $e) {
            \Log::error("Failed to record murabahah in ledger: " . $e->getMessage());
        }
    }

    protected function recordDirectSaleToLedger(StoreOrder $order): void
    {
        try {
            $journal = $this->ledgerService->recordStoreOrder($order);
            $order->updateQuietly(['ledger_journal_id' => $journal->id]);
        } catch (\Exception $e) {
            \Log::error("Failed to record store order in ledger: " . $e->getMessage());
        }
    }

    protected function handlePushNotification(StoreOrder $order): void
    {
        $user = $order->user;
        if (!$user || !$user->fcm_token) return;

        $newStatus = $order->status;
        $oldStatus = $order->getOriginal('status');

        $push = app(PushService::class);
        $title = "Order Status Updated";
        $body = "Your order #{$order->reference} status is now: " . ucfirst(str_replace('_', ' ', (string) $newStatus));
        $send = true;

        if ($order->wasRecentlyCreated) {
            $title = "Order Confirmed";
            $body = "Your order #{$order->reference} has been placed successfully.";

            if ($order->status === 'murabaha_pending') {
                $title = "Financing Application Received";
                $body = "Your Murabaha financing application for order #{$order->reference} has been received and is pending review.";
            }
        } else {
            switch ($newStatus) {
                case 'murabaha_active':
                    if ($oldStatus === 'murabaha_pending') {
                        $title = "Financing Approved! 🎉";
                        $body = "Your Murabaha financing application for order #{$order->reference} has been approved. You can now view your installment schedule.";
                    }
                    break;
                case 'processing':
                    $body = "We are currently processing your order #{$order->reference}. We will notify you once it's ready.";
                    break;
                case 'completed':
                    $title = "Order Completed";
                    $body = "Your order #{$order->reference} has been completed/delivered. Thank you for shopping with us!";
                    break;
                case 'cancelled':
                    $title = "Order Cancelled";
                    $body = "Your order #{$order->reference} has been cancelled.";
                    break;
                case 'paid':
                    $body = "Payment for your order #{$order->reference} has been confirmed.";
                    break;
                default:
                    $send = false;
                    break;
            }
        }

        if ($send) {
            $push->send($user->fcm_token, $title, $body, [
                'type' => 'order_update',
                'order_id' => (string) $order->id,
                'route' => "/store/orders/{$order->id}",
            ]);
        }
    }
}
