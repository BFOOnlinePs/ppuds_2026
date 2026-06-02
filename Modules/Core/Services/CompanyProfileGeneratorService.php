<?php

namespace Modules\Core\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Enums\Lab;
use Modules\Branch\Enums\WeekDay;
use Modules\Core\Ai\CompanyProfileGeneratorAgent;
use Modules\GeoLocation\Entities\City;
use Modules\GeoLocation\Entities\Country;
use Modules\PPUDS\Entities\CompanyCategory;
use Modules\PPUDS\Enums\CompanyStatus;
use Throwable;

class CompanyProfileGeneratorService
{
    public function generate(string $brief, bool $includeDepartments = false): array
    {
        $brief = trim($brief);
        $context = $this->context();

        if (! $this->aiIsConfigured()) {
            return [
                'message' => __('AI provider is not configured, so local suggestions were applied.'),
                'profile' => $this->fallbackProfile($brief, $context, $includeDepartments),
                'used_ai' => false,
            ];
        }

        try {
            $response = $this->aiProfile($brief, $context, $includeDepartments);
            $profile = $this->normalizeProfile($response['company'] ?? [], $context, $includeDepartments);

            if ($profile !== []) {
                return [
                    'message' => $response['summary'] ?? __('Company profile generated successfully.'),
                    'profile' => $profile,
                    'used_ai' => true,
                ];
            }
        } catch (Throwable $exception) {
            Log::warning('Company profile AI generation failed.', [
                'exception' => $exception->getMessage(),
            ]);
        }

        return [
            'message' => __('AI generation failed, so local suggestions were applied.'),
            'profile' => $this->fallbackProfile($brief, $context, $includeDepartments),
            'used_ai' => false,
        ];
    }

    private function aiProfile(string $brief, array $context, bool $includeDepartments): array
    {
        $response = CompanyProfileGeneratorAgent::make()->prompt(
            $this->prompt($brief, $context, $includeDepartments),
            provider: $this->configuredProvider(),
            model: $this->configuredModel(),
            timeout: $this->configuredTimeout(),
        );

        return method_exists($response, 'toArray') ? $response->toArray() : [];
    }

