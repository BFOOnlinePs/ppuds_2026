<?php

namespace Modules\Core\Actions\StudentCompanyAssistant;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\Core\Entities\User;

class FindStudentsForCompanyAssistant
{
    public function handle(string $term, int $limit = 6): Collection
    {
        $terms = $this->searchTerms($term);

        return User::query()
            ->with('studentProfile.major')
            ->whereHas('studentProfile')
            ->where(function ($query) use ($terms) {
                foreach ($terms as $term) {
                    $query
                        ->orWhere('name', 'like', "%{$term}%")
                        ->orWhere('name_en', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhereHas('studentProfile', fn ($profileQuery) => $profileQuery->where('student_number', 'like', "%{$term}%"));
                }
            })
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    public function exactMatch(Collection $matches, string $term): ?User
    {
        $normalizedTerm = Str::lower($term);
        $normalizedArabicTerm = $this->normalizeArabic($term);

        return $matches->first(function (User $student) use ($normalizedTerm, $normalizedArabicTerm) {
            return Str::lower((string) $student->name) === $normalizedTerm
                || $this->normalizeArabic((string) $student->name) === $normalizedArabicTerm
                || Str::lower((string) $student->name_en) === $normalizedTerm
                || (string) $student->studentProfile?->student_number === $normalizedTerm
                || Str::lower((string) $student->email) === $normalizedTerm;
        });
    }

    private function searchTerms(string $term): array
    {
        $term = trim(preg_replace('/\s+/u', ' ', $term) ?? $term);

        if ($term === '') {
            return [];
        }

        $normalizedTerm = $this->normalizeArabic($term);

        return collect([
            $term,
            $normalizedTerm,
            ...$this->alefVariants($normalizedTerm),
        ])
            ->filter()
            ->unique()
            ->take(80)
            ->values()
            ->all();
    }

    private function normalizeArabic(string $value): string
    {
        $value = Str::lower(trim(preg_replace('/\s+/u', ' ', $value) ?? $value));
        $value = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $value) ?? $value;

        return str_replace(
            ['أ', 'إ', 'آ', 'ٱ', 'ؤ', 'ئ', 'ى', 'ة', 'ـ'],
            ['ا', 'ا', 'ا', 'ا', 'و', 'ي', 'ي', 'ه', ''],
            $value,
        );
    }

    private function alefVariants(string $value): array
    {
        $variants = [''];
        $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($characters as $character) {
            $replacements = $character === 'ا'
                ? ['ا', 'أ', 'إ', 'آ', 'ٱ']
                : [$character];

            $nextVariants = [];

            foreach ($variants as $variant) {
                foreach ($replacements as $replacement) {
                    $nextVariants[] = $variant.$replacement;

                    if (count($nextVariants) >= 80) {
                        break 2;
                    }
                }
            }

            $variants = $nextVariants;
        }

        return $variants;
    }
}
