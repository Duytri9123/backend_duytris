<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserComplaint extends Model
{
    protected $table = 'user_complaints';

    protected $fillable = [
        'reported_user_id',
        'reporter_id',
        'order_id',
        'type',
        'description',
        'status',
        'admin_note',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function reportedUser()
    {
        return $this->belongsTo(User::class, 'reported_user_id');
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
