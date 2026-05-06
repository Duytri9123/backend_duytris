<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'order_number',
        'customer_name',
        'customer_email',
        'customer_phone',
        'shipping_address',
        'notes',
        'subtotal',
        'shipping_fee',
        'discount_id',
        'discount_code',
        'discount_amount',
        'grand_total',
        'payment_method_id',
        'status',
        'payment_status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function discount()
    {
        return $this->belongsTo(Discount::class);
    }

    // Alias để frontend dùng total_amount thay vì grand_total
    public function getTotalAmountAttribute(): float
    {
        return (float) $this->grand_total;
    }

    protected $appends = ['total_amount'];
}
