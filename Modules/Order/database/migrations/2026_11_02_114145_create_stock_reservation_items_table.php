<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_reservation_items', function (Blueprint $table) {
            $table->id();

            $table->uuid('reservation_id');

            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();

            $table->unsignedInteger('quantity');

            $table->timestamps();

            $table->foreign('reservation_id')
                ->references('id')
                ->on('stock_reservations')
                ->cascadeOnDelete();

            $table->unique([
                'reservation_id',
                'product_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reservation_items');
    }
};
