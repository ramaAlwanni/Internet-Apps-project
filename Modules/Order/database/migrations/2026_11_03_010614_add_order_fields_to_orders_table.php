<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('orders', 'total_amount')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->decimal('total_amount', 10, 2)
                    ->default(0.00)
                    ->after('user_id');
            });
        }

        if (!Schema::hasColumn('orders', 'status')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('status')
                    ->default('pending')
                    ->index()
                    ->after('total_amount');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'status')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropIndex([
                    'status',
                ]);

                $table->dropColumn('status');
            });
        }

        if (Schema::hasColumn('orders', 'total_amount')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('total_amount');
            });
        }
    }
};
