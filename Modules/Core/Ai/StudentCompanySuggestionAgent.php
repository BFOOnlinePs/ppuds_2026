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

class StudentCompanySuggestionAgent implements Agent, Conversational, HasStructuredOutput, HasTools
{
    use Promptable;

    public function __construct(
        private readonly int $limit = 5,
    ) {}

    public function instructions(): Stringable|string
    {
        return implode("\n", [
            'أنت مساعد داخل نظام تدريب جامعي.',
            'مهمتك اختيار شركات مناسبة لطالب واحد من قائمة شركات محددة فقط.',
            'لا تقترح أي شركة غير موجودة في قائمة المرشحين، ولا تغيّر أرقام المعرفات.',
            'فضّل الشركات ذات العلاقة بتخصص الطالب ومقرره، ووازن بين الملاءمة وعدد الطلاب الحاليين في الشركة.',
            'اكتب سببًا قصيرًا وواضحًا بالعربية لكل اقتراح.',
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
            'summary' => $schema->string()->required(),
            'suggestions' => $schema->array()
                ->min(1)
                ->max($this->limit)
                ->items($schema->object([
                    'company_id' => $schema->integer()->required(),
                    'branch_id' => $schema->integer()->nullable()->required(),
                    'department_id' => $schema->integer()->nullable()->required(),
                    'reason' => $schema->string()->max(280)->required(),
                    'fit_score' => $schema->integer()->min(1)->max(100)->required(),
                ])->required())
                ->required(),
        ];
    }
}
