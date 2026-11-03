<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            if (!Schema::hasColumn('cart_items', 'product_id')) {
                $table->foreignId('product_id')
                    ->nullable()
                    ->after('cart_id')
                    ->constrained('products')
                    ->restrictOnDelete();
            }

            if (!Schema::hasColumn('cart_items', 'quantity')) {
                $table->unsignedInteger('quantity')
                    ->default(1)
                    ->after('product_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            if (Schema::hasColumn('cart_items', 'product_id')) {
                $table->dropForeign([
                    'product_id',
                ]);

                $table->dropColumn('product_id');
            }

            if (Schema::hasColumn('cart_items', 'quantity')) {
                $table->dropColumn('quantity');
            }
        });
    }
};
