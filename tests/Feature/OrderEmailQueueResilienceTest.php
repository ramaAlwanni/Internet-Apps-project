<?php

namespace Tests\Feature;

use App\Jobs\SendOrderEmailJob;
use Illuminate\Support\Facades\Artisan;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Tests\TestCase;

/**
 * Proofs for requirement 4: messages survive a stopped worker, failed jobs
 * reach the DLQ, and the recovery command drains the DLQ.
 *
 * NOTE: test_message_survives_when_worker_is_stopped() must be run while the
 * rabbitmq-worker container is stopped. The DLQ test needs the worker running.
 */
class OrderEmailQueueResilienceTest extends TestCase
{
    private function queueMessageCount(string $queue): int
    {
        $config = config('queue.connections.rabbitmq');
        $host = $config['hosts'][0];

        $connection = new AMQPStreamConnection(
            $host['host'],
            (int) $host['port'],
            $host['user'],
            $host['password'],
            $host['vhost']
        );

        try {
            $channel = $connection->channel();
            // Passive declare only reads the queue, it never consumes a message.
            [, $messageCount] = $channel->queue_declare($queue, true);
            $channel->close();

            return $messageCount;
        } finally {
            $connection->close();
        }
    }

    private function payload(string $orderId, string $email): array
    {
        return [
            'order_id' => $orderId,
            'user_email' => $email,
            'user_name' => 'Resilience Test',
            'total_price' => 100,
            'items' => [],
        ];
    }

    public function test_message_survives_when_worker_is_stopped(): void
    {
        $before = $this->queueMessageCount('order_emails_queue');
        $orderId = 'STOPPED-'.now()->format('His');
        $email = env('DEVELOPER_ALERT_EMAIL', 'ramaalwanni83@gmail.com');

        SendOrderEmailJob::dispatch($this->payload($orderId, $email))
            ->onQueue('order_emails_queue');

        $after = $this->queueMessageCount('order_emails_queue');

        fwrite(STDOUT, "\n[✔] worker stopped: queue before={$before}, after={$after}, order={$orderId}\n");
        $this->assertGreaterThan($before, $after, 'Message was not persisted in order_emails_queue.');
    }

    public function test_failed_job_reaches_dlq_and_recovery_drains_it(): void
    {
        $dlqBefore = $this->queueMessageCount('order_emails_dlq');
        $orderId = 'DLQ-'.now()->format('His');

        // Empty email makes SendOrderEmailJob::handle() always fail, so after
        // three tries the message must be dead-lettered into order_emails_dlq.
        SendOrderEmailJob::dispatch($this->payload($orderId, ''))
            ->onQueue('order_emails_queue');

        $dlqAfter = $dlqBefore;
        $deadline = microtime(true) + 45.0;
        while (microtime(true) < $deadline) {
            $dlqAfter = $this->queueMessageCount('order_emails_dlq');
            if ($dlqAfter > $dlqBefore) {
                break;
            }
            sleep(1);
        }

        fwrite(STDOUT, "\n[✔] DLQ before={$dlqBefore}, after={$dlqAfter}, order={$orderId}\n");
        $this->assertGreaterThan($dlqBefore, $dlqAfter, 'Failed job never reached order_emails_dlq.');

        Artisan::call('emails:recover-dlq');

        $dlqRecovered = $this->queueMessageCount('order_emails_dlq');
        fwrite(STDOUT, "[✔] recovery: DLQ now={$dlqRecovered}\n");
        $this->assertLessThan($dlqAfter, $dlqRecovered, 'Recovery command did not drain the DLQ.');
    }
}
