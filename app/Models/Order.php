<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'hold_id',
        'product_id',
        'qty',
        'total_price',
        'status',
    ];

    /**
     * @return BelongsTo
     */
    public function hold(): BelongsTo
    {
        return $this->belongsTo(Hold::class, 'hold_id'); 
    }

    /**
     * @return BelongsTo
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id'); 
    }
}