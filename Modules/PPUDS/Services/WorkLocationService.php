<?php

namespace Modules\PPUDS\Services;

use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Enums\WorkLocationEnforcement;
use Modules\PPUDS\Settings\GeneralSettings;

/**
 * Decides whether a student is allowed to stamp attendance from where they
 * are standing.
 *
 * Enforcement is off by default and, when on, can be limited to a chosen set
 * of majors — so a specialty whose training happens off-site (field work,
 * remote placements) can be exempted without weakening the rule for everyone
 * else.
 */
class WorkLocationService
{
    /** Metres, used when the setting holds a nonsensical value. */
    public const FALLBACK_DISTANCE_METERS = 200;

    public function __construct(protected GeneralSettings $settings) {}

    public function mode(): WorkLocationEnforcement
    {
        return $this->settings->work_location_enforcement;
    }

    public function allowedDistanceMeters(): int
    {
        $distance = (int) $this->settings->work_location_allowed_distance_meters;

        return $distance > 0 ? $distance : self::FALLBACK_DISTANCE_METERS;
    }

    /** @return array<int, int> */
    public function requiredMajorIds(): array
    {
        return collect($this->settings->work_location_required_major_ids)
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function enforcesOnCheckOut(): bool
    {
        return (bool) $this->settings->work_location_enforce_on_check_out;
    }

    /**
     * Whether this particular placement has to stamp from the branch.
     *
     * A student with no major on file is treated as exempt in SELECTED_MAJORS
     * mode: the rule names the majors it applies to, and an unknown major is
     * not one of them.
     */
    public function isEnforcedFor(StudentCompany $studentCompany): bool
    {
        return match ($this->mode()) {
            WorkLocationEnforcement::DISABLED => false,
            WorkLocationEnforcement::ALL_MAJORS => true,
            WorkLocationEnforcement::SELECTED_MAJORS => in_array(
                (int) $studentCompany->student?->studentProfile?->major_id,
                $this->requiredMajorIds(),
                true,
            ),
        };
    }

    /**
     * Checks one stamp attempt.
     *
     * Returns null when the stamp is allowed — including when the rule cannot
     * be applied, such as a branch with no coordinates on file. Failing open
     * there is deliberate: an unmapped branch is an administrative gap, and
     * blocking a student over it would punish the wrong person. Those stamps
     * still surface in the non-compliance report.
     *
     * @return array{distance_meters: int, allowed_distance_meters: int}|null the violation, if any
     */
    public function violationFor(StudentCompany $studentCompany, mixed $latitude, mixed $longitude): ?array
    {
        if (! $this->isEnforcedFor($studentCompany)) {
            return null;
        }

        $branch = $studentCompany->branch;

        if (blank($branch?->latitude) || blank($branch?->longitude)) {
            return null;
        }

        if (blank($latitude) || blank($longitude)) {
            return null;
        }

        $distance = $this->distanceInMeters(
            (float) $latitude,
            (float) $longitude,
            (float) $branch->latitude,
            (float) $branch->longitude,
        );

        $allowed = $this->allowedDistanceMeters();

        if ($distance <= $allowed) {
            return null;
        }

        return [
            'distance_meters' => $distance,
            'allowed_distance_meters' => $allowed,
        ];
    }

    /** The message shown to a student whose stamp was refused. */
    public function violationMessage(array $violation): string
    {
        return __('You must be at the training workplace to record attendance. You are :distance m away, and the allowed range is :allowed m.', [
            'distance' => number_format($violation['distance_meters']),
            'allowed' => number_format($violation['allowed_distance_meters']),
        ]);
    }

    /** Great-circle distance, rounded to whole metres. */
    public function distanceInMeters(float $latitudeA, float $longitudeA, float $latitudeB, float $longitudeB): int
    {
        $earthRadius = 6371000;
        $latitudeDelta = deg2rad($latitudeB - $latitudeA);
        $longitudeDelta = deg2rad($longitudeB - $longitudeA);

        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($latitudeA)) * cos(deg2rad($latitudeB))
            * sin($longitudeDelta / 2) ** 2;

        return (int) round($earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a)));
    }
}
