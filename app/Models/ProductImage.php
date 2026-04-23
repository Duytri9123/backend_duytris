<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    protected $fillable = [
        'product_id',
        'product_variant_id',
        'image',
        'media_type',
        'alt_text',
        'display_order',
        'is_thumbnail',
    ];

    protected $casts = [
        'display_order' => 'integer',
        'is_thumbnail' => 'boolean',
    ];
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
