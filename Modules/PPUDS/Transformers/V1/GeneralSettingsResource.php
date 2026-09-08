<?php

namespace Modules\PPUDS\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\PPUDS\Enums\SurveyQuestionType;

class GeneralSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'semester_type'         => $this->semester_type?->value,
            'year'                  => $this->year,
            'report_status'         => $this->report_status?->value,
            'login_method'          => $this->login_method?->value,
            'giz_evaluation_status' => $this->giz_evaluation_status?->value,
            'start_semester'        => $this->start_semester?->format('Y-m-d'),
            'end_semester'          => $this->end_semester?->format('Y-m-d'),

            'evaluation'            => $this->evaluation(),

            'facebook_url'          => $this->facebook_url,
            'linkedin_url'          => $this->linkedin_url,
            'x_url'                 => $this->x_url,
            'instagram_url'         => $this->instagram_url,
        ];
    }

    /**
     * توزيع العلامات ومقياس التقييم، حتى تبني الواجهة شاشات الرصد دون أن
     * تُثبّت السقوف في كودها — فهي قابلة للتغيير من الإعدادات.
     *
     * @return array<string, mixed>
     */
    protected function evaluation(): array
    {
        $ratingScale = SurveyQuestionType::ratingScaleOptions();

        return [
            'evaluation_supervisor_max_grade' => $this->evaluation_supervisor_max_grade,
            'university_supervisor_max_grade' => $this->university_supervisor_max_grade,
            'company_max_grade'               => $this->company_max_grade,
            'total_max_grade'                 => $this->evaluation_supervisor_max_grade
                + $this->university_supervisor_max_grade
                + $this->company_max_grade,

            // علامة الشركة محسوبة من هذا المقياس، فلا تُرصد يدوياً.
            'rating_scale' => [
                'min'     => (int) min(array_keys($ratingScale)),
                'max'     => (int) max(array_keys($ratingScale)),
                'options' => collect($ratingScale)
                    ->map(fn (string $label, int $value): array => [
                        'value' => $value,
                        'label' => $label,
                    ])
                    ->values()
                    ->all(),
            ],
        ];
    }
}
