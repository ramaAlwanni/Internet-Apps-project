<?php

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'photo',
        'stock',
        'category_id',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    // مسار الملف الداخلي ما منبعتو للعميل، منبعت photo_url بدالو
    protected $hidden = ['photo'];

    protected $appends = ['photo_url'];

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo ? asset('storage/' . $this->photo) : null;
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}

// namespace Modules\Product\Models;

// use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\Factories\HasFactory;
// //use Modules\Product\Models\Category;

// class Product extends Model
// {
//     use HasFactory;

//     protected $fillable = [
//         'name',
//         'description',
//         'price',
//         'photo',
//         'stock',
//         'category_id',
//     ];

//     public function category()
//     {
//         return $this->belongsTo(Category::class);
//     }
// }
