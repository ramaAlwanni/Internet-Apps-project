<?php

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Product\Database\Factories\ProductFactory;

use Illuminate\Support\Facades\Storage;


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

    protected $hidden = ['photo'];

    protected $appends = ['photo_url'];

    public function getPhotoUrlAttribute(): ?string
{
    return $this->photo
        ? Storage::disk('public')->url($this->photo)
        : null;
}


    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }
}
