<?php

namespace Modules\PPUDS\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Entities\User;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A company's sign-in QR code. See CompanyQrCodeService.
 */
class CompanyQrCode extends Model
{
    use LogsActivity;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('ppuds.table_prefix').'company_qr_codes');
    }

    protected $fillable = [
        'company_id',
        'token_hash',
        'token',
        'expires_at',
        'revoked_at',
        'revoked_by',
        'created_by',
        'last_used_at',
        'use_count',
    ];

    protected $hidden = [
        'token',
        'token_hash',
    ];

    protected $casts = [
        'token' => 'encrypted',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_used_at' => 'datetime',
        'use_count' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    /** Not revoked and not past its expiry. */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->whereNull('revoked_at')
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * Generation and revocation are what matter in the audit trail — never
     * the token, and not the usage counters that change on every sign-in.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['company_id', 'expires_at', 'revoked_at', 'revoked_by', 'created_by'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "This model has been {$eventName} and value ")
            ->useLogName(class_basename($this));
    }
}
