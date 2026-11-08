<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Entities\User;
use Modules\PPUDS\Entities\Course;
use Modules\PPUDS\Entities\Registration;
use Modules\PPUDS\Entities\RegistrationSubject;
use Modules\PPUDS\Livewire\Pages\Student\Details\Registration\Index;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RegistrationSubjectTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        // نفس شرط TrainingPlanTest: migrations المشروع لا تعمل على SQLite.
        // DB_CONNECTION=mysql DB_DATABASE=ppuds_2026_test vendor/bin/phpunit --filter RegistrationSubjectTest
        if (! str_ends_with((string) getenv('DB_DATABASE'), '_test')) {
            $this->markTestSkipped('Requires a dedicated MySQL test database.');
        }

        parent::setUp();

        app()->setLocale('ar');

        $this->admin = $this->user();
    }

    public function test_user_with_create_permission_adds_subjects_to_a_registration(): void
    {
        $this->actingAs($this->userWith(['RegistrationSubject View', 'RegistrationSubject Create']));
        $registration = $this->registration();

        $this->callSubjects($registration, [
            ['name' => 'برمجة 1', 'code' => 'CS101'],
            ['name' => 'قواعد بيانات', 'code' => null],
        ]);

        $this->assertSame(
            ['برمجة 1', 'قواعد بيانات'],
            $registration->subjects()->pluck('name')->all()
        );
        $this->assertSame('CS101', $registration->subjects()->first()->code);
    }

    public function test_subjects_belong_only_to_their_registration(): void
    {
        $this->actingAs($this->userWith(['RegistrationSubject View', 'RegistrationSubject Create']));
        $first = $this->registration();
        $second = $this->registrationFor($first->student_id);

        $this->callSubjects($second, [['name' => 'شبكات', 'code' => 'NET']]);

        $this->assertSame(0, $first->subjects()->count());
        $this->assertSame(['شبكات'], $second->subjects()->pluck('name')->all());
    }

    public function test_user_with_update_and_delete_permissions_edits_and_removes_subjects(): void
    {
        $this->actingAs($this->userWith([
            'RegistrationSubject View',
            'RegistrationSubject Update',
            'RegistrationSubject Delete',
        ]));
        $registration = $this->registration();
        $kept = $this->subject($registration, 'قديم');
        $removed = $this->subject($registration, 'محذوف');

        $this->callSubjects($registration, [
            ['id' => $kept->id, 'name' => 'معدل', 'code' => 'X1'],
        ]);

        $this->assertSame('معدل', $kept->fresh()->name);
        $this->assertSoftDeleted($removed);
    }

    public function test_view_only_user_cannot_change_subjects_even_with_a_crafted_payload(): void
    {
        $this->actingAs($this->userWith(['RegistrationSubject View']));
        $registration = $this->registration();
        $subject = $this->subject($registration, 'أصلي');

        $this->callSubjects($registration, [
            ['id' => $subject->id, 'name' => 'مغير', 'code' => null],
            ['name' => 'جديد', 'code' => null],
        ]);

        $this->assertSame(['أصلي'], $registration->subjects()->pluck('name')->all());
    }

    public function test_subject_of_another_registration_cannot_be_changed_through_this_one(): void
    {
        $this->actingAs($this->userWith([
            'RegistrationSubject View',
            'RegistrationSubject Update',
            'RegistrationSubject Delete',
        ]));
        $registration = $this->registration();
        $foreign = $this->subject($this->registration(), 'لطالب آخر');

        $this->callSubjects($registration, [
            ['id' => $foreign->id, 'name' => 'اختراق', 'code' => null],
        ]);

        $this->assertSame('لطالب آخر', $foreign->fresh()->name);
    }

    public function test_subjects_action_is_hidden_without_the_view_permission(): void
    {
        $this->actingAs($this->userWith([]));
        $registration = $this->registration();

        Livewire::test(Index::class, ['studentId' => $registration->student_id])
            ->assertTableActionHidden('subjects', $registration);
    }

    private function callSubjects(Registration $registration, array $subjects): void
    {
        Livewire::test(Index::class, ['studentId' => $registration->student_id])
            ->mountTableAction('subjects', $registration)
            ->set('mountedTableActionsData.0.subjects', collect($subjects)
                ->mapWithKeys(fn (array $subject, int $index) => ["item-{$index}" => $subject])
                ->all())
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();
    }

    private function user(): User
    {
        static $sequence = 0;
        $sequence++;

        return User::query()->forceCreate([
            'name' => "Subject User {$sequence}",
            'email' => "subject-user{$sequence}@example.test",
            'password' => 'secret',
            'email_verified_at' => now(),
        ]);
    }

    private function registration(): Registration
    {
        return $this->registrationFor($this->user()->id);
    }

    private function registrationFor(int $studentId): Registration
    {
        static $sequence = 0;
        $sequence++;

        $course = Course::create([
            'course_code' => "STAGE-{$sequence}",
            'hours' => 3,
            'name' => "STAGE-{$sequence}",
            'created_by' => $this->admin->id,
        ]);

        return Registration::create([
            'student_id' => $studentId,
            'course_id' => $course->id,
            'semester' => 1,
            'year' => 2026,
            'created_by' => $this->admin->id,
        ]);
    }

    private function subject(Registration $registration, string $name): RegistrationSubject
    {
        return $registration->subjects()->create([
            'name' => $name,
            'created_by' => $this->admin->id,
        ]);
    }

    private function userWith(array $permissions): User
    {
        $user = $this->user();
        $user->givePermissionTo(array_map(
            fn (string $name) => Permission::findOrCreate($name, 'web'),
            $permissions
        ));

        return $user;
    }
}
