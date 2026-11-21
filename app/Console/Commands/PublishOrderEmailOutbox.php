<?php

namespace App\Console\Commands;

use App\Jobs\SendOrderEmailJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Order\Models\OrderEmailOutbox;
use Throwable;

class PublishOrderEmailOutbox extends Command
{
    protected $signature = 'outbox:publish-order-emails';

    protected $description = 'Publish pending order email messages to RabbitMQ';

    public function handle(): int
    {
        DB::transaction(function () {
            // lockForUpdate + skipLocked: لو شغّلتِ أكثر من ناشر لا يلمسان الصف نفسه.
            $rows = OrderEmailOutbox::query()
                ->where('status', 'pending')
                ->where(function ($query) {
                    $query->whereNull('available_at')
                        ->orWhere('available_at', '<=', now());
                })
                ->orderBy('id')
                ->limit(20)
                ->lockForUpdate()
                //->skipLocked()
                ->get();

            foreach ($rows as $row) {
                try {
                    SendOrderEmailJob::dispatch($row->payload)
                        ->onQueue('order_emails_queue');

                    $row->update([
                        'status' => 'published',
                        'attempts' => $row->attempts + 1,
                        'last_error' => null,
                        'published_at' => now(),
                    ]);

                    $this->info("Published email for Order #{$row->order_id}");
                } catch (Throwable $exception) {
                    // تأخير تصاعدي بحد أقصى 60 ثانية (5، 10، 20، 40، 60...)
                    $delay = min(60, 5 * (2 ** min($row->attempts, 4)));

                    $row->update([
                        'attempts' => $row->attempts + 1,
                        'last_error' => mb_substr($exception->getMessage(), 0, 500),
                        'available_at' => now()->addSeconds($delay),
                    ]);

                    Log::warning('Could not publish order email.', [
                        'outbox_id' => $row->id,
                        'order_id' => $row->order_id,
                        'error' => $exception->getMessage(),
                    ]);

                    $this->error("RabbitMQ unavailable for Order #{$row->order_id}");

                    // RabbitMQ غائب: لا فائدة من تجربة الباقي في هذه الدورة
                    // (وإلا ننتظر مهلة اتصال لكل صف).
                    break;
                }
            }
        });

        return self::SUCCESS;
    }
}