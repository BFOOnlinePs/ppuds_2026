<?php

namespace Modules\Core\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Modules\Core\Settings\GeneralSettings;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

// use Modules\Core\Database\Factories\SettingsFactory;

class Settings extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $table = 'settings';

    protected $fillable = ['id', 'name', 'group', 'payload'];

    public $timestamps = false;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')->useDisk('media')->singleFile();
    }

    public function handleLogoUpload($logo): void
    {
        if (
            is_array($logo) &&
            !empty($logo) &&
            reset($logo) instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile
        ) {
            $this->addLogo(reset($logo));
        }
    }

    public function addLogo($file)
    {
        if (
            !$file instanceof \Illuminate\Http\UploadedFile &&
            !($file instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile)
        ) {
            return;
        }

        $this->clearMediaCollection('logo');

        $media = $this
            ->addMedia($file)
            ->usingFileName($file->getClientOriginalName())
            ->toMediaCollection('logo', 'media');

        // اختياري: احفظ رابط الشعار في GeneralSettings
        $settings = app(GeneralSettings::class);
        $settings->site_logo_url = $media->getUrl();
        $settings->save();
    }

    public function getLogoUrl(): ?string
    {
        return $this->getFirstMediaUrl('logo');
    }
}
