<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\Course;
use Modules\PPUDS\Entities\Major;
use Modules\PPUDS\Entities\Registration;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Entities\StudentProfile;
use Modules\PPUDS\Entities\TrainingPlan;
use Modules\PPUDS\Livewire\Pages\Student\Details\Details as StudentDetails;
use Modules\PPUDS\Livewire\Pages\Student\Details\TrainingPlan\Index as PlanTab;
use Modules\PPUDS\Livewire\Pages\StudentCompany\Details as PlacementDetails;
use Modules\PPUDS\Livewire\Pages\TrainingPlan\Index;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TrainingPlanTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        // migrations المشروع تكرر أسماء الفهارس فلا تعمل على SQLite، وRefreshDatabase
        // يمسح القاعدة، لذلك لا يعمل هذا الاختبار إلا على قاعدة MySQL مخصصة للاختبارات:
        // DB_CONNECTION=mysql DB_DATABASE=ppuds_2026_test vendor/bin/phpunit --filter TrainingPlanTest
        if (! str_ends_with((string) getenv('DB_DATABASE'), '_test')) {
            $this->markTestSkipped('Requires a dedicated MySQL test database.');
        }

        parent::setUp();

        app()->setLocale('ar');

        $this->admin = $this->user();
    }

    public function test_placement_resolves_the_plan_of_its_student_major_and_course(): void
    {
        $major = $this->major('Fintech');
        $course = $this->course('STAGE-1');
        $plan = $this->plan($major, $course);
        $placement = $this->placement($major, $course);

        $this->assertTrue($plan->is($placement->trainingPlan()));
    }

    public function test_placement_does_not_resolve_a_plan_of_another_major_on_the_same_course(): void
    {
        $course = $this->course('STAGE-1');
        $this->plan($this->major('Fintech'), $course);
        $placement = $this->placement($this->major('Software'), $course);

        $this->assertNull($placement->trainingPlan());
    }

    public function test_placement_of_student_without_major_has_no_plan(): void
    {
        $major = $this->major('Fintech');
        $course = $this->course('STAGE-1');
        $this->plan($major, $course);
        $placement = $this->placement(null, $course);

        $this->assertNull($placement->trainingPlan());
    }

    public function test_plan_written_in_arabic_is_readable_from_the_english_interface(): void
    {
        $plan = $this->plan($this->major('Fintech'), $this->course('STAGE-1'));

        app()->setLocale('en');

        $this->assertSame(['هدف تجريبي'], TrainingPlan::find($plan->id)->objectives);
    }

    public function test_user_with_create_permission_can_fill_a_plan(): void
    {
        $this->actingAs($this->userWith(['TrainingPlan View List', 'TrainingPlan Create']));
        $major = $this->major('Fintech');
        $course = $this->course('STAGE-1');

        Livewire::test(Index::class)
            ->mountTableAction('create')
            ->set('mountedTableActionsData.0', [
                'major_id' => $major->id,
                'course_id' => $course->id,
                'objectives' => $this->items('هدف أول', 'هدف ثان'),
                'tasks' => $this->items('مهمة'),
                'outcomes' => $this->items('مخرج'),
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $plan = TrainingPlan::query()->where('major_id', $major->id)->where('course_id', $course->id)->sole();

        $this->assertSame(['هدف أول', 'هدف ثان'], $plan->objectives);
    }

    public function test_second_plan_for_the_same_major_and_course_is_rejected(): void
    {
        $this->actingAs($this->userWith(['TrainingPlan View List', 'TrainingPlan Create']));
        $major = $this->major('Fintech');
        $course = $this->course('STAGE-1');
        $this->plan($major, $course);

        Livewire::test(Index::class)
            ->mountTableAction('create')
            ->set('mountedTableActionsData.0', [
                'major_id' => $major->id,
                'course_id' => $course->id,
                'objectives' => $this->items('هدف'),
                'tasks' => $this->items('مهمة'),
                'outcomes' => $this->items('مخرج'),
            ])
            ->callMountedTableAction()
            ->assertHasTableActionErrors(['course_id']);

        $this->assertSame(1, TrainingPlan::query()->count());
    }

    public function test_deleted_plan_can_be_entered_again(): void
    {
        $this->actingAs($this->userWith(['TrainingPlan View List', 'TrainingPlan Create']));
        $major = $this->major('Fintech');
        $course = $this->course('STAGE-1');
        $this->plan($major, $course)->delete();

        Livewire::test(Index::class)
            ->mountTableAction('create')
            ->set('mountedTableActionsData.0', [
                'major_id' => $major->id,
                'course_id' => $course->id,
                'objectives' => $this->items('هدف جديد'),
                'tasks' => $this->items('مهمة'),
                'outcomes' => $this->items('مخرج'),
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertSame(1, TrainingPlan::query()->count());
    }

    public function test_user_with_update_permission_can_edit_a_plan(): void
    {
        $this->actingAs($this->userWith(['TrainingPlan View List', 'TrainingPlan Update']));
        $plan = $this->plan($this->major('Fintech'), $this->course('STAGE-1'));

        Livewire::test(Index::class)
            ->mountTableAction('edit', $plan)
            ->set('mountedTableActionsData.0', [
                'major_id' => $plan->major_id,
                'course_id' => $plan->course_id,
                'objectives' => $this->items('هدف معدل'),
                'tasks' => $this->items('مهمة'),
                'outcomes' => $this->items('مخرج'),
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertSame(['هدف معدل'], TrainingPlan::find($plan->id)->objectives);
    }

    public function test_write_actions_are_hidden_without_their_permissions(): void
    {
        $this->actingAs($this->userWith(['TrainingPlan View List']));
        $plan = $this->plan($this->major('Fintech'), $this->course('STAGE-1'));

        Livewire::test(Index::class)
            ->assertTableActionHidden('create')
            ->assertTableActionHidden('edit', $plan)
            ->assertTableActionHidden('delete', $plan);
    }

    public function test_plans_page_requires_the_view_list_permission(): void
    {
        $this->actingAs($this->userWith([]));

        $this->withoutLocaleRedirects();

        $this->get(route('training-plans.index'))->assertForbidden();
    }

    public function test_plans_page_opens_with_the_view_list_permission(): void
    {
        $this->withoutVite();
        $this->actingAs($this->userWith(['TrainingPlan View List']));

        $this->withoutLocaleRedirects();

        $this->get(route('training-plans.index'))
            ->assertOk()
            ->assertSee(__('Training Plans'));
    }

    public function test_user_with_view_permission_reads_the_plan_of_the_student(): void
    {
        $major = $this->major('Fintech');
        $course = $this->course('STAGE-1');
        $this->plan($major, $course);
        $placement = $this->placement($major, $course);
        $this->actingAs($this->adminWith(['TrainingPlan View']));

        Livewire::test(PlanTab::class, ['studentId' => $placement->student_id])
            ->assertSee('هدف تجريبي')
            ->assertSee('مهمة تجريبية')
            ->assertSee('مخرج تجريبي');
    }

    public function test_plan_tab_is_forbidden_without_the_view_permission(): void
    {
        $placement = $this->placement($this->major('Fintech'), $this->course('STAGE-1'));
        $this->actingAs($this->adminWith([]));

        Livewire::test(PlanTab::class, ['studentId' => $placement->student_id])
            ->assertForbidden();
    }

    public function test_plan_tab_explains_when_no_plan_was_entered(): void
    {
        $placement = $this->placement($this->major('Fintech'), $this->course('STAGE-1'));
        $this->actingAs($this->adminWith(['TrainingPlan View']));

        Livewire::test(PlanTab::class, ['studentId' => $placement->student_id])
            ->assertSee(__('No training plan has been added for this major and course yet.'));
    }

    public function test_placement_scope_shows_only_the_plan_of_that_placement(): void
    {
        $major = $this->major('Fintech');
        $firstCourse = $this->course('STAGE-1');
        $secondCourse = $this->course('STAGE-2');
        $this->plan($major, $firstCourse, 'هدف المرحلة الأولى');
        $this->plan($major, $secondCourse, 'هدف المرحلة الثانية');
        $first = $this->placement($major, $firstCourse);
        $second = $this->placementFor($first->student_id, $secondCourse);
        $this->actingAs($this->adminWith(['TrainingPlan View']));

        Livewire::test(PlanTab::class, ['studentId' => $first->student_id, 'studentCompanyId' => $second->id])
            ->assertSee('هدف المرحلة الثانية')
            ->assertDontSee('هدف المرحلة الأولى');
    }

    public function test_company_supervisor_reads_the_plan_of_a_student_in_their_department(): void
    {
        $major = $this->major('Fintech');
        $course = $this->course('STAGE-1');
        $this->plan($major, $course);
        $placement = $this->placement($major, $course);
        $supervisor = $this->companySupervisor();
        $this->seatSupervisor($supervisor, $placement);
        $this->actingAs($supervisor);

        Livewire::test(PlanTab::class, ['studentId' => $placement->student_id])
            ->assertSee('هدف تجريبي');
    }

    public function test_company_supervisor_cannot_read_the_plan_of_another_company_student(): void
    {
        $major = $this->major('Fintech');
        $course = $this->course('STAGE-1');
        $this->plan($major, $course);
        $placement = $this->placement($major, $course);
        $this->actingAs($this->companySupervisor());

        Livewire::test(PlanTab::class, ['studentId' => $placement->student_id])
            ->assertDontSee('هدف تجريبي');
    }

    public function test_placement_page_shows_the_plan_tab_only_with_the_view_permission(): void
    {
        $placement = $this->placement($this->major('Fintech'), $this->course('STAGE-1'));
        $this->assignCompany($placement);

        $this->actingAs($this->adminWith(['TrainingPlan View']));
        Livewire::test(PlacementDetails::class, ['studentCompany' => $placement->id])
            ->assertSee(__('Training Plan'));

        $this->actingAs($this->adminWith([]));
        Livewire::test(PlacementDetails::class, ['studentCompany' => $placement->id])
            ->assertDontSee(__('Training Plan'));
    }

    public function test_student_page_shows_the_plan_tab_only_with_the_view_permission(): void
    {
        $placement = $this->placement($this->major('Fintech'), $this->course('STAGE-1'));

        $this->actingAs($this->adminWith(['TrainingPlan View']));
        Livewire::test(StudentDetails::class, ['user' => $placement->student_id])
            ->assertSee(__('Training Plan'));

        $this->actingAs($this->adminWith([]));
        Livewire::test(StudentDetails::class, ['user' => $placement->student_id])
            ->assertDontSee(__('Training Plan'));
    }

    /**
     * المسارات مسجلة بدون بادئة لغة في الاختبارات، فإعادة التوجيه إلى /ar تنتهي بـ 404.
     */
    private function withoutLocaleRedirects(): void
    {
        $this->withoutMiddleware([
            \Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect::class,
            \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter::class,
            \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath::class,
        ]);
    }

    private function user(): User
    {
        static $sequence = 0;
        $sequence++;

        return User::query()->forceCreate([
            'name' => "User {$sequence}",
            'email' => "user{$sequence}@example.test",
            'password' => 'secret',
            'email_verified_at' => now(),
        ]);
    }

    private function major(string $name): Major
    {
        return Major::create(['name' => $name, 'created_by' => $this->admin->id]);
    }

    private function course(string $code): Course
    {
        return Course::create([
            'course_code' => $code,
            'hours' => 3,
            'name' => $code,
            'created_by' => $this->admin->id,
        ]);
    }

    private function plan(Major $major, Course $course, string $objective = 'هدف تجريبي'): TrainingPlan
    {
        return TrainingPlan::create([
            'major_id' => $major->id,
            'course_id' => $course->id,
            'objectives' => [$objective],
            'tasks' => ['مهمة تجريبية'],
            'outcomes' => ['مخرج تجريبي'],
            'created_by' => $this->admin->id,
        ]);
    }

    private function placement(?Major $major, Course $course): StudentCompany
    {
        $student = $this->user();

        StudentProfile::query()->forceCreate([
            'user_id' => $student->id,
            'major_id' => $major?->id,
            'student_number' => (string) $student->id,
        ]);

        return $this->placementFor($student->id, $course);
    }

    private function placementFor(int $studentId, Course $course): StudentCompany
    {
        $registration = Registration::create([
            'student_id' => $studentId,
            'course_id' => $course->id,
            'semester' => 1,
            'year' => 2026,
            'created_by' => $this->admin->id,
        ]);

        return StudentCompany::create([
            'registration_id' => $registration->id,
            'student_id' => $studentId,
            'created_by' => $this->admin->id,
        ]);
    }

    private function items(string ...$items): array
    {
        return collect($items)
            ->mapWithKeys(fn (string $item, int $index) => ["item-{$index}" => ['item' => $item]])
            ->all();
    }

    /**
     * صفحة تفاصيل التدريب تقرأ شعار الشركة، فتحتاج شركة حقيقية؛ أما تصنيفها فلا يهم هنا.
     */
    private function assignCompany(StudentCompany $placement): void
    {
        Schema::disableForeignKeyConstraints();

        $company = Company::create([
            'name' => 'Company',
            'company_category_id' => 1,
            'created_by' => $this->admin->id,
        ]);

        Schema::enableForeignKeyConstraints();

        $placement->update(['company_id' => $company->id]);
    }

    private function companySupervisor(): User
    {
        $supervisor = $this->userWith(['TrainingPlan View']);
        $supervisor->assignRole(Role::findOrCreate(UserRole::COMPANY_SUPERVISOR->value, 'web'));

        return $supervisor;
    }

    /**
     * مقعد مشرف الشركة: الفرع والقسم نفسهما هما ما يربطه بالطالب، ولا يهم هنا سوى تطابقهما.
     */
    private function seatSupervisor(User $supervisor, StudentCompany $placement): void
    {
        Schema::disableForeignKeyConstraints();

        $placement->forceFill(['branch_id' => 1, 'department_id' => 1])->save();

        DB::table(config('ppuds.table_prefix').'branch_department')->insert([
            'branch_id' => 1,
            'company_department_id' => 1,
            'user_id' => $supervisor->id,
        ]);

        Schema::enableForeignKeyConstraints();
    }

    private function userWith(array $permissions): User
    {
        $user = $this->user();
        $user->givePermissionTo($this->permissions($permissions));

        return $user;
    }

    private function adminWith(array $permissions): User
    {
        $user = $this->userWith($permissions);
        $user->assignRole(Role::findOrCreate(UserRole::ADMIN->value, 'web'));

        return $user;
    }

    private function permissions(array $names): array
    {
        return array_map(
            fn (string $name) => Permission::findOrCreate($name, 'web'),
            $names
        );
    }
}
