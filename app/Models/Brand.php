<?php

namespace App\Models;

// Import các lớp cần thiết
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Brand extends Model
{
    // Thêm HasFactory
    use HasFactory, HasSlug;

    protected $fillable = [
        'name',
        'slug',
        'img_url'
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    /**
     * Mối quan hệ: Một thương hiệu có nhiều sản phẩm.
     */
    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
