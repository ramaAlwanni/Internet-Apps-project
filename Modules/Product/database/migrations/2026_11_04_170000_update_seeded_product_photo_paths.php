<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $photoPaths = [
        'photos/phone.jpg' => 'products/phone.jpg',
        'photos/headphones.jpg' => 'products/headphones.jpg',
        'photos/keyboard.jpg' => 'products/keyboard.jpg',
    ];

    public function up(): void
    {
        foreach ($this->photoPaths as $oldPath => $newPath) {
            DB::table('products')
                ->where('photo', $oldPath)
                ->update(['photo' => $newPath]);
        }
    }

    public function down(): void
    {
        foreach ($this->photoPaths as $oldPath => $newPath) {
            DB::table('products')
                ->where('photo', $newPath)
                ->update(['photo' => $oldPath]);
        }
    }
};
