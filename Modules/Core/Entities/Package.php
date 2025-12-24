<?php

namespace Modules\Core\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Core\Database\Factories\PackageFactory;

class Package extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['name', 'display_name', 'description', 'icon', 'price', 'is_active'];

    public function systemModules()
    {
        return $this->belongsToMany(SystemModule::class, 'package_modules')
            ->withPivot('settings')
            ->withTimestamps();
    }

    // protected static function newFactory(): PackageFactory
    // {
    //     // return PackageFactory::new();
    // }
}
