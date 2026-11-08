<?php

namespace Modules\Core\Actions\AiAssistant;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\GeoLocation\Entities\City;

class ResolveCityByName
{
    /**
     * يبحث عن المدينة بالاسم العربي أو الإنجليزي، ويُرجع المطابقات الأقرب أولًا.
     */
    public function handle(string $name, int $limit = 5): Collection
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);

        if (mb_strlen($name) < 2) {
            return collect();
        }

        $variants = collect([$name, $this->normalizeArabic($name), $this->withoutArticle($name)])
            ->filter()
            ->unique()
            ->values();

        $exact = City::query()
            ->with('translations')
            ->whereHas('translations', fn (Builder $query) => $query->whereIn('name', $variants->all()))
            ->limit($limit)
            ->get();

        if ($exact->isNotEmpty()) {
            return $exact;
        }

        return City::query()
            ->with('translations')
            ->whereHas('translations', function (Builder $query) use ($variants) {
                $query->where(function (Builder $query) use ($variants) {
                    foreach ($variants as $variant) {
                        $query->orWhere('name', 'like', "%{$variant}%");
                    }
                });
            })
            ->limit($limit)
            ->get();
    }

    private function normalizeArabic(string $value): string
    {
        return str_replace(['أ', 'إ', 'آ', 'ة', 'ى'], ['ا', 'ا', 'ا', 'ه', 'ي'], $value);
    }

    private function withoutArticle(string $value): string
    {
        return str_starts_with($value, 'ال') ? mb_substr($value, 2) : $value;
    }
}
