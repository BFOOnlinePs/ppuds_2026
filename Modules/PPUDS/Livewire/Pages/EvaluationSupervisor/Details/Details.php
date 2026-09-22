<?php

namespace Modules\PPUDS\Livewire\Pages\EvaluationSupervisor\Details;

use App\View\Components\AppLayout;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Settings\GeneralSettings;

/**
 * سجل مشرف التقييم: بياناته، ثم أرقام تقدّمه في رصد العلامات، ثم الطلاب
 * المسندون إليه وسجل نشاطه كلٌّ في تبويب.
 */
class Details extends Component
{
    public int $supervisorId;

    #[Url(as: 'tab', keep: true)]
    public string $tab = 'students';

    public function mount(User $user): void
    {
        // السجل لمشرفي التقييم فقط، فلا تُكشف بيانات أي مستخدم آخر بتغيير الرقم في الرابط.
        abort_unless(
            $user->hasRole(UserRole::EVALUATION_SUPERVISOR->value)
            || StudentCompany::query()->where('evaluation_supervisor_id', $user->id)->exists(),
            404
        );

        $this->supervisorId = $user->id;

        $this->selectTab($this->tab);
    }

    public function selectTab(string $tab): void
    {
        $this->tab = $tab === 'activity' && $this->canViewActivity()
            ? 'activity'
            : 'students';
    }

    /**
     * سجل النشاط يعرض كل ما فعله المستخدم في النظام، فيُحكم بنفس صلاحية
     * تقرير المشرفين التي تحكمه هناك.
     */
    public function canViewActivity(): bool
    {
        return (bool) auth()->user()?->can('Supervisor Report View List');
    }

    #[Computed]
    public function supervisor(): User
    {
        return User::withTrashed()->with(['roles', 'media'])->findOrFail($this->supervisorId);
    }

    /**
     * كل الأرقام من استعلام واحد. العلامة تُرصد لكل تدريب، لذلك التقدّم
     * يُحسب على التدريبات بينما عدد الطلاب بلا تكرار.
     *
     * @return array<string, int|float|null>
     */
    #[Computed]
    public function summary(): array
    {
        $row = StudentCompany::query()
            ->where('evaluation_supervisor_id', $this->supervisorId)
            ->toBase()
            ->selectRaw('COUNT(*) as trainings')
            ->selectRaw('COUNT(DISTINCT student_id) as students')
            ->selectRaw('COUNT(DISTINCT company_id) as companies')
            ->selectRaw('COUNT(evaluation_score) as graded')
            ->selectRaw('AVG(evaluation_score) as average')
            ->first();

        $trainings = (int) ($row->trainings ?? 0);
        $graded = (int) ($row->graded ?? 0);

        return [
            'students' => (int) ($row->students ?? 0),
            'companies' => (int) ($row->companies ?? 0),
            'trainings' => $trainings,
            'graded' => $graded,
            'pending' => $trainings - $graded,
            'completion' => $trainings > 0 ? (int) round(($graded / $trainings) * 100) : 0,
            'average' => $row->average !== null ? round((float) $row->average, 1) : null,
            'max_grade' => app(GeneralSettings::class)->evaluation_supervisor_max_grade,
        ];
    }

    public function render()
    {
        return view('ppuds::livewire.pages.evaluation-supervisor.details.details')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Evaluation Supervisors List'), 'url' => route('evaluation-supervisors.index')],
                ['title' => $this->supervisor->name, 'url' => route('evaluation-supervisors.details', $this->supervisorId)],
            ],
        ]);
    }
}
