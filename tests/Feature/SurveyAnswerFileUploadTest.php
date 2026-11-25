<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\PPUDS\Entities\Survey;
use Modules\PPUDS\Entities\SurveyAnswer;
use Modules\PPUDS\Entities\SurveyQuestion;
use Modules\PPUDS\Enums\SurveyQuestionType;
use Modules\PPUDS\Http\Controllers\SurveyAnswerFileController;
use Modules\PPUDS\Livewire\Pages\Survey\SurveyForm;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\BuildsAuthenticationTables;
use Tests\TestCase;

class SurveyAnswerFileUploadTest extends TestCase
{
    use BuildsAuthenticationTables;

    private Survey $survey;

    private SurveyQuestion $fileQuestion;

    private SurveyQuestion $textQuestion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessSqliteMemory();
        $this->buildAuthenticationTables();
        $this->fakeAuthenticationSettings();
        $this->buildSurveyTables();

        config(['activitylog.enabled' => false]);
        Storage::fake('local');
        Storage::fake('public');

        $this->survey = new Survey;
        $this->survey->forceFill(['is_active' => true])->saveQuietly();

        $this->fileQuestion = $this->makeQuestion($this->survey, SurveyQuestionType::FILE, required: false, sort: 1);
        $this->textQuestion = $this->makeQuestion($this->survey, SurveyQuestionType::TEXT, required: false, sort: 2);
    }

    private function buildSurveyTables(): void
    {
        $prefix = config('ppuds.table_prefix');

        Schema::create($prefix.'surveys', function (Blueprint $table): void {
            $table->id();
            $table->string('serve_group')->nullable();
            $table->unsignedBigInteger('major_id')->nullable();
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('semester')->nullable();
            $table->integer('year')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create($prefix.'survey_translations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('survey_id');
            $table->string('locale');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
        });

        Schema::create($prefix.'survey_questions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('survey_id');
            $table->unsignedTinyInteger('type');
            $table->boolean('is_required')->default(false);
            $table->integer('sort_order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create($prefix.'survey_question_translations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('survey_question_id');
            $table->string('locale');
            $table->text('content')->nullable();
        });

        Schema::create($prefix.'survey_question_options', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('survey_question_id');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create($prefix.'survey_question_option_translations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('survey_question_option_id');
            $table->string('locale');
            $table->string('text')->nullable();
        });

        Schema::create($prefix.'survey_answers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('survey_id');
            $table->unsignedBigInteger('survey_question_id');
            $table->text('text_answer')->nullable();
            $table->unsignedBigInteger('selected_option_id')->nullable();
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->unsignedBigInteger('student_company_id')->nullable();
            $table->unsignedBigInteger('evaluated_student_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    private function makeQuestion(Survey $survey, SurveyQuestionType $type, bool $required, int $sort): SurveyQuestion
    {
        $question = new SurveyQuestion;
        $question->forceFill([
            'survey_id' => $survey->id,
            'type' => $type->value,
            'is_required' => $required,
            'sort_order' => $sort,
        ])->saveQuietly();

        DB::table(config('ppuds.table_prefix').'survey_question_translations')->insert([
            'survey_question_id' => $question->id,
            'locale' => app()->getLocale(),
            'content' => $type->name.' question',
        ]);

        return $question->fresh();
    }

    private function student(string $email = 'student@example.test'): User
    {
        return $this->makeUser($email, [UserRole::STUDENT->value]);
    }

    private function submit(array $answers, ?int $surveyId = null)
    {
        return $this->post(route('api.api.v1.ppuds.survey-answers.store'), [
            'survey_id' => $surveyId ?? $this->survey->id,
            'answers' => $answers,
        ], ['Accept' => 'application/json']);
    }

    private function pdf(string $name = 'cv.pdf', int $kb = 50): UploadedFile
    {
        return UploadedFile::fake()->create($name, $kb, 'application/pdf');
    }

    private function openAs(User $user, SurveyAnswer $answer)
    {
        $this->actingAs($user);

        return app(SurveyAnswerFileController::class)($answer);
    }

    private function assertHttpError(int $status, callable $callback): void
    {
        try {
            $callback();
            $this->fail("Expected HTTP {$status}");
        } catch (HttpException $e) {
            $this->assertSame($status, $e->getStatusCode());
        }
    }

    private function assertNothingStored(): void
    {
        $this->assertSame(0, SurveyAnswer::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles('surveys'));
    }

    // ───────────── API: successful upload ─────────────

    public function test_api_stores_uploaded_file_privately_with_original_name(): void
    {
        Sanctum::actingAs($this->student());

        $this->submit([['question_id' => $this->fileQuestion->id, 'file' => $this->pdf('Malak CV.pdf')]])
            ->assertCreated();

        $answer = SurveyAnswer::query()->sole();

        $this->assertMatchesRegularExpression('#^surveys/[0-9a-f-]{36}/Malak CV\.pdf$#', $answer->text_answer);
        Storage::disk('local')->assertExists($answer->text_answer);
        Storage::disk('public')->assertMissing($answer->text_answer);
    }

    public function test_api_accepts_the_file_in_value_too(): void
    {
        Sanctum::actingAs($this->student());

        $this->submit([['question_id' => $this->fileQuestion->id, 'value' => UploadedFile::fake()->create('report.docx', 50)]])
            ->assertCreated();

        Storage::disk('local')->assertExists(SurveyAnswer::query()->sole()->text_answer);
    }

    public function test_api_sanitizes_dangerous_file_names(): void
    {
        Sanctum::actingAs($this->student());

        $this->submit([['question_id' => $this->fileQuestion->id, 'file' => $this->pdf('../../<x>cv.pdf')]])
            ->assertCreated();

        $path = SurveyAnswer::query()->sole()->text_answer;

        $this->assertStringNotContainsString('..', $path);
        $this->assertStringNotContainsString('<', $path);
        $this->assertCount(3, explode('/', $path));
    }

    // ───────────── API: missing file ─────────────

    public function test_api_rejects_required_file_question_without_file(): void
    {
        $this->fileQuestion->forceFill(['is_required' => true])->saveQuietly();
        Sanctum::actingAs($this->student());

        $this->submit([['question_id' => $this->textQuestion->id, 'value' => 'hello']])
            ->assertStatus(422)
            ->assertJsonPath('status', false);

        $this->assertNothingStored();
    }

    public function test_api_rejects_required_file_question_sent_empty(): void
    {
        $this->fileQuestion->forceFill(['is_required' => true])->saveQuietly();
        Sanctum::actingAs($this->student());

        $this->submit([['question_id' => $this->fileQuestion->id, 'value' => '']])
            ->assertStatus(422);

        $this->assertNothingStored();
    }

    public function test_api_allows_optional_file_question_without_file(): void
    {
        Sanctum::actingAs($this->student());

        $this->submit([
            ['question_id' => $this->fileQuestion->id, 'value' => null],
            ['question_id' => $this->textQuestion->id, 'value' => 'hello'],
        ])->assertCreated();

        $answer = SurveyAnswer::query()->sole();
        $this->assertSame($this->textQuestion->id, (int) $answer->survey_question_id);
        $this->assertSame('hello', $answer->text_answer);
    }

    public function test_api_rejects_device_path_instead_of_file(): void
    {
        Sanctum::actingAs($this->student());

        $this->submit([['question_id' => $this->fileQuestion->id, 'value' => '/private/var/mobile/Containers/Data/Application/X/tmp/CV.pdf']])
            ->assertStatus(422);

        $this->assertNothingStored();
    }

    public function test_api_rejects_server_path_text_instead_of_file(): void
    {
        Sanctum::actingAs($this->student());

        $this->submit([['question_id' => $this->fileQuestion->id, 'value' => 'ppuds/student_payments/receipt.jpg']])
            ->assertStatus(422);

        $this->assertNothingStored();
    }

    // ───────────── API: invalid file ─────────────

    public function test_api_rejects_disallowed_file_type(): void
    {
        Sanctum::actingAs($this->student());

        $this->submit([['question_id' => $this->fileQuestion->id, 'file' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')]])
            ->assertStatus(422);

        $this->assertNothingStored();
    }

    public function test_api_rejects_html_disguised_as_pdf(): void
    {
        Sanctum::actingAs($this->student());

        // ملف حقيقي (لا fake) حتى يُفحص نوعه من محتواه كما يحدث على الخادم، لا من اسمه.
        $path = tempnam(sys_get_temp_dir(), 'survey');
        file_put_contents($path, '<!DOCTYPE html><html><body><script>alert(1)</script></body></html>');
        $file = new UploadedFile($path, 'cv.pdf', null, null, true);

        $this->submit([['question_id' => $this->fileQuestion->id, 'file' => $file]])
            ->assertStatus(422);

        $this->assertNothingStored();
    }

    public function test_api_rejects_file_larger_than_limit(): void
    {
        Sanctum::actingAs($this->student());

        $this->submit([['question_id' => $this->fileQuestion->id, 'file' => $this->pdf('big.pdf', 20481)]])
            ->assertStatus(422);

        $this->assertNothingStored();
    }

    public function test_api_rejects_more_than_one_file_for_the_same_question(): void
    {
        Sanctum::actingAs($this->student());

        $this->submit([
            ['question_id' => $this->fileQuestion->id, 'file' => $this->pdf('a.pdf')],
            ['question_id' => $this->fileQuestion->id, 'file' => $this->pdf('b.pdf')],
        ])->assertStatus(422);

        $this->assertNothingStored();
    }

    public function test_api_rejects_file_array_for_one_answer(): void
    {
        Sanctum::actingAs($this->student());

        $this->submit([['question_id' => $this->fileQuestion->id, 'file' => [$this->pdf('a.pdf'), $this->pdf('b.pdf')]]])
            ->assertStatus(422);

        $this->assertNothingStored();
    }

    public function test_api_rejects_answers_for_questions_of_another_survey(): void
    {
        $otherSurvey = new Survey;
        $otherSurvey->forceFill(['is_active' => true])->saveQuietly();
        $foreignQuestion = $this->makeQuestion($otherSurvey, SurveyQuestionType::TEXT, required: false, sort: 1);

        Sanctum::actingAs($this->student());

        $this->submit([['question_id' => $foreignQuestion->id, 'value' => 'x']])
            ->assertStatus(422);

        $this->assertNothingStored();
    }

    public function test_api_requires_authentication(): void
    {
        $this->submit([['question_id' => $this->fileQuestion->id, 'file' => $this->pdf()]])
            ->assertUnauthorized();

        $this->assertNothingStored();
    }

    // ───────────── Opening the file ─────────────

    public function test_admin_and_owner_can_open_the_file_but_other_students_cannot(): void
    {
        $owner = $this->student();
        Sanctum::actingAs($owner);
        $this->submit([['question_id' => $this->fileQuestion->id, 'file' => $this->pdf()]])->assertCreated();
        $answer = SurveyAnswer::query()->sole();

        $admin = $this->makeUser('admin@example.test', [UserRole::ADMIN->value]);
        $this->assertSame(200, $this->openAs($admin, $answer)->getStatusCode());
        $this->assertSame(200, $this->openAs($owner, $answer)->getStatusCode());

        $other = $this->student('other@example.test');
        $this->assertHttpError(403, fn () => $this->openAs($other, $answer));
    }

    public function test_opened_file_has_safe_headers(): void
    {
        Storage::disk('local')->put('surveys/a/cv.pdf', '%PDF-1.4');
        Storage::disk('local')->put('surveys/a/page.html', '<script>alert(1)</script>');

        $admin = $this->makeUser('admin@example.test', [UserRole::ADMIN->value]);

        $pdf = $this->openAs($admin, $this->storedAnswer('surveys/a/cv.pdf'));
        $this->assertSame('nosniff', $pdf->headers->get('X-Content-Type-Options'));
        $this->assertStringStartsWith('inline', $pdf->headers->get('Content-Disposition'));

        // ملف غير آمن للعرض يُنزَّل ولا يُفتح كصفحة.
        $html = $this->openAs($admin, $this->storedAnswer('surveys/a/page.html'));
        $this->assertStringStartsWith('attachment', $html->headers->get('Content-Disposition'));
    }

    public function test_old_web_submissions_on_public_disk_still_open(): void
    {
        Storage::disk('public')->put('surveys/01OLDFILE.pdf', '%PDF-1.4');

        $admin = $this->makeUser('admin@example.test', [UserRole::ADMIN->value]);

        $this->assertSame(200, $this->openAs($admin, $this->storedAnswer('surveys/01OLDFILE.pdf'))->getStatusCode());
    }

    public function test_stored_paths_outside_survey_folder_cannot_be_opened(): void
    {
        Storage::disk('public')->put('ppuds/student_payments/receipt.jpg', 'secret');
        Storage::disk('local')->put('secret.txt', 'secret');

        $admin = $this->makeUser('admin@example.test', [UserRole::ADMIN->value]);

        foreach ([
            'ppuds/student_payments/receipt.jpg',
            '/storage/ppuds/student_payments/receipt.jpg',
            'surveys/../ppuds/student_payments/receipt.jpg',
            'surveys/../secret.txt',
            'https://evil.example/cv.pdf',
            '/private/var/mobile/Containers/Data/Application/X/tmp/CV.pdf',
        ] as $value) {
            $this->assertNull(SurveyAnswerFileController::locate($value), $value);
            $this->assertHttpError(404, fn () => $this->openAs($admin, $this->storedAnswer($value)));
        }
    }

    public function test_non_file_question_answers_cannot_be_opened(): void
    {
        Storage::disk('local')->put('surveys/a/cv.pdf', '%PDF-1.4');

        $answer = SurveyAnswer::query()->forceCreate([
            'survey_id' => $this->survey->id,
            'survey_question_id' => $this->textQuestion->id,
            'text_answer' => 'surveys/a/cv.pdf',
            'submitted_by' => $this->student()->id,
        ]);

        $admin = $this->makeUser('admin@example.test', [UserRole::ADMIN->value]);

        $this->assertHttpError(404, fn () => $this->openAs($admin, $answer));
    }

    private function storedAnswer(string $value): SurveyAnswer
    {
        return SurveyAnswer::query()->forceCreate([
            'survey_id' => $this->survey->id,
            'survey_question_id' => $this->fileQuestion->id,
            'text_answer' => $value,
            'submitted_by' => User::query()->value('id'),
        ]);
    }

    // ───────────── Web form ─────────────

    private function webStudent(): User
    {
        $student = $this->student('web@example.test');
        $student->givePermissionTo(Permission::findOrCreate('Survey Submit', 'web'));
        $this->actingAs($student);

        return $student;
    }

    public function test_web_form_requires_file_for_required_question(): void
    {
        $this->fileQuestion->forceFill(['is_required' => true])->saveQuietly();
        $this->webStudent();

        Livewire::test(SurveyForm::class, ['survey' => $this->survey->fresh()])
            ->call('submit')
            ->assertHasErrors(['data.question_'.$this->fileQuestion->id => 'required']);

        $this->assertNothingStored();
    }

    public function test_web_form_stores_file_privately(): void
    {
        $this->webStudent();

        Livewire::test(SurveyForm::class, ['survey' => $this->survey->fresh()])
            ->set('data.question_'.$this->fileQuestion->id, [$this->pdf()])
            ->call('submit')
            ->assertHasNoErrors();

        $answer = SurveyAnswer::query()->sole();

        $this->assertStringStartsWith('surveys/', $answer->text_answer);
        Storage::disk('local')->assertExists($answer->text_answer);
        Storage::disk('public')->assertMissing($answer->text_answer);
    }

    public function test_web_form_rejects_disallowed_file_type(): void
    {
        $this->webStudent();

        Livewire::test(SurveyForm::class, ['survey' => $this->survey->fresh()])
            ->set('data.question_'.$this->fileQuestion->id, [UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')])
            ->call('submit')
            ->assertHasErrors(['data.question_'.$this->fileQuestion->id]);

        $this->assertSame(0, SurveyAnswer::query()->count());
    }
}
