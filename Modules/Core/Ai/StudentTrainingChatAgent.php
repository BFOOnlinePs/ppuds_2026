<?php

namespace Modules\Core\Ai;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

class StudentTrainingChatAgent implements Agent, Conversational
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return implode("\n", [
            'أنت مساعد ذكي داخل نظام التدريب الجامعي PPUDS.',
            'تجيب بالعربية بشكل واضح ومباشر يشبه أسلوب ChatGPT.',
            'اعتمد فقط على بيانات النظام المرسلة لك داخل system_data، ولا تخترع أي معلومة غير موجودة.',
            'ركّز على معلومات الطالب والتدريب: الشركة، الفرع، القسم، المشرف، حالة التدريب، الحضور، طلبات الغياب، التقارير، والزيارات الميدانية.',
            'إذا كانت المعلومة غير موجودة قل إنها غير مسجلة في النظام.',
            'لا تعرض المعرفات الداخلية إلا إذا كانت مفيدة جدًا لفهم الحالة.',
            'اكتب الإجابة على شكل فقرات قصيرة ونقاط منظمة بدون إطالة.',
        ]);
    }

    /**
     * @return Message[]
     */
    public function messages(): iterable
    {
        return [];
    }
}