    private function prompt(string $brief, array $context, bool $includeDepartments): string
    {
        return json_encode([
            'task' => 'حوّل وصف الشركة إلى بيانات نموذج إضافة الشركة حسب schema فقط.',
            'brief' => $brief,
            'include_departments' => $includeDepartments,
            'available_categories' => $context['categories'],
            'available_countries' => $context['countries'],
            'available_cities' => $context['cities'],
            'working_days' => $context['working_days'],
            'defaults' => $context['defaults'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function context(): array
    {
        $countries = Country::query()
            ->orderBy('id')
            ->limit(50)
            ->get()
            ->map(fn (Country $country): array => [
                'id' => $country->id,
                'name' => $country->name,
            ])
            ->values();

        $defaultCountry = $this->defaultCountry($countries);
        $cities = City::query()
            ->with('governorate')
            ->when(
                $defaultCountry,
                fn ($query) => $query->whereHas('governorate', fn ($governorateQuery) => $governorateQuery->where('country_id', $defaultCountry['id']))
            )
            ->orderBy('id')
            ->limit(80)
            ->get()
            ->map(fn (City $city): array => [
                'id' => $city->id,
                'name' => $city->name,
                'country_id' => $city->governorate?->country_id,
                'latitude' => $city->latitude,
                'longitude' => $city->longitude,
            ])
            ->values();

        $defaultCity = $this->defaultCity($cities);

        return [
            'categories' => CompanyCategory::query()
                ->orderBy('id')
                ->get()
                ->map(fn (CompanyCategory $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                ])
                ->values()
                ->all(),
            'countries' => $countries->all(),
            'cities' => $cities->all(),
            'working_days' => collect(WeekDay::cases())
                ->map(fn (WeekDay $day): array => [
                    'value' => $day->value,
                    'label' => $day->getLabel(),
                ])
                ->values()
                ->all(),
            'defaults' => [
                'country_id' => $defaultCountry['id'] ?? null,
                'city_id' => $defaultCity['id'] ?? null,
                'latitude' => $defaultCity['latitude'] ?? 31.5326,
                'longitude' => $defaultCity['longitude'] ?? 35.0998,
            ],
        ];
    }

    private function fallbackProfile(string $brief, array $context, bool $includeDepartments): array
    {
        $website = $this->extractWebsite($brief);
        $name = $this->guessCompanyName($brief, $website);
        $categoryId = $this->bestCategoryId($brief, collect($context['categories']));
        $city = $this->bestCity($brief, collect($context['cities']), $context['defaults']);
        $countryId = $city['country_id'] ?? $context['defaults']['country_id'];

        return [
            'name' => $name,
            'website' => $website,
            'description' => $this->fallbackDescription($brief, $name),
            'company_category_id' => $categoryId,
            'status' => CompanyStatus::ACTIVE->value,
            'branches' => [
                [
                    'name' => __('Main Branch'),
                    'email' => $this->extractEmail($brief),
                    'phone' => $this->extractPhone($brief),
                    'country_id' => $countryId,
                    'city_id' => $city['id'] ?? $context['defaults']['city_id'],
                    'latitude' => $city['latitude'] ?? $context['defaults']['latitude'],
                    'longitude' => $city['longitude'] ?? $context['defaults']['longitude'],
                    'location' => [
                        'lat' => $city['latitude'] ?? $context['defaults']['latitude'],
                        'lng' => $city['longitude'] ?? $context['defaults']['longitude'],
                    ],
                    'working_hours' => $this->defaultWorkingHours(),
                    'departments' => $includeDepartments ? $this->fallbackDepartments($brief) : [],
                ],
            ],
        ];
    }

    private function normalizeProfile(array $profile, array $context, bool $includeDepartments): array
    {
        $branches = collect($profile['branches'] ?? [])
            ->map(fn (array $branch): array => $this->normalizeBranch($branch, $context, $includeDepartments))
            ->filter(fn (array $branch): bool => filled($branch['name'] ?? null))
            ->values();

        if ($branches->isEmpty()) {
            $branches = collect($this->fallbackProfile((string) ($profile['name'] ?? ''), $context, $includeDepartments)['branches']);
        }

        return [
            'name' => Str::limit(trim((string) ($profile['name'] ?? '')), 160, ''),
            'website' => $this->validUrl($profile['website'] ?? null),
            'description' => Str::limit(trim((string) ($profile['description'] ?? '')), 1200, ''),
            'company_category_id' => $this->validId($profile['company_category_id'] ?? null, collect($context['categories'])->pluck('id')),
            'status' => $this->validStatus($profile['status'] ?? null),
            'branches' => $branches->all(),
        ];
    }

    private function normalizeBranch(array $branch, array $context, bool $includeDepartments): array
    {
        $city = $this->validCity($branch['city_id'] ?? null, collect($context['cities']));
        $countryId = $this->validId($branch['country_id'] ?? null, collect($context['countries'])->pluck('id'))
            ?? $city['country_id']
            ?? $context['defaults']['country_id'];
        $latitude = $this->validCoordinate($branch['latitude'] ?? null) ?? $city['latitude'] ?? $context['defaults']['latitude'];
        $longitude = $this->validCoordinate($branch['longitude'] ?? null) ?? $city['longitude'] ?? $context['defaults']['longitude'];

        return [
            'name' => Str::limit(trim((string) ($branch['name'] ?? __('Main Branch'))), 160, ''),
            'email' => filter_var($branch['email'] ?? null, FILTER_VALIDATE_EMAIL) ? $branch['email'] : null,
            'phone' => $this->sanitizePhone($branch['phone'] ?? null),
            'country_id' => $countryId,
            'city_id' => $city['id'] ?? $context['defaults']['city_id'],
            'latitude' => $latitude,
            'longitude' => $longitude,
            'location' => [
                'lat' => $latitude,
                'lng' => $longitude,
            ],
            'working_hours' => $this->normalizeWorkingHours($branch['working_hours'] ?? []),
            'departments' => $includeDepartments
                ? $this->normalizeDepartments($branch['departments'] ?? [])
                : [],
        ];
    }

    private function normalizeWorkingHours(array $workingHours): array
    {
        $byDay = collect($workingHours)->keyBy(fn ($workingHour) => (int) ($workingHour['day'] ?? 0));

        return collect(WeekDay::cases())
            ->map(function (WeekDay $day) use ($byDay): array {
                $workingHour = $byDay->get($day->value, []);
                $isClosed = (bool) ($workingHour['is_closed'] ?? ($day === WeekDay::FRIDAY));

                return [
                    'day' => $day->value,
                    'is_closed' => $isClosed,
                    'start_time' => $isClosed ? null : $this->validTime($workingHour['start_time'] ?? null, '08:00'),
                    'end_time' => $isClosed ? null : $this->validTime($workingHour['end_time'] ?? null, '16:00'),
                ];
            })
            ->values()
            ->all();
    }

    private function defaultWorkingHours(): array
    {
        return $this->normalizeWorkingHours([]);
    }

    private function normalizeDepartments(array $departments): array
    {
        return collect($departments)
            ->map(fn ($department): string => trim((string) $department))
            ->filter()
            ->unique(fn (string $department): string => Str::lower($department))
            ->take(8)
            ->map(fn (string $department): array => [
                'name' => Str::limit($department, 120, ''),
                'user_id' => null,
            ])
            ->values()
            ->all();
    }

    private function fallbackDepartments(string $brief): array
    {
        $brief = Str::lower($brief);

        $departments = match (true) {
            str_contains($brief, 'software') || str_contains($brief, 'برمج') || str_contains($brief, 'it') => [
                __('Software Development'),
                __('Technical Support'),
                __('Training'),
            ],
            str_contains($brief, 'design') || str_contains($brief, 'تصميم') => [
                __('Design'),
                __('Marketing'),
                __('Training'),
            ],
            default => [
                __('Operations'),
                __('Training'),
            ],
        };

        return $this->normalizeDepartments($departments);
    }

    private function fallbackDescription(string $brief, string $name): string
    {
        $brief = trim(strip_tags($brief));

        if (mb_strlen($brief) >= 80) {
            return Str::limit($brief, 900, '');
        }

        return trim("{$name} شركة تدريب وشراكة مهنية تعمل على توفير بيئة عملية مناسبة للطلبة، مع التركيز على تطوير المهارات وربط المعرفة الأكاديمية باحتياجات سوق العمل.");
    }

    private function guessCompanyName(string $brief, ?string $website): string
    {
        if (preg_match('/(?:اسم الشركة|الشركة|company|name)\s*[:：-]\s*([^\n,،]+)/iu', $brief, $matches)) {
            return Str::limit(trim($matches[1]), 160, '');
        }

        if ($website) {
            $host = parse_url($website, PHP_URL_HOST);

            if ($host) {
                return Str::headline(preg_replace('/^www\./', '', explode('.', $host)[0]));
            }
        }

        $line = trim((string) Str::of($brief)->replace(["\r"], "\n")->explode("\n")->first());

        return Str::limit($line ?: __('New Company'), 80, '');
    }

    private function extractWebsite(string $brief): ?string
    {
        if (! preg_match('/https?:\/\/[^\s]+/i', $brief, $matches)) {
            return null;
        }

        return $this->validUrl($matches[0]);
    }

    private function extractEmail(string $brief): ?string
    {
        if (! preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $brief, $matches)) {
            return null;
        }

        return strtolower($matches[0]);
    }

    private function extractPhone(string $brief): ?string
    {
        if (! preg_match('/(?:\+?\d[\d\s\-()]{6,}\d)/', $brief, $matches)) {
            return null;
        }

        return $this->sanitizePhone($matches[0]);
    }

    private function bestCategoryId(string $brief, Collection $categories): ?int
    {
        $brief = Str::lower($brief);

        $bestMatch = $categories
            ->map(fn (array $category): array => [
                'id' => $category['id'],
                'score' => similar_text(Str::lower((string) $category['name']), $brief),
            ])
            ->sortByDesc('score')
            ->first(fn (array $category): bool => $category['score'] > 2);

        return $bestMatch['id'] ?? null;
    }

    private function bestCity(string $brief, Collection $cities, array $defaults): array
    {
        $brief = Str::lower($brief);
        $matched = $cities->first(fn (array $city): bool => filled($city['name']) && str_contains($brief, Str::lower((string) $city['name'])));

        return $matched ?? [
            'id' => $defaults['city_id'],
            'country_id' => $defaults['country_id'],
            'latitude' => $defaults['latitude'],
            'longitude' => $defaults['longitude'],
        ];
    }

    private function validCity(mixed $cityId, Collection $cities): ?array
    {
        $cityId = filled($cityId) ? (int) $cityId : null;

        return $cityId ? $cities->firstWhere('id', $cityId) : null;
    }

    private function defaultCountry(Collection $countries): ?array
    {
        return $countries->first(fn (array $country): bool => in_array(Str::lower((string) $country['name']), [
            'palestine',
            'فلسطين',
        ], true)) ?? $countries->first();
    }

    private function defaultCity(Collection $cities): ?array
    {
        return $cities->first(fn (array $city): bool => in_array(Str::lower((string) $city['name']), [
            'hebron',
            'الخليل',
        ], true)) ?? $cities->first();
    }

    private function validId(mixed $id, Collection $allowedIds): ?int
    {
        $id = filled($id) ? (int) $id : null;

        return $id && $allowedIds->contains($id) ? $id : null;
    }

    private function validStatus(mixed $status): int
    {
        $status = filled($status) ? (int) $status : CompanyStatus::ACTIVE->value;

        return collect(CompanyStatus::cases())->pluck('value')->contains($status)
            ? $status
            : CompanyStatus::ACTIVE->value;
    }

    private function validUrl(mixed $url): ?string
    {
        $url = trim((string) $url);

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    private function sanitizePhone(mixed $phone): ?string
    {
        $phone = preg_replace('/[^0-9+]/', '', (string) $phone);

        return filled($phone) ? Str::limit($phone, 30, '') : null;
    }

    private function validCoordinate(mixed $coordinate): ?float
    {
        return is_numeric($coordinate) ? round((float) $coordinate, 8) : null;
    }

    private function validTime(mixed $time, string $fallback): string
    {
        $time = trim((string) $time);

        return preg_match('/^\d{2}:\d{2}/', $time) ? substr($time, 0, 5) : $fallback;
    }

    private function aiIsConfigured(): bool
    {
        $provider = $this->configuredProvider() ?? config('ai.default');

        if ($provider instanceof Lab) {
            $provider = $provider->value;
        }

        if (is_array($provider)) {
            return collect($provider)
                ->map(fn ($model, $providerName) => is_int($providerName) ? $model : $providerName)
                ->contains(fn ($providerName) => $this->providerHasCredentials((string) $providerName));
        }

        return $this->providerHasCredentials((string) $provider);
    }

    private function providerHasCredentials(string $provider): bool
    {
        if ($provider === 'ollama') {
            return filled(config('ai.providers.ollama.url'));
        }

        return filled(config("ai.providers.{$provider}.key"));
    }

    private function configuredProvider(): Lab|array|string|null
    {
        $provider = config('ai.company_profile_generator.provider');

        return blank($provider) ? null : $provider;
    }

    private function configuredModel(): ?string
    {
        $model = config('ai.company_profile_generator.model');

        return blank($model) ? null : $model;
    }

    private function configuredTimeout(): int
    {
        return (int) config('ai.company_profile_generator.timeout', 60);
    }
}
