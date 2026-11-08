<?php

namespace Modules\Core\Actions\AiAssistant;

use Illuminate\Support\Collection;
use Modules\Branch\Entities\Branch;
use Modules\GeoLocation\Entities\City;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Enums\CompanyStatus;

class FindNearestCompaniesForStudent
{
    private const EARTH_RADIUS_KM = 6371;

    /**
     * يرتّب الشركات النشطة حسب أقرب فرع لمدينة السكن.
     * إحداثيات الفرع أولًا، وإن لم تتوفر تُستخدم إحداثيات مدينة الفرع.
     */
    public function handle(City $origin, int $limit = 5): Collection
    {
        $originLat = $this->coordinate($origin->latitude);
        $originLng = $this->coordinate($origin->longitude);

        return Company::query()
            ->with(['category', 'branches.city', 'branches.departments'])
            ->withCount('currentStudentCompanies')
            ->where('status', CompanyStatus::ACTIVE->value)
            ->whereHas('branches')
            ->get()
            ->map(function (Company $company) use ($origin, $originLat, $originLng) {
                $nearest = $company->branches
                    ->map(fn (Branch $branch) => [
                        'branch' => $branch,
                        'distance_km' => $this->branchDistance($branch, $origin, $originLat, $originLng),
                    ])
                    ->filter(fn (array $item) => $item['distance_km'] !== null)
                    ->sortBy('distance_km')
                    ->first();

                if (! $nearest) {
                    return null;
                }

                /** @var Branch $branch */
                $branch = $nearest['branch'];

                return [
                    'company_id' => $company->id,
                    'company_name' => $company->name,
                    'category' => $company->category?->name,
                    'branch_id' => $branch->id,
                    'branch_name' => $branch->name,
                    'branch_city' => $branch->city?->name,
                    'distance_km' => round($nearest['distance_km'], 1),
                    'current_students_count' => (int) ($company->current_student_companies_count ?? 0),
                    'departments' => $branch->departments
                        ->map(fn ($department) => ['id' => $department->id, 'name' => $department->name])
                        ->values()
                        ->all(),
                ];
            })
            ->filter()
            ->sortBy([
                ['distance_km', 'asc'],
                ['current_students_count', 'asc'],
            ])
            ->take($limit)
            ->values();
    }

    private function branchDistance(Branch $branch, City $origin, ?float $originLat, ?float $originLng): ?float
    {
        $lat = $this->coordinate($branch->latitude) ?? $this->coordinate($branch->city?->latitude);
        $lng = $this->coordinate($branch->longitude) ?? $this->coordinate($branch->city?->longitude);

        if ($originLat === null || $originLng === null || $lat === null || $lng === null) {
            // بدون إحداثيات: فرع في نفس المدينة يُعتبر الأقرب.
            return (int) $branch->city_id === (int) $origin->id ? 0.0 : null;
        }

        return $this->haversine($originLat, $originLng, $lat, $lng);
    }

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function coordinate(mixed $value): ?float
    {
        return filled($value) && (float) $value !== 0.0 ? (float) $value : null;
    }
}
