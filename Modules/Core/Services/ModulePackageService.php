<?php

namespace Modules\Core\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use App\Models\User;

class ModulePackageService
{
    protected $packages;

    public function __construct()
    {
        $this->packages = collect();
    }

    /**
     * جلب الـ features المتاحة
     */
//    public function getAvailableFeatures(string $search = null): Collection
//    {
//        $cacheKey = 'available_features' . ($search ? "_search_{$search}" : '');
//
//        return Cache::remember($cacheKey, 3600, function () use ($search) {
//            $allFeatures = config('core.features.available_features', []);
//            $enabledFeatures = config('core.features.enabled_features', []);
//
//            // فلترة الـ features المفعلة فقط
//            $features = collect($allFeatures)->filter(function ($feature, $key) use ($enabledFeatures) {
//                return $enabledFeatures[$key] ?? false;
//            });
//
//            // فلترة حسب البحث
//            if ($search) {
//                $features = $features->filter(function ($feature) use ($search) {
//                    return str_contains(strtolower($feature['name']), strtolower($search)) ||
//                        str_contains(strtolower($feature['description']), strtolower($search));
//                });
//            }
//
//            return $features;
//        });
//    }

    /**
     * جلب feature معين
     */
    public function getFeature(string $featureKey): ?array
    {
        $feature = config("core.features.available_features.{$featureKey}");

        if (!$feature || !$this->isFeatureEnabled($featureKey)) {
            return null;
        }

        return array_merge($feature, ['key' => $featureKey]);
    }

    /**
     * التحقق من تفعيل الـ feature
     */
    public function isFeatureEnabled(string $featureKey): bool
    {
        return config("core.features.enabled_features.{$featureKey}", false);
    }

    /**
     * تعيين feature للمستخدم
     */
    public function assignFeatureToUser(User $user, string $featureKey): bool
    {
        $feature = $this->getFeature($featureKey);

        if (!$feature) {
            return false;
        }

        // حذف الـ modules الحالية
        $user->userModules()->delete();

        // إضافة الـ modules الجديدة
        foreach ($feature['modules'] as $moduleName) {
            $user->userModules()->create([
                'module_name' => strtolower($moduleName),
                'package_source' => $featureKey,
                'is_active' => true
            ]);
        }

        return true;
    }

    /**
     * جلب الـ feature الحالي للمستخدم
     */
    public function getUserCurrentFeature(User $user): ?string
    {
        $userModule = $user->userModules()->first();
        return $userModule ? $userModule->package_source : null;
    }

    /**
     * جلب اسم الـ feature
     */
    public function getName(string $featureKey): ?string
    {
        $feature = config("core.features.available_features.{$featureKey}");
        return $feature['name'] ?? null;
    }

    /**
     * جلب الـ features المتاحة (للجميع)
     */
    public function getAvailableFeatures(string $search = null): Collection
    {
        $cacheKey = 'available_features' . ($search ? "_search_{$search}" : '');

        return Cache::remember($cacheKey, 3600, function () use ($search) {
            $allFeatures = config('core.features.available_features', []);
            $enabledFeatures = config('core.features.enabled_features', []);

            // فلترة الـ features المفعلة فقط
            $features = collect($allFeatures)->filter(function ($feature, $key) use ($enabledFeatures) {
                return $enabledFeatures[$key] ?? false;
            });

            // فلترة حسب البحث
            if ($search) {
                $features = $features->filter(function ($feature) use ($search) {
                    return str_contains(strtolower($feature['name']), strtolower($search)) ||
                        str_contains(strtolower($feature['description']), strtolower($search));
                });
            }

            return $features;
        });
    }
}
