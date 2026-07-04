<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class OrderTimEvent extends Pivot
{
    protected $table = 'order_tim_event';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['order_id', 'user_id'];
}
