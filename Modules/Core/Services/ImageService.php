<?php

namespace Modules\Core\Services;

use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageManagerInterface;

class ImageService
{
    public function handle()
    {
    }

    protected static function manager(): ImageManagerInterface
    {
        return new ImageManager(new Driver());
    }

    public static function optimize(string $path, int $quality = 75) : void
    {
        $manager = self::manager();
        $image = $manager->read($path);
        $image->encodeByExtension(pathinfo($path, PATHINFO_EXTENSION),  $quality);
        $image->save($path);
    }

    public static function resize(string $path, int $width = 800, int $height = 800) : void
    {
        $manager = self::manager();
        $image = $manager->read($path);
        $image->scale(width: $width, height: $height);
        $image->save($path);
    }
}
