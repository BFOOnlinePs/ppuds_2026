<?php

namespace Modules\Core\Ai;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

class CompanyProfileGeneratorAgent implements Agent, Conversational, HasStructuredOutput, HasTools
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return implode("\n", [
            'أنت مساعد إدخال بيانات داخل نظام تدريب جامعي.',
            'مهمتك تحويل وصف مختصر عن شركة إلى بيانات قابلة للتعبئة في نموذج إضافة الشركة.',
            'استخدم معرفات التصنيفات والدول والمدن المتاحة فقط. لا تختر أي معرف غير موجود في القوائم.',
            'إذا لم تكن متأكدًا من التصنيف أو المدينة، أعد null بدل اختراع قيمة.',
            'اكتب وصف الشركة بشكل مهني ومختصر بالعربية، واجعل البيانات عملية وقابلة للمراجعة من المستخدم قبل الحفظ.',
            'لا تنشئ مشرفين أو مستخدمين. الأقسام تكون أسماء مقترحة فقط.',
        ]);
    }

    /**
     * @return Message[]
     */
    public function messages(): iterable
    {
        return [];
    }

    /**
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return [];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'summary' => $schema->string()->max(240)->required(),
            'company' => $schema->object([
                'name' => $schema->string()->max(160)->required(),
                'website' => $schema->string()->nullable()->required(),
                'description' => $schema->string()->max(1200)->required(),
                'company_category_id' => $schema->integer()->nullable()->required(),
                'status' => $schema->integer()->nullable()->required(),
                'branches' => $schema->array()
                    ->min(1)
                    ->max(3)
                    ->items($schema->object([
                        'name' => $schema->string()->max(160)->required(),
                        'email' => $schema->string()->nullable()->required(),
                        'phone' => $schema->string()->nullable()->required(),
                        'country_id' => $schema->integer()->nullable()->required(),
                        'city_id' => $schema->integer()->nullable()->required(),
                        'latitude' => $schema->number()->nullable()->required(),
                        'longitude' => $schema->number()->nullable()->required(),
                        'departments' => $schema->array()
                            ->max(8)
                            ->items($schema->string()->max(120)->required())
                            ->required(),
                        'working_hours' => $schema->array()
                            ->min(7)
                            ->max(7)
                            ->items($schema->object([
                                'day' => $schema->integer()->min(1)->max(7)->required(),
                                'is_closed' => $schema->boolean()->required(),
                                'start_time' => $schema->string()->nullable()->required(),
                                'end_time' => $schema->string()->nullable()->required(),
                            ])->required())
                            ->required(),
                    ])->required())
                    ->required(),
            ])->required(),
        ];
    }
}
