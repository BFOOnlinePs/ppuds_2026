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

    public function index(GeneralSettings $settings)
    {
        return $this->successResponse(
            new GeneralSettingsResource($settings),
            __('General settings retrieved successfully')
        );
    }

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
