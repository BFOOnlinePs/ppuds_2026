<?php

namespace Modules\Core\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// use Modules\Core\Database\Factories\DeviceTokenFactory;

class DeviceToken extends Model
{

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'token',
        'device_name',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
