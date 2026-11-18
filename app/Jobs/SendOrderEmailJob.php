<?php

namespace App\Jobs;

use App\Mail\OrderConfirmationMail;
use App\Mail\ParkingLotAlertMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendOrderEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    // public array $backoff = [10, 30, 60];
    public array $backoff = [1, 2,3];

    public function __construct(public array $orderData) {}

    public function handle(): void
    {
        //Approve that messages are persisted in RabbitMQ when the worker is stopped and then processed on restart.
        // sleep(10);

        $orderId = $this->orderData['order_id'] ?? 'Unknown';
        $email = $this->orderData['user_email'] ?? 'Unknown';

        //Simulate a programming error to test DLQ handling. Uncomment the line below to test.
        // $email = null;
        // if (!$email) {
        //     throw new \InvalidArgumentException("Invalid or missing email address for Order #{$orderId}. Data structure might be corrupted.");
        // }

        if (app()->runningInConsole()) {
            $output = new \Symfony\Component\Console\Output\ConsoleOutput();
            $output->writeln("<comment> -> Processing Order #{$orderId} for ({$email})...</comment>");
        }
        // Send the real email through SMTP.
        Mail::to($this->orderData['user_email'])
            ->send(new OrderConfirmationMail($this->orderData));

        if (app()->runningInConsole()) {
            $output->writeln("<info> ✓ Email sent successfully for Order #{$orderId}</info>");
        }
        Log::info('Order confirmation email sent successfully. Order ID: ' . $this->orderData['order_id']);
    }


    public function failed(Throwable $exception): void
    {
        $orderId = $this->orderData['order_id'] ?? 'Unknown';
        $displayName = self::class;
        $errorMessage = $exception->getMessage();

        Log::critical("PARKING LOT ALERT: Order #{$orderId} reached the Parking Lot. Reason: {$errorMessage}");

        $developerEmail = env('DEVELOPER_ALERT_EMAIL', 'ramaalwanni83@gmail.com');
        Mail::to($developerEmail)->send(
            new ParkingLotAlertMail($orderId, $displayName, $errorMessage)
        );
    }
}
