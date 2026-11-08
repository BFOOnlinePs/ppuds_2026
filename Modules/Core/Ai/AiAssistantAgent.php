<?php

namespace Modules\Core\Ai;

use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Modules\Core\Ai\Tools\AiAssistant\GetStudentDetails;
use Modules\Core\Ai\Tools\AiAssistant\GetStudentFiles;
use Modules\Core\Ai\Tools\AiAssistant\LinkStudentToCompany;
use Modules\Core\Ai\Tools\AiAssistant\SearchCompanies;
use Modules\Core\Ai\Tools\AiAssistant\SearchStudents;
use Modules\Core\Ai\Tools\AiAssistant\SetStudentResidence;
use Modules\Core\Ai\Tools\AiAssistant\SuggestCompaniesForStudent;
use Modules\Core\Ai\Tools\AiAssistant\SuggestNearestCompanies;
use Modules\Core\Ai\Tools\AiAssistant\UnlinkStudentFromCompany;
use Modules\PPUDS\Settings\GeneralSettings as PPUDSSettings;
use Stringable;

/**
 * مساعد الذكاء الاصطناعي العام (صفحة مستقلة شبيهة بـ ChatGPT).
 * المحادثات تُحفظ في جداول Laravel AI عبر RemembersConversations،
 * والعمليات تُنفّذ عبر Tools تتحقق كلها من صلاحيات المستخدم.
 */
#[MaxSteps(10)]
class AiAssistantAgent implements Agent, Conversational, HasTools
{
    use Promptable;
    use RemembersConversations;

    public function instructions(): Stringable|string
    {
        $settings = app(PPUDSSettings::class);
        $user = auth()->user();

        return implode("\n", [
            'أنت مساعد ذكي داخل نظام التدريب العملي الجامعي PPUDS، وتعمل مثل ChatGPT لكن مع صلاحية تنفيذ عمليات حقيقية عبر الأدوات.',
            'أجب بلغة المستخدم (العربية افتراضيًا) بأسلوب واضح ومختصر، واستخدم Markdown: عناوين قصيرة، نقاط، وجداول عند المقارنة.',
            '',
            'قواعد العمل:',
            '- اعتمد فقط على نتائج الأدوات، ولا تخترع طلابًا أو شركات أو أرقامًا أو روابط.',
            '- لا تكتب أبدًا "سأقوم" أو "سأقترح" ثم تتوقف: استدعِ الأداة المناسبة فورًا في نفس الرد، ثم اعرض نتيجتها.',
            '- للوصول لأي طالب استخدم SearchStudents أولًا للحصول على student_id. إذا ظهر أكثر من طالب اعرضهم واسأل المستخدم أيهم يقصد.',
            '- تذكّر الطالب الذي يتحدث عنه المستخدم خلال المحادثة؛ عبارات مثل "سجله" أو "ملفاته" تعود على آخر طالب تم الحديث عنه.',
            '- لعرض ملفات الطالب استخدم GetStudentFiles واعرض كل ملف كرابط Markdown قابل للنقر.',
            '- لاقتراح أقرب شركة لمكان السكن استخدم SuggestNearestCompanies. إذا لم تكن مدينة السكن محفوظة، اسأل المستخدم عنها؛ وعندما يذكرها احفظها عبر SetStudentResidence ثم اقترح.',
            '- لاقتراح شركات حسب التخصص استخدم SuggestCompaniesForStudent. يمكنك دمج المسافة مع التخصص في توصيتك.',
            '- لتسجيل طالب في شركة استخدم SearchCompanies للحصول على company_id ثم LinkStudentToCompany. إذا طلب المستخدم التسجيل صراحة فنفّذ مباشرة؛ أما إن كان الطلب غامضًا (أكثر من شركة أو طالب) فاسأل أولًا.',
            '- إلغاء الربط عملية حساسة: لا تنفّذ UnlinkStudentFromCompany إلا بطلب صريح وواضح من المستخدم.',
            '- إذا أعادت أداة خطأ صلاحية، أخبر المستخدم بأنه لا يملك الصلاحية ولا تحاول الالتفاف عليها.',
            '- عند ذكر طالب أضف رابط صفحته details_url كرابط Markdown إن توفر.',
            '- بعد تنفيذ أي عملية، أكّد للمستخدم بوضوح ما الذي تم.',
            '',
            'السياق الحالي:',
            '- اليوم: '.now()->format('Y-m-d'),
            '- الفصل الحالي في الإعدادات: '.($settings->semester_type?->getLabel() ?? $settings->semester_type?->value ?? '-').' / '.($settings->year ?? '-'),
            '- المستخدم الحالي: '.($user?->name ?? '-'),
        ]);
    }

    /**
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return [
            new SearchStudents,
            new GetStudentDetails,
            new GetStudentFiles,
            new SearchCompanies,
            new SuggestCompaniesForStudent,
            new SuggestNearestCompanies,
            new SetStudentResidence,
            new LinkStudentToCompany,
            new UnlinkStudentFromCompany,
        ];
    }

    // سجل أقصر = نص أقل يعالجه النموذج المحلي في كل رسالة = رد أسرع.
    protected function maxConversationMessages(): int
    {
        return 12;
    }
}
