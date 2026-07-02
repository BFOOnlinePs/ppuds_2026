<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\PPUDS\Entities\LeaveRequest;
use Modules\PPUDS\Entities\Registration;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Transformers\V1\LeaveRequestResource;
use Spatie\QueryBuilder\QueryBuilder;
use Tests\TestCase;

class LeaveRequestSupervisorFilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            $this->markTestSkipped('This test builds temporary SQLite tables and only runs against :memory:.');
        }

        $this->createTables();
        $this->seedLeaveRequests();
    }

    public function test_supervisor_id_filter_matches_the_student_registration_supervisor(): void
    {
        request()->query->replace([
            'filter' => ['supervisor_id' => '1296'],
        ]);

        $ids = QueryBuilder::for(LeaveRequest::query())
            ->allowedFilters(LeaveRequestResource::allowedFilters())
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $this->assertSame([10], $ids);
    }

    public function test_supervisor_id_filter_supports_multiple_supervisors(): void
    {
        request()->query->replace([
            'filter' => ['supervisor_id' => '1296,2048'],
        ]);

        $ids = QueryBuilder::for(LeaveRequest::query())
            ->allowedFilters(LeaveRequestResource::allowedFilters())
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $this->assertSame([10, 20], $ids);
    }

    private function createTables(): void
    {
        Schema::dropIfExists((new LeaveRequest)->getTable());
        Schema::dropIfExists((new StudentCompany)->getTable());
        Schema::dropIfExists((new Registration)->getTable());

        Schema::create((new Registration)->getTable(), function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('student_id')->nullable();
            $table->unsignedBigInteger('supervisor_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create((new StudentCompany)->getTable(), function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('registration_id');
            $table->unsignedBigInteger('student_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create((new LeaveRequest)->getTable(), function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('student_company_id');
            $table->integer('type')->nullable();
            $table->dateTime('start_at')->nullable();
            $table->dateTime('end_at')->nullable();
            $table->text('reason')->nullable();
            $table->string('company_approval')->nullable();
            $table->string('university_approval')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    private function seedLeaveRequests(): void
    {
        $now = now();

        DB::table((new Registration)->getTable())->insert([
            [
                'id' => 1,
                'student_id' => 101,
                'supervisor_id' => 1296,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 2,
                'student_id' => 102,
                'supervisor_id' => 2048,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table((new StudentCompany)->getTable())->insert([
            [
                'id' => 1,
                'registration_id' => 1,
                'student_id' => 101,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 2,
                'registration_id' => 2,
                'student_id' => 102,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table((new LeaveRequest)->getTable())->insert([
            [
                'id' => 10,
                'student_company_id' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 20,
                'student_company_id' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
