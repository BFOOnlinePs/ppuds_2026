<?php

namespace Modules\Core\Ai\Tools\AiAssistant;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Modules\Core\Actions\AiAssistant\ResolveCityByName;
use Modules\GeoLocation\Entities\City;
use Stringable;

class SetStudentResidence extends AiAssistantTool
{
    public function description(): Stringable|string
    {
        return 'حفظ مدينة سكن الطالب في ملفه، حتى تُستخدم لاحقًا في اقتراح أقرب شركة.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (! $this->userCan('Student Update')) {
            return $this->denied('Student Update');
        }

        $student = $this->accessibleStudent($request->integer('student_id'));

        if (! $student) {
            return $this->studentNotFound();
        }

        $cityName = trim((string) $request->string('city_name'));
        $cities = app(ResolveCityByName::class)->handle($cityName);
        $city = $cities->firstWhere('name', $cityName) ?? ($cities->count() === 1 ? $cities->first() : null);

        if (! $city) {
            return $this->respond([
                'ok' => false,
                'error' => $cities->isEmpty()
                    ? "لم أجد مدينة باسم ({$cityName}) في النظام."
                    : 'يوجد أكثر من مدينة مطابقة، اسأل المستخدم أيها يقصد.',
                'cities' => $cities->map(fn (City $city) => $city->name)->values()->all(),
            ]);
        }

        $student->studentProfile->update(['residence_city_id' => $city->id]);

        return $this->respond([
            'ok' => true,
            'message' => "تم حفظ مدينة السكن ({$city->name}) للطالب {$student->name}.",
            'student_id' => $student->id,
            'residence_city' => $city->name,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'student_id' => $schema->integer()->description('student_id من SearchStudents')->required(),
            'city_name' => $schema->string()->description('اسم مدينة السكن كما كتبها المستخدم')->required(),
        ];
    }
}
