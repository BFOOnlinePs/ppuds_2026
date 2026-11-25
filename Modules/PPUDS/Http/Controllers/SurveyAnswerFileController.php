<?php

namespace Modules\PPUDS\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Core\Enums\UserRole;
use Modules\PPUDS\Entities\SurveyAnswer;
use Modules\PPUDS\Enums\SurveyQuestionType;
use Modules\PPUDS\Support\SurveyAnswerFiles;
use Symfony\Component\HttpFoundation\Response;

/**
 * يفتح الملف المرفوع كإجابة على سؤال "رفع ملف" في الاستبيان.
 * يُخدَّم الملف من خلال التطبيق نفسه حتى لا يعتمد فتحه على رابط storage العام،
 * ولا يراه إلا من يملك عرض تسليمات الاستبيانات أو صاحب الإجابة نفسه.
 */
class SurveyAnswerFileController extends Controller
{
    public function __invoke(SurveyAnswer $answer): Response
    {
        abort_unless($this->canView($answer), 403);

        $answer->loadMissing('question');
        abort_unless((int) $answer->question?->type === SurveyQuestionType::FILE->value, 404);

        $location = self::locate($answer->text_answer);

        if ($location === null) {
            Log::warning('Survey answer file not found', [
                'answer_id' => $answer->id,
                'text_answer' => $answer->text_answer,
            ]);

            abort(404);
        }

        // nosniff: يُعرض الملف بنوعه الحقيقي فقط، فلا يُنفَّذ ملف مرفوع كصفحة HTML/سكربت.
        return Storage::disk($location['disk'])->response(
            $location['path'],
            basename($location['path']),
            ['X-Content-Type-Options' => 'nosniff'],
            self::isInlineSafe($location['path']) ? 'inline' : 'attachment',
        );
    }

    /**
     * يحدد مكان الملف من القيمة المحفوظة في الإجابة.
     * لا يُفتح إلا ما هو داخل مجلد ملفات الاستبيانات، حتى لو حُفظ في الإجابة مسار آخر
     * (إجابات قديمة كان الـ API يقبل فيها أي نص)، فلا يمكن الوصول عبرها لملفات أقسام أخرى.
     *
     * @return array{disk: string, path: string}|null
     */
    public static function locate(?string $value): ?array
    {
        $value = trim((string) $value);

        if ($value === '' || self::isDevicePath($value) || Str::contains($value, ['://', "\0"])) {
            return null;
        }

        $path = ltrim(str_replace('\\', '/', $value), '/');

        if (Str::startsWith($path, 'storage/')) {
            $path = Str::after($path, 'storage/');
        }

        // اسم ملف فقط (بلا مجلد) يُبحث عنه داخل مجلد الاستبيانات.
        if (! Str::contains($path, '/')) {
            $path = SurveyAnswerFiles::DIRECTORY.'/'.$path;
        }

        $segments = explode('/', $path);

        if ($segments[0] !== SurveyAnswerFiles::DIRECTORY
            || count($segments) < 2
            || array_intersect($segments, ['', '.', '..']) !== []) {
            return null;
        }

        // الخاص أولاً (رفع التطبيق ونموذج الويب حالياً)، ثم العام (تسليمات الويب القديمة).
        foreach (array_unique([SurveyAnswerFiles::DISK, 'public']) as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return ['disk' => $disk, 'path' => $path];
            }
        }

        return null;
    }

    /** الصور وPDF والنص تُعرض في المتصفح، وما عداها يُنزَّل. */
    private static function isInlineSafe(string $path): bool
    {
        return in_array(Str::lower(pathinfo($path, PATHINFO_EXTENSION)), ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'txt'], true);
    }

    /**
     * إجابات قديمة من تطبيق الموبايل حُفظ فيها مسار الملف على جهاز الطالب بدل رفعه،
     * فلا يوجد ملف على الخادم أصلاً.
     */
    public static function isDevicePath(?string $value): bool
    {
        return Str::startsWith(trim((string) $value), [
            'file://',
            '/private/var/mobile/',
            '/var/mobile/',
            '/data/user/',
            '/data/data/',
            '/storage/emulated/',
            'content://',
        ]);
    }

    private function canView(SurveyAnswer $answer): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        // نفس الأدوار التي ترى تفاصيل التسليمات (Survey\Details\SubmissionDetails).
        return (int) $answer->submitted_by === (int) $user->id
            || $user->hasAnyRole([
                UserRole::SUPER_ADMIN->value,
                UserRole::ADMIN->value,
                UserRole::PRACTICAL_TRAINING_SUPERVISOR->value,
                'Academic Supervisor',
                'University Supervisor',
            ]);
    }
}
