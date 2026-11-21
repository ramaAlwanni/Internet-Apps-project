<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_email_outbox', function (Blueprint $table) {
            $table->id();

            $table
                ->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->json('payload');

            $table
                ->string('status')
                ->default('pending')
                ->index();

            $table
                ->unsignedInteger('attempts')
                ->default(0);

            $table
                ->text('last_error')
                ->nullable();

            $table
                ->timestamp('available_at')
                ->nullable()
                ->index();

            $table
                ->timestamp('published_at')
                ->nullable();

            $table->timestamps();

            $table->unique('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_email_outbox');
    }
};
