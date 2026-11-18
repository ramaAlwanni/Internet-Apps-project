<?php

namespace Tests\Feature;

use App\Jobs\SendOrderEmailJob;
use Tests\TestCase;

class OrderEmailQueueTest extends TestCase
{
    private function getSampleOrderPayload(int $orderId = 999): array
    {
        return [
            'order_id' => $orderId,
            'user_email' => 'ramaalwanni83@gmail.com',
            'user_name' => 'Rama Alwanni',
            'total_price' => 5000,
            'items' => [
                [
                    'product_name' => 'Test Product gRPC',
                    'quantity' => 2,
                    'unit_price' => 2500,
                    'subtotal' => 5000,
                ],
            ],
        ];
    }

    public function test_1_dispatch_real_message_to_rabbitmq(): void
    {
        $payload = $this->getSampleOrderPayload(1001);

        SendOrderEmailJob::dispatch($payload)->onQueue('order_emails_queue');

        fwrite(STDOUT, "\n[✔] Test 1: Message for Order #1001 pushed to RabbitMQ (order_emails_queue).\n");
        $this->assertTrue(true);
    }
}
