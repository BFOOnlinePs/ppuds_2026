<?php

namespace Modules\Core\Ai\Tools\AiAssistant;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Modules\Core\Actions\AiAssistant\FindNearestCompaniesForStudent;
use Modules\Core\Actions\AiAssistant\ResolveCityByName;
use Modules\GeoLocation\Entities\City;
use Stringable;

class SuggestNearestCompanies extends AiAssistantTool
{
    public function description(): Stringable|string
    {
        return 'اقتراح أقرب الشركات النشطة لمكان سكن الطالب مع المسافة بالكيلومتر. يستخدم مدينة السكن المحفوظة للطالب، أو city_name إذا ذكرها المستخدم.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (! $this->userCan('StudentCompany Create')) {
            return $this->denied('StudentCompany Create');
        }

        $student = $this->accessibleStudent($request->integer('student_id'));

        if (! $student) {
            return $this->studentNotFound();
        }

        $cityName = trim((string) $request->string('city_name'));
        $origin = $student->studentProfile?->residenceCity;

        if ($cityName !== '') {
            $cities = app(ResolveCityByName::class)->handle($cityName);

            if ($cities->count() > 1 && ! $cities->contains(fn (City $city) => $city->name === $cityName)) {
                return $this->respond([
                    'ok' => false,
                    'error' => 'يوجد أكثر من مدينة مطابقة، اسأل المستخدم أيها يقصد.',
                    'cities' => $cities->map(fn (City $city) => $city->name)->values()->all(),
                ]);
            }

            $origin = $cities->firstWhere('name', $cityName) ?? $cities->first();

            if (! $origin) {
                return $this->error("لم أجد مدينة باسم ({$cityName}) في النظام.");
            }
        }

        if (! $origin) {
            return $this->error('مدينة سكن الطالب غير محفوظة. اسأل المستخدم عن مدينة السكن، ثم احفظها عبر SetStudentResidence أو مرّرها في city_name.');
        }

        $limit = min(10, max(1, $request->integer('limit', 5) ?: 5));
        $companies = app(FindNearestCompaniesForStudent::class)->handle($origin, $limit);

        return $this->respond([
            'ok' => true,
            'student' => $this->studentSummary($student),
            'origin_city' => $origin->name,
            'origin_has_coordinates' => filled($origin->latitude) && filled($origin->longitude),
            'count' => $companies->count(),
            'companies' => $companies->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'student_id' => $schema->integer()->description('student_id من SearchStudents')->required(),
            'city_name' => $schema->string()->description('اختياري: مدينة السكن إذا ذكرها المستخدم'),
            'limit' => $schema->integer()->description('عدد الشركات المطلوب (1-10)، الافتراضي 5'),
        ];
    }
}
