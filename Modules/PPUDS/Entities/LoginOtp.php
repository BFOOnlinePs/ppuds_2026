<?php

namespace Modules\PPUDS\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Entities\User;
use Modules\PPUDS\Enums\OtpChannel;
use Modules\PPUDS\Enums\OtpPurpose;

/**
 * A sign-in code that was sent. Only its keyed hash is kept — see
 * LoginOtpService::hash().
 */
class LoginOtp extends Model
{
    use Prunable;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('ppuds.table_prefix').'login_otps');
    }

    protected $fillable = [
        'user_id',
        'channel',
        'purpose',
        'code_hash',
        'company_id',
        'attempts',
        'expires_at',
        'consumed_at',
        'invalidated_at',
        'ip',
    ];

    protected $hidden = [
        'code_hash',
    ];

    protected $casts = [
        'channel' => OtpChannel::class,
        'purpose' => OtpPurpose::class,
        'attempts' => 'integer',
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
        'invalidated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** Not used, not superseded, not burned and not expired. */
    public function scopeUsable(Builder $query): Builder
    {
        return $query
            ->whereNull('consumed_at')
            ->whereNull('invalidated_at')
            ->where('expires_at', '>', now());
    }

    /** Codes live minutes; a day later the row is no longer worth keeping. */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDay());
    }
}
