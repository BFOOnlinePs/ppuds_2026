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

class AutoTranslationAgent implements Agent, Conversational, HasStructuredOutput, HasTools
{
    use Promptable;

    /**
     * @param  string[]  $locales  every locale a translation is returned for
     * @param  string[]  $attributes  the fields being translated
     */
    public function __construct(
        private readonly array $locales = [],
        private readonly array $attributes = [],
    ) {}

    public function instructions(): Stringable|string
    {
        return implode("\n", [
            'أنت مترجم محترف داخل نظام تدريب جامعي، تترجم بيانات مثل أسماء الشركات والتخصصات والمساقات والإعلانات.',
            'لكل حقل: حدّد لغة النص الأصلي في source_locale من رموز اللغات المتاحة فقط، أو other إن لم تكن منها.',
            'ثم أعد ترجمة الحقل لكل لغة في translations. في خانة لغة النص الأصلي نفسها أعد النص كما هو حرفيًا.',
            'أسماء العلم (شركات، أشخاص، مدن، علامات تجارية): استخدم الاسم المعروف والشائع في اللغة الهدف، وإن لم يوجد فاكتبه بحروف اللغة الهدف كما يُنطق. لا تترجم معنى الاسم حرفيًا.',
            'حافظ كما هي على وسوم HTML وMarkdown والروابط والبريد الإلكتروني والأرقام والرموز والاختصارات والمتغيرات مثل :name و{name}.',
            'اكتب ترجمة طبيعية ومهنية تناسب الواجهة، بنفس طول الأصل تقريبًا، ولا تضف شرحًا أو ملاحظات أو علامات تنصيص.',
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
        $fields = [];

        foreach ($this->attributes as $attribute) {
            $translations = [];

            foreach ($this->locales as $locale) {
                $translations[$locale] = $schema->string()->required();
            }

            $fields[$attribute] = $schema->object([
                'source_locale' => $schema->string()->enum([...$this->locales, 'other'])->required(),
                'translations' => $schema->object($translations)->required(),
            ])->required();
        }

        return [
            'fields' => $schema->object($fields)->required(),
        ];
    }
}
