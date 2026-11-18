<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Wire\AMQPTable;

Schedule::command('emails:recover-dlq')->dailyAt('00:00');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Declare the RabbitMQ topology used by the async email queue.
 *
 * config/queue.php references orders_dlx and parking_lot_exchange but Laravel
 * does not declare exchanges by itself. Without this command, failed jobs are
 * silently dropped instead of landing in order_emails_dlq.
 */
Artisan::command('rabbitmq:setup', function () {
    $host = config('queue.connections.rabbitmq.hosts.0');

    $connection = new AMQPStreamConnection(
        $host['host'],
        (int) $host['port'],
        $host['user'],
        $host['password'],
        $host['vhost']
    );

    $channel = $connection->channel();

    $channel->exchange_declare('orders_dlx', 'direct', false, true, false);
    $channel->exchange_declare('parking_lot_exchange', 'direct', false, true, false);

    $channel->queue_declare('order_emails_dlq', false, true, false, false, false,
        new AMQPTable([
            'x-dead-letter-exchange' => 'parking_lot_exchange',
            'x-dead-letter-routing-key' => 'parking.key',
        ]));
    $channel->queue_declare('order_emails_parking_lot', false, true, false, false);

    $channel->queue_declare('order_emails_queue', false, true, false, false, false,
        new AMQPTable([
            'x-dead-letter-exchange' => 'orders_dlx',
            'x-dead-letter-routing-key' => 'order_emails_dlq',
        ]));

    $channel->queue_bind('order_emails_dlq', 'orders_dlx', 'order_emails_dlq');
    $channel->queue_bind('order_emails_parking_lot', 'parking_lot_exchange', 'parking.key');

    $channel->close();
    $connection->close();

    $this->info('RabbitMQ topology declared: order_emails_queue -> orders_dlx -> order_emails_dlq -> parking_lot_exchange -> order_emails_parking_lot.');
})->purpose('Declare RabbitMQ exchanges, queues and dead-letter bindings');
