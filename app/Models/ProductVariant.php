<?php

namespace App\Models;

use App\Http\Resources\ProductImageResource;
use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id',
        'sku',
        'selling_price',
        'original_price',
        'quantity',
        'weight',
        'dimensions',
        'is_default',
    ];

    protected $casts = [
        'selling_price'  => 'decimal:2',
        'original_price' => 'decimal:2',
        'weight'         => 'decimal:2',
        'quantity'       => 'integer',
        'is_default'     => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function attributeValues()
    {
        return $this->belongsToMany(AttributeValue::class, 'attribute_product_variants');
    }
    public function attribute()
    {
        return $this->belongsTo(ProductAttribute::class, 'attribute_id');
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class, 'product_variant_id');
    }
}
