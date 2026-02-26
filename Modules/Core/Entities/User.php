<?php

namespace Modules\Core\Entities;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Intervention\Image\Encoders\PngEncoder;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Laravolt\Avatar\Avatar;
use Modules\Customer\Entities\Customer;
use Modules\Delivery\Entities\CustomerAddress;
use Modules\Items\Entities\Order;
use Modules\Marketing\Entities\LoyaltyTier;
use Modules\PPUDS\Entities\StudnetProfile;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Permission\Traits\HasRoles;
use ArPHP\I18N\Arabic;
use Wirechat\Wirechat\Contracts\WirechatUser;
use Wirechat\Wirechat\Panel;
use Wirechat\Wirechat\Traits\InteractsWithWirechat;

/**
 *
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property string|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property int|null $current_team_id
 * @property string|null $profile_photo_path
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string $avatar_url
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \Spatie\MediaLibrary\MediaCollections\Models\Media> $media
 * @property-read int|null $media_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read string $profile_photo_url
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCurrentTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereProfilePhotoPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorConfirmedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorRecoveryCodes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class User extends Authenticatable implements HasMedia, WirechatUser
{
    use HasApiTokens;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use Notifiable;
    use TwoFactorAuthenticatable;
    use InteractsWithMedia;
    use HasRoles;
    use InteractsWithWirechat;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'name',
        'name_en',
        'email',
        'phone',
        'password',
        'branch_id',
        'point_balance',
        'loyalty_tier_id'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
//        'profile_photo_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    public function routeNotificationForFcm()
    {
        return $this->deviceTokens()->pluck('token')->toArray();
    }

    public function canAccessWirechatPanel(Panel $panel): bool
    {
        return $this->hasVerifiedEmail();
    }

    public function canCreateChats(): bool
    {
        return true;
    }

    public function canCreateGroups(): bool
    {
        return true;
    }

    public function getWirechatAvatarUrlAttribute(): string
    {
        return $this->getAvatarUrlAttribute();
    }

    public function getWirechatNameAttribute(): string
    {
        return $this->name;
    }

    public function getAvatarUrlAttribute(): string
    {
        $mediaUrl = $this->getFirstMediaUrl('avatar');
        if ($mediaUrl) {
            return $mediaUrl;
        }

        // --- التعديل هنا ---
        // حوّل البيانات الثنائية إلى رابط بيانات يفهمه المتصفح
        $pngData = $this->generateDefaultAvatar();
        return 'data:image/png;base64,' . base64_encode($pngData);
    }

    protected function getAvatarName(): string
    {
        return $this->name
            ?: ($this->email ? explode('@', $this->email)[0] : 'User_' . $this->id);
    }

    public function registerMediaCollections(): void
    {
        $pngData = $this->generateDefaultAvatar();
        $fallbackDataUrl = 'data:image/png;base64,' . base64_encode($pngData);
        // --- نهاية التعديل ---

        $this->addMediaCollection('avatar')
            ->singleFile()
            ->useFallbackUrl($fallbackDataUrl);

        $this->addMediaCollection('cover_photo')
            ->singleFile()
            ->useFallbackUrl($fallbackDataUrl); // استخدم الرابط المُعدل
    }

    protected function getDefaultRandomColor(): string
    {
        $colors = [
            '#f44336', '#E91E63', '#9C27B0', '#673AB7', '#3F51B5',
            '#2196F3', '#03A9F4', '#00BCD4', '#009688', '#4CAF50',
            '#8BC34A', '#CDDC39', '#FFC107', '#FF9800', '#FF5722'
        ];

        return $colors[array_rand($colors)];
    }

    private function generateInitials(string $name): array
    {
        $name = trim($name);
        $words = explode(' ', $name);
        $initialsArray = [];
        $isArabic = (bool) preg_match('/[\p{Arabic}]/u', $name);

        // Get the first letter of the first word
        if (!empty($words[0])) {
            $initialsArray[] = mb_strtoupper(mb_substr($words[0], 0, 1));
        }

        // Get the first letter of the last word if there's more than one word
        if (count($words) > 1) {
            $initialsArray[] = mb_strtoupper(mb_substr(end($words), 0, 1));
        }

        $initials = implode('', $initialsArray);

        return ['initials' => $initials, 'is_arabic' => $isArabic];
    }

    protected function generateDefaultAvatar(): string
    {
        // 1. Get the full name and generate initials data
        $name = $this->getAvatarName();
        $initialsData = $this->generateInitials($name);
        $initials = $initialsData['initials'];
        $isArabic = $initialsData['is_arabic'];

        $processedText = $initials;

        // 2. Process text ONLY if it is Arabic
        if ($isArabic) {
            $arabic = new Arabic('Glyphs');
            // The initials are passed directly without reversal
            $processedText = $arabic->utf8Glyphs($initials);
        }

        // 3. Create the avatar
        $avatar = new Avatar(config('laravolt.avatar'));
        $image = $avatar->create($processedText)
            ->setDimension(200, 200)
            ->setFontSize(70)
            ->getImageObject();

        return (string) $image->encode(new PngEncoder());
    }

    public function generateAvatar(): void
    {
        $pngData = $this->generateDefaultAvatar(); // هنا سيكون string جاهز

        $this->addMediaFromString($pngData)
            ->usingFileName("user-{$this->id}-avatar.png")
            ->toMediaCollection('avatar', 'media');
    }

    public function getProfileImageUrlAttribute(): string
    {
        $mediaUrl = $this->getFirstMediaUrl('avatar');

        if ($mediaUrl) {
            return $mediaUrl;
        }

        // حوّل البيانات الثنائية إلى رابط بيانات
        $pngData = $this->generateDefaultAvatar();
        return 'data:image/png;base64,' . base64_encode($pngData); $this->getFirstMediaUrl('avatar') ?: $this->generateDefaultAvatar();
    }

    public function getUserDisplayHtmlAttribute(): \Illuminate\Support\HtmlString
    {
        $imageUrl = $this->getProfileImageUrlAttribute();
        $name = e($this->name);
        $email = e($this->email);

        $html = <<<HTML
            <div class="flex items-center gap-4">
                <img
                    src="{$imageUrl}"
                    alt="{$name}"
                    class="w-10 h-10 rounded-full object-cover border border-gray-300 dark:border-gray-600"
                    loading="lazy"
                    width="20"
                    height="20"
                    onerror="this.onerror=null;this.src='{$imageUrl}';"
                >
                <div class="flex flex-col overflow-hidden">
                    <span class="font-medium text-sm text-gray-900 dark:text-gray-100 truncate">{$name}</span>
                    <span class="text-sm text-gray-500 dark:text-gray-400 truncate">{$email}</span>
                </div>
            </div>
            HTML;

        return new \Illuminate\Support\HtmlString($html);
    }

    public function userModules(): HasMany
    {
        return $this->hasMany(UserModule::class)->where('is_active', 1);
    }

    public function getAvailableModules()
    {
        return $this->userModules()->pluck('module_name');
    }

    public function getModulesByPackage($packageSource)
    {
        return $this->userModules()
            ->where('package_source', $packageSource)
            ->get();
    }

    public function getAvailablePackages()
    {
        return $this->userModules()
            ->whereNotNull('package_source')
            ->pluck('package_source')
            ->unique();
    }

    public function activateModule($moduleName, $packageSource = null)
    {
        return $this->userModules()->updateOrCreate([
            'module_name' => $moduleName
        ], [
            'is_active' => true,
            'package_source' => $packageSource
        ]);
    }

    public function clinicProfile(): HasOne
    {
        return $this->hasOne(Customer::class , 'user_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'user_id');
    }

    public function getPointBalance()
    {
        return $this->point_balance;
    }

    public function loyaltyTier(): BelongsTo
    {
        return $this->belongsTo(LoyaltyTier::class , 'loyalty_tier_id');
    }

/*************  ✨ Windsurf Command ⭐  *************/
/**
 * Get the addresses associated with the customer.
 *
 * @return \Illuminate\Database\Eloquent\Relations\HasMany
 */
/*******  3330dc38-a5df-4714-8e1c-41e49d9ae75e  *******/
    public function customerAddresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class, 'user_id');
    }

    public function receivesBroadcastNotificationsOn(): string
    {
        return 'App.Models.User.'.$this->id;
    }

    public function studentProfile(): HasOne
    {
        return $this->hasOne(StudnetProfile::class , 'user_id');
    }


//    public function routeNotificationForFcm()
//    {
//        return $this->getDeviceTokens();
//    }
}
