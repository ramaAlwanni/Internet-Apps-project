<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class ReplayParkingLotEmailsCommand extends Command
{
    protected $signature = 'emails:replay-parking-lot';
    protected $description = 'Move fixed order emails from Parking Lot back to the main queue for processing';

    public function handle(): int
    {
        $this->info('Starting to replay messages from Parking Lot to Main Queue...');
        Log::info('Parking Lot replay started at ' . now());

        Artisan::call('rabbitmq:consume', [
            '--queue' => 'order_emails_parking_lot',
            '--tries' => 3,
            '--stop-when-empty' => true,
        ], $this->output);

        $this->newLine();
        $this->info('Replay completed successfully. All fixed orders are processed or returned to main queue.');
        
        return self::SUCCESS;
    }
}