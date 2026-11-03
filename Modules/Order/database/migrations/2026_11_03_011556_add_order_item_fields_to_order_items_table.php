<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('order_items', 'product_id')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->foreignId('product_id')
                    ->nullable()
                    ->constrained('products')
                    ->restrictOnDelete();
            });
        }

        if (!Schema::hasColumn('order_items', 'product_name')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->string('product_name')
                    ->nullable();
            });
        }

        if (!Schema::hasColumn('order_items', 'unit_price')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->decimal('unit_price', 10, 2)
                    ->default(0.00);
            });
        }

        if (!Schema::hasColumn('order_items', 'quantity')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->unsignedInteger('quantity')
                    ->default(1);
            });
        }

        if (!Schema::hasColumn('order_items', 'subtotal')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->decimal('subtotal', 10, 2)
                    ->default(0.00);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('order_items', 'product_id')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropForeign(['product_id']);
                $table->dropColumn('product_id');
            });
        }

        if (Schema::hasColumn('order_items', 'product_name')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropColumn('product_name');
            });
        }

        if (Schema::hasColumn('order_items', 'unit_price')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropColumn('unit_price');
            });
        }

        if (Schema::hasColumn('order_items', 'quantity')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropColumn('quantity');
            });
        }

        if (Schema::hasColumn('order_items', 'subtotal')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropColumn('subtotal');
            });
        }
    }
};
