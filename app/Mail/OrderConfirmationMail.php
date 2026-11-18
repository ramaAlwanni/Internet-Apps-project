<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public array $orderData) {}

    public function build()
    {
        return $this->subject('Order Receipt Confirmation #' . $this->orderData['order_id'])
            ->html("
                <p>Hello {$this->orderData['user_name']},</p>
                <p>Your order has been successfully received and is now being processed.</p>
                <p><strong>Order Number:</strong> {$this->orderData['order_id']}</p>
                <p><strong>Total Amount:</strong> {$this->orderData['total_price']}</p>
            ");
    }
}
