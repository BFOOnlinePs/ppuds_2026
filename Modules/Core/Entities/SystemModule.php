<?php

namespace Modules\Core\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Core\Database\Factories\SystemModuleFactory;

class SystemModule extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['name', 'display_name', 'icon', 'description', 'is_active'];

    public function packages()
    {
        return $this->belongsToMany(Package::class, 'package_modules');
    }

    // protected static function newFactory(): SystemModuleFactory
    // {
    //     // return SystemModuleFactory::new();
    // }
}
