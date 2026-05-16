<?php

namespace Modules\Core\Actions\StudentCompanyAssistant;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\Core\Entities\User;

class FindStudentsForCompanyAssistant
{
    public function handle(string $term, int $limit = 6): Collection
    {
        return User::query()
            ->with('studentProfile.major')
            ->whereHas('studentProfile')
            ->where(function ($query) use ($term) {
                $query
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('name_en', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhereHas('studentProfile', fn ($profileQuery) => $profileQuery->where('student_number', 'like', "%{$term}%"));
            })
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    public function exactMatch(Collection $matches, string $term): ?User
    {
        $normalizedTerm = Str::lower($term);

        return $matches->first(function (User $student) use ($normalizedTerm) {
            return Str::lower((string) $student->name) === $normalizedTerm
                || Str::lower((string) $student->name_en) === $normalizedTerm
                || (string) $student->studentProfile?->student_number === $normalizedTerm
                || Str::lower((string) $student->email) === $normalizedTerm;
        });
    }
}
