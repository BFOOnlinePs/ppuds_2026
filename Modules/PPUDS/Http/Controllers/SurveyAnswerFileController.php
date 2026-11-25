<?php

namespace Modules\PPUDS\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Core\Enums\UserRole;
use Modules\PPUDS\Entities\SurveyAnswer;
use Modules\PPUDS\Enums\SurveyQuestionType;
use Symfony\Component\HttpFoundation\Response;

/**
 * يفتح الملف المرفوع كإجابة على سؤال "رفع ملف" في الاستبيان.
 * يُخدَّم الملف من خلال التطبيق نفسه حتى لا يعتمد فتحه على رابط storage العام،
 * ولا يراه إلا من يملك عرض تسليمات الاستبيانات أو صاحب الإجابة نفسه.
 */
class SurveyAnswerFileController extends Controller
{
    /** الأقراص التي قد يكون الملف محفوظاً عليها، بالترتيب. */
    private const DISKS = ['public', 'local'];

    public function __invoke(SurveyAnswer $answer): Response
    {
        abort_unless($this->canView($answer), 403);

        $answer->loadMissing('question');
        abort_unless((int) $answer->question?->type === SurveyQuestionType::FILE->value, 404);

        $path = trim((string) $answer->text_answer);
        abort_if($path === '', 404);

        // إجابات قديمة/من التطبيق قد تحفظ رابطاً كاملاً بدل مسار.
        if (Str::startsWith($path, ['http://', 'https://'])) {
            return redirect()->away($path);
        }

        $path = ltrim(Str::after($path, '/storage/'), '/');
        abort_if(Str::contains($path, '..'), 404);

        foreach (self::DISKS as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return Storage::disk($disk)->response($path, basename($path), [], 'inline');
            }
        }

        abort(404);
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
