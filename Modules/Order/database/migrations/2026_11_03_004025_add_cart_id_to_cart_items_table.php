<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('cart_items', 'cart_id')) {
            Schema::table('cart_items', function (Blueprint $table) {
                $table->foreignId('cart_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('carts')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cart_items', 'cart_id')) {
            Schema::table('cart_items', function (Blueprint $table) {
                $table->dropForeign(['cart_id']);
                $table->dropColumn('cart_id');
            });
        }
    }
};
