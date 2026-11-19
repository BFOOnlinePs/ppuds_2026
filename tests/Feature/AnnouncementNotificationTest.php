<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Entities\User;
use Modules\Core\Notifications\GeneralNotification;
use Modules\Core\Settings\GeneralSettings as CoreGeneralSettings;
use Modules\PPUDS\Entities\Announcement;
use Modules\PPUDS\Jobs\SendAnnouncementNotification;
use Modules\PPUDS\Services\PpudsNotificationService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\BuildsAuthenticationTables;
use Tests\TestCase;

class AnnouncementNotificationTest extends TestCase
{
    use BuildsAuthenticationTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessSqliteMemory();
        $this->buildAuthenticationTables();
        $this->fakeAuthenticationSettings();
        $this->buildAnnouncementTables();

        CoreGeneralSettings::fake([
            'site_name' => 'PPUDS',
            'email_address_for_contact' => 'contact@example.test',
            'site_description' => '',
            'site_logo_url' => 'https://example.test/logo.png',
        ], false);
        config(['activitylog.enabled' => false]);
    }

    private function buildAnnouncementTables(): void
    {
        $prefix = config('ppuds.table_prefix');

        Schema::create($prefix.'announcements', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('announcement_category_id')->nullable();
            $table->json('target_roles')->nullable();
            $table->json('filters')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create($prefix.'announcement_translations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('announcement_id');
            $table->string('locale');
            $table->string('name');
            $table->text('content');
            $table->timestamps();
        });

        Schema::create($prefix.'announcement_categories', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });

        Schema::create($prefix.'announcement_category_translations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('announcement_category_id');
            $table->string('locale');
            $table->string('name');
            $table->timestamps();
        });

        Schema::create($prefix.'student_profiles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('major_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        (require base_path('Modules/PPUDS/database/migrations/2026_11_20_000001_add_notified_at_to_announcements_table.php'))->up();
    }

    private function makeAnnouncement(array $attributes = []): Announcement
    {
        return Announcement::create(array_merge([
            'name' => 'Career fair',
            'content' => 'The career fair opens on Monday.',
            'target_roles' => ['Student'],
            'published_at' => now()->subMinute(),
        ], $attributes));
    }

    private function makeStudent(string $email, ?int $majorId = null): User
    {
        $student = $this->makeUser($email, ['Student']);
        $student->studentProfile()->create(['major_id' => $majorId]);

        return $student;
    }

    private function grantPermissions(User $user, array $permissions): void
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $user->givePermissionTo($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_a_published_announcement_is_queued_once(): void
    {
        Queue::fake();
        $service = app(PpudsNotificationService::class);
        $announcement = $this->makeAnnouncement();

        $service->announcementCreated($announcement);
        $service->announcementCreated($announcement);
        $this->assertSame(0, $service->dispatchDueAnnouncementNotifications());

        Queue::assertPushed(SendAnnouncementNotification::class, 1);
        Queue::assertPushed(fn (SendAnnouncementNotification $job) => $job->announcementId === $announcement->id);
        $this->assertNotNull($announcement->fresh()->notified_at);
    }

    public function test_a_scheduled_announcement_waits_for_its_publish_time(): void
    {
        Queue::fake();
        $service = app(PpudsNotificationService::class);
        $announcement = $this->makeAnnouncement(['published_at' => now()->addHour()]);

        $service->announcementCreated($announcement);
        $this->assertSame(0, $service->dispatchDueAnnouncementNotifications());
        Queue::assertNothingPushed();

        $this->travel(61)->minutes();

        $this->assertSame(1, $service->dispatchDueAnnouncementNotifications());
        $this->assertSame(0, $service->dispatchDueAnnouncementNotifications());
        Queue::assertPushed(SendAnnouncementNotification::class, 1);
    }

    public function test_an_expired_announcement_is_never_notified(): void
    {
        Queue::fake();
        $service = app(PpudsNotificationService::class);
        $announcement = $this->makeAnnouncement([
            'published_at' => now()->subDays(2),
            'expires_at' => now()->subDay(),
        ]);

        $service->announcementCreated($announcement);
        $service->dispatchDueAnnouncementNotifications();

        Queue::assertNothingPushed();
        $this->assertNull($announcement->fresh()->notified_at);
    }

    public function test_the_job_notifies_only_the_targeted_users(): void
    {
        Notification::fake();

        $engineering = $this->makeStudent('eng@example.com', majorId: 7);
        $nursing = $this->makeStudent('nurse@example.com', majorId: 9);
        $supervisor = $this->makeUser('sup@example.com', ['Company Supervisor']);
        $evaluator = $this->makeUser('eval@example.com', ['Company Supervisor', 'Evaluation Supervisor']);

        $announcement = $this->makeAnnouncement([
            'target_roles' => ['Student', 'Company Supervisor'],
            'filters' => ['major_id' => 7],
        ]);

        (new SendAnnouncementNotification($announcement->id))->handle(app(PpudsNotificationService::class));

        Notification::assertSentTo($engineering, GeneralNotification::class, fn (GeneralNotification $n) => $n->title === 'Career fair'
            && $n->message === 'The career fair opens on Monday.');
        Notification::assertSentTo($supervisor, GeneralNotification::class);
        Notification::assertNotSentTo($nursing, GeneralNotification::class);
        Notification::assertNotSentTo($evaluator, GeneralNotification::class);
    }

    public function test_the_api_lists_only_announcements_aimed_at_the_user(): void
    {
        $student = $this->makeStudent('student@example.com', majorId: 7);
        $this->grantPermissions($student, ['Announcement View List', 'Announcement Details']);

        $forAllStudents = $this->makeAnnouncement(['name' => 'All students']);
        $forMyMajor = $this->makeAnnouncement(['name' => 'My major', 'filters' => ['major_id' => 7]]);
        $forOtherMajor = $this->makeAnnouncement(['name' => 'Other major', 'filters' => ['major_id' => 9]]);
        $forSupervisors = $this->makeAnnouncement(['name' => 'Supervisors', 'target_roles' => ['Company Supervisor']]);
        $scheduled = $this->makeAnnouncement(['name' => 'Scheduled', 'published_at' => now()->addDay()]);
        $expired = $this->makeAnnouncement(['name' => 'Expired', 'expires_at' => now()->subSecond()]);

        Sanctum::actingAs($student);

        $ids = collect($this->getJson('/api/v1/ppuds/announcements')->assertOk()->json('data'))->pluck('id')->sort()->values()->all();
        $this->assertSame([$forAllStudents->id, $forMyMajor->id], $ids);

        $this->getJson("/api/v1/ppuds/announcements/{$forMyMajor->id}")->assertOk();
        foreach ([$forOtherMajor, $forSupervisors, $scheduled, $expired] as $hidden) {
            $this->getJson("/api/v1/ppuds/announcements/{$hidden->id}")->assertNotFound();
        }
    }

    public function test_an_announcement_manager_still_sees_every_announcement(): void
    {
        $admin = $this->makeUser('admin@example.com', ['Admin']);
        $this->grantPermissions($admin, ['Announcement View List', 'Announcement Create']);

        $this->makeAnnouncement();
        $this->makeAnnouncement(['target_roles' => ['Company Supervisor']]);
        $this->makeAnnouncement(['published_at' => now()->addDay()]);

        Sanctum::actingAs($admin);

        $this->assertCount(3, $this->getJson('/api/v1/ppuds/announcements')->assertOk()->json('data'));
    }
}
