<?php

namespace Modules\Core\Entities;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use ArPHP\I18N\Arabic;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Intervention\Image\Encoders\PngEncoder;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Laravolt\Avatar\Avatar;
use Modules\Customer\Entities\Customer;
use Modules\Delivery\Entities\CustomerAddress;
use Modules\Items\Entities\Order;
use Modules\Marketing\Entities\LoyaltyTier;
use Modules\PPUDS\Entities\FieldVisit;
use Modules\PPUDS\Entities\Registration;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Entities\StudentProfile;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Permission\Traits\HasRoles;
use Wirechat\Wirechat\Contracts\WirechatUser;
use Wirechat\Wirechat\Panel;
use Wirechat\Wirechat\Traits\InteractsWithWirechat;

/**
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
 *
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
 *
 * @mixin \Eloquent
 */
class User extends Authenticatable implements HasMedia, WirechatUser
{
    use HasApiTokens;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;

    use HasRoles;
    use InteractsWithMedia;
    use InteractsWithWirechat;
    use Notifiable;
    use TwoFactorAuthenticatable;

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
        'loyalty_tier_id',
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

    /** Avatar background colours, indexed deterministically per user. */
    protected const AVATAR_PALETTE = [
        '#f44336', '#E91E63', '#9C27B0', '#673AB7', '#3F51B5',
        '#2196F3', '#03A9F4', '#00BCD4', '#009688', '#4CAF50',
        '#8BC34A', '#CDDC39', '#FFC107', '#FF9800', '#FF5722',
    ];

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

        $pngData = $this->generateDefaultAvatar();

        return 'data:image/png;base64,'.base64_encode($pngData);
    }

    protected function getAvatarName(): string
    {
        return $this->name
            ?: ($this->email ? explode('@', $this->email)[0] : 'User_'.$this->id);
    }

    public function registerMediaCollections(): void
    {
        $pngData = $this->generateDefaultAvatar();
        $fallbackDataUrl = 'data:image/png;base64,'.base64_encode($pngData);

        $this->addMediaCollection('avatar')
            ->singleFile()
            ->useFallbackUrl($fallbackDataUrl);

        $this->addMediaCollection('cover_photo')
            ->singleFile()
            ->useFallbackUrl($fallbackDataUrl);
    }

    protected function getDefaultRandomColor(): string
    {
        return self::AVATAR_PALETTE[$this->avatarPaletteIndex()];
    }

    /**
     * Same user, same colour, every render. Picking at random made a row's
     * avatar flicker to a new colour on each Livewire refresh.
     */
    protected function avatarPaletteIndex(): int
    {
        $seed = $this->getKey() ?: crc32((string) ($this->name ?? $this->email ?? ''));

        return ((int) $seed) % count(self::AVATAR_PALETTE);
    }

    private function generateInitials(string $name): array
    {
        $name = trim($name);
        $isArabic = (bool) preg_match('/[\p{Arabic}]/u', $name);
        $initials = mb_strtoupper(mb_substr($name, 0, 1));

        return ['initials' => $initials, 'is_arabic' => $isArabic];
    }

    protected ?string $generatedDefaultAvatar = null;

    protected function generateDefaultAvatar(): string
    {
        if ($this->generatedDefaultAvatar !== null) {
            return $this->generatedDefaultAvatar;
        }

        return $this->generatedDefaultAvatar = $this->buildDefaultAvatar();
    }

    protected function buildDefaultAvatar(): string
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

        return (string) $image->encode(new PngEncoder);
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

        return 'data:image/png;base64,'.base64_encode($pngData);
        $this->getFirstMediaUrl('avatar') ?: $this->generateDefaultAvatar();
    }

    public function getUserDisplayHtmlAttribute(): \Illuminate\Support\HtmlString
    {
        return $this->userDisplayHtml();
    }

    /**
     * Avatar + name + secondary line, as used for every person shown in a
     * table cell. $subtitle defaults to the email; pass something else (a
     * student number, a role) when the table needs a different second line.
     */
    public function userDisplayHtml(?string $subtitle = null, bool $muted = false): \Illuminate\Support\HtmlString
    {
        $name = e($this->name ?: __('Unknown'));
        $subtitle = $subtitle ?? $this->email;
        $nameColor = $muted
            ? 'text-gray-700 dark:text-gray-300'
            : 'text-gray-900 dark:text-gray-100';

        $avatar = $this->avatarMarkup();

        $subtitleMarkup = filled($subtitle)
            ? '<span class="block max-w-full truncate text-xs text-gray-500 dark:text-gray-400" dir="auto">'.e($subtitle).'</span>'
            : '';

        $html = <<<HTML
            <div class="flex min-w-0 items-center gap-3">
                {$avatar}
                <span class="flex min-w-0 flex-col leading-tight">
                    <span class="block max-w-full truncate text-sm font-semibold {$nameColor}">{$name}</span>
                    {$subtitleMarkup}
                </span>
            </div>
            HTML;

        return new \Illuminate\Support\HtmlString($html);
    }

    /**
     * An uploaded avatar renders as an <img>; everyone else gets a coloured
     * initial drawn in CSS. The CSS branch matters: the PNG fallback runs the
     * image encoder once per user, which is far too slow for a table page.
     */
    protected function avatarMarkup(): string
    {
        $name = e($this->name ?: __('Unknown'));
        $ring = 'ring-1 ring-gray-200';
        $media = $this->getFirstMedia('avatar');

        if ($media) {
            return '<img src="'.e($media->getUrl()).'" alt="'.$name.'" loading="lazy" width="40" height="40"'
                .' class="h-10 w-10 shrink-0 rounded-full object-cover shadow-sm '.$ring.'">';
        }

        $initials = e($this->generateInitials($this->getAvatarName())['initials']);
        $color = $this->getDefaultRandomColor();

        return '<span aria-hidden="true" style="background-color: '.e($color).'"'
            .' class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-bold uppercase text-white shadow-sm '.$ring.'">'
            .$initials.'</span>';
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
            'module_name' => $moduleName,
        ], [
            'is_active' => true,
            'package_source' => $packageSource,
        ]);
    }

    public function clinicProfile(): HasOne
    {
        return $this->hasOne(Customer::class, 'user_id');
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
        return $this->belongsTo(LoyaltyTier::class, 'loyalty_tier_id');
    }

    /**
     * Get the addresses associated with the customer.
     */
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
        return $this->hasOne(StudentProfile::class, 'user_id');
    }

    public function studentCompanies(): HasMany
    {
        return $this->hasMany(StudentCompany::class, 'student_id');
    }

    public function supervisedRegistrations(): HasMany
    {
        return $this->hasMany(Registration::class, 'supervisor_id');
    }

    public function fieldVisits(): HasMany
    {
        return $this->hasMany(FieldVisit::class, 'supervisor_id');
    }

    //    public function routeNotificationForFcm()
    //    {
    //        return $this->getDeviceTokens();
    //    }
}
