<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttributeValue extends Model
{
    protected $fillable = [
        'product_attribute_id',
        'value',
        'code',
    ];

     public function productAttribute()
    {
        return $this->belongsTo(ProductAttribute::class);
    }
    public function products()
    {
        return $this->belongsToMany(Product::class, 'attribute_value_product');
    }

     public function productVariants()
    {
        return $this->belongsToMany(ProductVariant::class, 'attribute_product_variant');
    }
}
