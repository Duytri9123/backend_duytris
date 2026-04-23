<?php

namespace App\Models;

use App\Enums\ProductStatus;
use Illuminate\Database\Eloquent\Model;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Product extends Model
{
    use HasSlug;
    protected $fillable = [
        'name',
        'slug',
        'description',
        'short_description',
        'status',
        'brand_id',
        'category_id',
        'created_by',
        'updated_by',
        'delete_by',
    ];

    protected $casts = [
        'status' => ProductStatus::class,
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }
    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }
    public function defaultVariant()
    {
        return $this->hasOne(ProductVariant::class)->where('is_default', true);
    }
    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }
    public function thumbnailImage()
    {
        return $this->hasOne(ProductImage::class)->where('is_thumbnail', true);
    }

    public function views()
    {
        return $this->hasMany(ViewProduct::class);
    }

    public function product_reviews()
    {
        return $this->hasMany(ProductReview::class);
    }

    public function averageRating()
    {
        return $this->product_reviews()->avg('rating');
    }

    public function ratingCount()
    {
        return $this->product_reviews()->count();
    }

    public function getFormattedAverageRatingAttribute()
    {
        $avg = round($this->averageRating());
        return str_repeat('★', $avg) . str_repeat('☆', 5 - $avg);
    }


    public function attributeValues()
    {
        return $this->belongsToMany(AttributeValue::class, 'attribute_value_product');
    }


    public function loadAllRelations()
    {
        return $this->load([
            'brand',
            'category.parent',
            'images',
            'thumbnailImage',
            'variants' => function ($query) {
                $query->with(['attributeValues.productAttribute', 'images']);
            },
            'attributeValues.productAttribute',
            'product_reviews.user',
        ]);
    }
}
