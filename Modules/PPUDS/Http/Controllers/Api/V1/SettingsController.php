<?php

namespace Modules\PPUDS\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Modules\Core\Traits\ApiResponse;
use Modules\PPUDS\Enums\GigEvaluationStatus;
use Modules\PPUDS\Enums\LoginMethod;
use Modules\PPUDS\Enums\ReportStatus;
use Modules\PPUDS\Enums\SemesterType;
use Modules\PPUDS\Http\Requests\GeneralSettingsRequestUpdate;
use Modules\PPUDS\Settings\GeneralSettings;
use Modules\PPUDS\Transformers\V1\GeneralSettingsResource;

class SettingsController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     * path="/api/v1/ppuds/settings",
     * summary="Get general settings",
     * description="Retrieve all general system settings including semester dates and status configurations.",
     * tags={"Settings"},
     * security={{"sanctum": {}}},
     * @OA\Parameter(
     * name="Accept-Language",
     * in="header",
     * required=true,
     * description="Language header (ar or en)",
     * @OA\Schema(type="string", default="ar", example="en")
     * ),
     * @OA\Response(
     * response=200,
     * description="Settings retrieved successfully",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="General settings retrieved successfully"),
     * @OA\Property(property="data", type="object")
     * )
     * )
     * )
     */
    public function index(GeneralSettings $settings)
    {
        return $this->successResponse(
            new GeneralSettingsResource($settings),
            __('General settings retrieved successfully')
        );
    }


    /**
     * @OA\Put(
     * path="/api/v1/ppuds/settings",
     * summary="Update general settings",
     * description="Update the global system settings. Use PUT to update the settings object.",
     * tags={"Settings"},
     * security={{"sanctum": {}}},
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="semester_type", type="string", example="first_semester", description="Enum value from SemesterType"),
     * @OA\Property(property="report_status", type="integer", example=1, description="Enum value from ReportStatus"),
     * @OA\Property(property="login_method", type="string", example="sso", description="Enum value from LoginMethod"),
     * @OA\Property(property="giz_evaluation_status", type="integer", example=1),
     * @OA\Property(property="start_semester", type="string", format="date", example="2024-01-01"),
     * @OA\Property(property="end_semester", type="string", format="date", example="2024-06-01"),
     * @OA\Property(property="maintenance_mode", type="boolean", example=false)
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Settings updated successfully",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="General settings updated successfully"),
     * @OA\Property(property="data", type="object")
     * )
     * ),
     * @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(GeneralSettingsRequestUpdate $request, GeneralSettings $settings)
    {
        foreach ($request->validated() as $key => $value) {
            $settings->{$key} = match ($key) {
                'semester_type' => SemesterType::from($value),
                'report_status' => ReportStatus::from($value),
                'login_method' => LoginMethod::from($value),
                'giz_evaluation_status' => GigEvaluationStatus::from($value),
                'start_semester',
                'end_semester' => Carbon::parse($value),
                default => $value,
            };
        }

        $settings->save();

        return $this->successResponse(
            new GeneralSettingsResource($settings),
            __('General settings updated successfully')
        );
    }
}
