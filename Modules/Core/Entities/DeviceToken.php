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

    protected static function booted()
    {
        // A token identifies one device, so it belongs only to whoever signed
        // in on it last. Otherwise the previous user's pushes keep arriving on
        // a phone someone else is now using.
        static::saved(function (DeviceToken $deviceToken) {
            static::query()
                ->where('token', $deviceToken->token)
                ->where('user_id', '!=', $deviceToken->user_id)
                ->delete();
        });
    }
}
