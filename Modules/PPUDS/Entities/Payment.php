<?php

namespace Modules\PPUDS\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Modules\Core\Entities\Currency;
use Modules\Core\Entities\User;
use Modules\Core\Enums\ImageQuality;
use Modules\Core\Enums\ImageSize;
use Modules\Core\Services\ImageService;
use Modules\PPUDS\Enums\PaymentStatus;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Payment extends Model implements HasMedia
{
    use LogsActivity;
    use SoftDeletes;
    use InteractsWithMedia;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        // تعيين اسم الجدول بناءً على الكونفيج
        $this->setTable(config('ppuds.table_prefix') . 'payments');
    }

    protected $fillable = [
        'student_company_id',
        'reference_id',
        'payment_value',
        'student_role',
        'status',
        'currency_id',
        'company_notes',
        'supervisor_id',
        'created_by',
    ];

    protected $casts = [
        'payment_value' => 'decimal:2',
         'status' => PaymentStatus::class, // قم بتفعيل هذا السطر إذا أنشأت Enum خاص بالحالة
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->getFillable())
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "Payment has been {$eventName}")
            ->useLogName('Payment');
    }

    public function studentCompany(): BelongsTo
    {
        return $this->belongsTo(StudentCompany::class, 'student_company_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function registration()
    {
        $this->studentCompany?->registration;
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }


    public function registerMediaConversions(?Media $media = null): void
    {
        $this
            ->addMediaConversion('receipt')
            ->fit(Fit::Contain, 300, 300)
            ->nonQueued();
    }

    public function addImage($file)
    {
        if (is_array($file)) {
            $file = reset($file);
        }

        if (
            !$file instanceof \Illuminate\Http\UploadedFile &&
            !($file instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile)
        ) {
            return null;
        }

        $this->clearMediaCollection('receipt');

        try {
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $fileName = time() . '_' . Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '.' . $extension;

            $media = $this
                ->addMedia($file)
                ->usingFileName($fileName)
                ->toMediaCollection('receipt', 'payments');

            $size = ImageSize::MEDIUM;

            ImageService::optimize($media->getPath() , ImageQuality::HIGH->value);
            ImageService::resize($media->getPath() , $size->width(), $size->height());

            return $media;
        } catch (\Exception $e) {
            \Log::error('Error uploading receipt file: ' . $e->getMessage());
            return null;
        }
    }

    public function getImageAttribute()
    {
        return $this->getFirstMediaUrl('receipt');
    }
}
