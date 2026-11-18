<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class RecoverFailedEmailsCommand extends Command
{
    protected $signature = 'emails:recover-dlq';
    protected $description = 'Retry dead-lettered order emails automatically at midnight';

    public function handle(): int
    {
        $this->info('Starting midnight DLQ recovery for order_emails_dlq...');
        Log::info('DLQ Recovery started at ' . now());

        Artisan::call('rabbitmq:consume', [
            '--queue' => 'order_emails_dlq',
            '--tries' => 3,
            '--stop-when-empty' => true,
        ], $this->output);

        $this->newLine();

        $this->info('DLQ recovery completed.');
        return self::SUCCESS;
    }
}