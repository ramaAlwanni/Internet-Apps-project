<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ParkingLotAlertMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public string $orderId,
        public string $jobName,
        public string $errorMessage
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "🚨 Critical Alert: Order #{$this->orderId} Reached Parking Lot",
        );
    }

    public function content(): Content
    {
        $html = "
            <div style='font-family: sans-serif; line-height: 1.6; color: #333;'>
                <h2 style='color: #d9534f;'>🚨 Critical Alert: Message Reached Parking Lot</h2>
                <p>All attempts to process and recover the following task have failed and require immediate developer intervention:</p>
                <ul>
                    <li><strong>Order ID:</strong> {$this->orderId}</li>
                    <li><strong>Job Name:</strong> {$this->jobName}</li>
                    <li><strong>Error Reason:</strong> <code style='background: #f8f9fa; padding: 2px 5px; color: #c7254e;'>{$this->errorMessage}</code></li>
                </ul>
                <p>Please review the code, fix the issue, and then replay the message from the <code>order_emails_parking_lot</code> queue.</p>
            </div>
        ";

        return new Content(
            htmlString: $html,
        );
    }
}
