<?php

namespace Modules\PPUDS\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Entities\User;
use Modules\PPUDS\Enums\InternalLoginMode;
use Modules\PPUDS\Enums\OtpChannel;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * One person's own sign-in method and code channel. Null fields follow the
 * role rules, then the general setting — see LoginOtpService::modeForUser().
 */
class UserLoginPreference extends Model
{
    use LogsActivity;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('ppuds.table_prefix').'user_login_preferences');
    }

    protected $fillable = [
        'user_id',
        'login_mode',
        'otp_channel',
        'updated_by',
    ];

    protected $casts = [
        'login_mode' => InternalLoginMode::class,
        'otp_channel' => OtpChannel::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Who signs in how is a security setting: every change goes to the audit trail. */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['user_id', 'login_mode', 'otp_channel', 'updated_by'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "This model has been {$eventName} and value ")
            ->useLogName(class_basename($this));
    }
}
