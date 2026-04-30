<?php

namespace Modules\Core\Entities;

use Illuminate\Database\Eloquent\Model;

class AppVersion extends Model
{

    /**
     * The attributes that are mass assignable.
     */

    protected $table = 'app_versions';
    protected $fillable = [
        'id',
        'platform',
        'min_version',
        'latest_version',
        'store_url',
        'maintenance_mode',
        'message'
    ];

    protected $casts = [
        'maintenance_mode'  =>  'boolean'
    ];

    // protected static function newFactory(): AppVersionFactory
    // {
    //     // return AppVersionFactory::new();
    // }
}
