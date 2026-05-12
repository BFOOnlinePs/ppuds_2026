<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Entities\User;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\Course;
use Modules\PPUDS\Entities\Registration;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Entities\StudentProfile;
use Modules\PPUDS\Enums\TrainingStatus;
use Modules\PPUDS\Settings\GeneralSettings;

class MigrateStudentCompanyLinks extends Command
{
    protected $signature = 'migrate:student-companies
        {--table= : Old database table that contains student/company links.}
        {--student-column= : Old table column for student number or old user id.}
        {--company-column= : Old table column for old company id.}
        {--year= : Academic year to link registrations for. Defaults to PPUDS settings year.}
        {--semester= : Semester to link registrations for. Defaults to PPUDS settings semester.}
        {--update-existing : Update existing student-company records instead of skipping them.}
        {--dry-run : Preview the import without writing anything.}';

    protected $description = 'Link current student registrations to companies using placement data from the old database.';

    private const OLD_TABLE_CANDIDATES = [
        'students_companies',
        'student_companies',
        'student_company',
        'companies_students',
        'company_students',
        'company_student',
        'students_training',
        'student_training',
        'training_students',
        'student_company_training',
    ];

    private const STUDENT_COLUMN_CANDIDATES = [
        'student_number',
        'student_no',
        'studentNo',
        'st_no',
        'u_username',
        'username',
        'student_id',
        'user_id',
        'u_id',
        's_id',
        'sc_student_id',
        'sc_user_id',
        'student_user_id',
        'studentID',
    ];

    private const OLD_USER_ID_COLUMNS = [
        'student_id',
        'user_id',
        'u_id',
        's_id',
        'sc_student_id',
        'sc_user_id',
        'student_user_id',
        'studentID',
    ];

    private const COMPANY_COLUMN_CANDIDATES = [
        'company_id',
        'c_id',
        'sc_company_id',
        'companyId',
        'companyID',
        'training_company_id',
        'company',
    ];

    private const STATUS_COLUMN_CANDIDATES = [
        'status',
        'sc_status',
        'training_status',
        'student_company_status',
    ];

    private const REGISTRATION_COLUMN_CANDIDATES = [
        'sc_registration_id',
        'registration_id',
        'student_registration_id',
        'r_id',
    ];

    private array $studentProfileCache = [];

    private array $oldUserCache = [];

    private array $companyCache = [];

    private array $registrationCache = [];

    private array $oldRegistrationCache = [];

    private array $oldCourseCache = [];

    private array $companyPlacementCache = [];

    private ?bool $companiesHaveOldCompanyIdColumn = null;

    private ?array $oldUsersColumns = null;

    public function handle(): int
    {
        [$year, $semester] = $this->targetPeriod();

        try {
            $oldTable = $this->resolveOldTable();
            $columns = Schema::connection('old_db')->getColumnListing($oldTable);
        } catch (\Throwable $e) {
            $this->error('Could not read the old database schema: '.$e->getMessage());

            return self::FAILURE;
        }

        $studentColumn = $this->resolveColumn('student-column', $columns, self::STUDENT_COLUMN_CANDIDATES);
        $companyColumn = $this->resolveColumn('company-column', $columns, self::COMPANY_COLUMN_CANDIDATES);
        $statusColumn = $this->firstExistingColumn($columns, self::STATUS_COLUMN_CANDIDATES);
        $oldRegistrationColumn = $this->firstExistingColumn($columns, self::REGISTRATION_COLUMN_CANDIDATES);

        if (! $studentColumn || ! $companyColumn) {
            $this->error('Could not detect the required old table columns.');
            $this->line('Detected table: '.$oldTable);
            $this->line('Available columns: '.implode(', ', $columns));
            $this->line('Pass --student-column=... and --company-column=... explicitly.');

            return self::FAILURE;
        }

        $query = DB::connection('old_db')
            ->table($oldTable)
            ->select($this->selectColumns($studentColumn, $companyColumn, $statusColumn, $oldRegistrationColumn));

        if ($this->hasColumn($columns, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        $total = (clone $query)->count();

        $this->info("Linking students to companies from old_db.{$oldTable}");
        $this->line("Student column: {$studentColumn}");
        $this->line("Company column: {$companyColumn}");
        $this->line('Registration column: '.($oldRegistrationColumn ?: 'not detected'));
        $this->line('Target year/semester: '.$year.' / '.$semester);

        if ($this->option('dry-run')) {
            $this->warn('Dry run enabled: no records will be written.');
        }

        if ($total === 0) {
            $this->warn('No rows found in the old table.');

            return self::SUCCESS;
        }

        $stats = [
            'created' => 0,
            'updated' => 0,
            'skipped_existing' => 0,
            'missing_student' => 0,
            'missing_company' => 0,
            'missing_registration' => 0,
            'incomplete' => 0,
        ];

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query
            ->orderBy($this->orderColumn($columns, $studentColumn))
            ->chunk(500, function ($rows) use (&$stats, $bar, $studentColumn, $companyColumn, $statusColumn, $oldRegistrationColumn, $year, $semester) {
                foreach ($rows as $oldLink) {
                    $result = $this->importOldLink($oldLink, $studentColumn, $companyColumn, $statusColumn, $oldRegistrationColumn, $year, $semester);
                    $stats[$result]++;
                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine(2);

        $this->info(sprintf(
            'Done. Created: %d, Updated: %d, Existing skipped: %d, Missing students: %d, Missing companies: %d, Missing registrations: %d, Incomplete rows: %d',
            $stats['created'],
            $stats['updated'],
            $stats['skipped_existing'],
            $stats['missing_student'],
            $stats['missing_company'],
            $stats['missing_registration'],
            $stats['incomplete'],
        ));

        return self::SUCCESS;
    }

    private function targetPeriod(): array
    {
        $year = $this->option('year');
        $semester = $this->option('semester');

        if ($year && $semester) {
            return [(int) $year, (int) $semester];
        }

        $settings = app(GeneralSettings::class);

        return [
            (int) ($year ?: $settings->year),
            (int) ($semester ?: $settings->semester_type->value),
        ];
    }

    private function importOldLink(
        object $oldLink,
        string $studentColumn,
        string $companyColumn,
        ?string $statusColumn,
        ?string $oldRegistrationColumn,
        int $year,
        int $semester,
    ): string {
        $oldStudentValue = $oldLink->{$studentColumn} ?? null;
        $oldCompanyValue = $oldLink->{$companyColumn} ?? null;
        $oldRegistration = $this->oldRegistrationFromLink($oldLink, $oldRegistrationColumn);

        if (blank($oldStudentValue) || blank($oldCompanyValue)) {
            return 'incomplete';
        }

        $studentProfile = $oldRegistration
            ? $this->resolveStudentProfileFromOldRegistration($oldRegistration)
            : null;

        if (! $studentProfile) {
            $studentProfile = $this->resolveStudentProfile($oldStudentValue, $studentColumn);
        }

        if (! $studentProfile) {
            return 'missing_student';
        }

        $company = $this->resolveCompany($oldCompanyValue);

        if (! $company) {
            return 'missing_company';
        }

        $registrations = $this->registrationsForDirectOldRegistrationId($oldLink, $oldRegistrationColumn, $studentProfile->user_id);

        if ($registrations->isEmpty() && $oldRegistration) {
            $registrations = $this->registrationsForOldRegistration($oldRegistration, $studentProfile->user_id, $year, $semester);
        }

        if ($registrations->isEmpty()) {
            $registrations = $this->registrationsForStudent($studentProfile->user_id, $year, $semester);
        }

        if ($registrations->isEmpty()) {
            return 'missing_registration';
        }

        $placement = $this->placementForCompany($company);
        $status = $this->trainingStatus($oldLink, $statusColumn);
        $createdBy = $this->createdById();
        $result = 'skipped_existing';

        foreach ($registrations as $registration) {
            $currentResult = $this->upsertStudentCompany($registration, $company, $placement, $status, $createdBy);

            if ($currentResult === 'created') {
                $result = 'created';
            } elseif ($currentResult === 'updated' && $result !== 'created') {
                $result = 'updated';
            }
        }

        return $result;
    }

    private function upsertStudentCompany(
        Registration $registration,
        Company $company,
        array $placement,
        int $status,
        int $createdBy,
    ): string {
        $studentCompany = StudentCompany::withTrashed()
            ->where('registration_id', $registration->id)
            ->first();

        if ($studentCompany && ! $this->option('update-existing')) {
            return 'skipped_existing';
        }

        $attributes = [
            'registration_id' => $registration->id,
            'student_id' => $registration->student_id,
            'company_id' => $company->id,
            'branch_id' => $placement['branch_id'],
            'department_id' => $placement['department_id'],
            'status' => $status,
            'created_by' => $createdBy,
        ];

        if ($this->option('dry-run')) {
            return $studentCompany ? 'updated' : 'created';
        }

        if ($studentCompany) {
            if ($studentCompany->trashed()) {
                $studentCompany->restore();
            }

            $studentCompany->fill($attributes)->save();

            return 'updated';
        }

        StudentCompany::create($attributes);

        return 'created';
    }

    private function resolveOldTable(): string
    {
        $requestedTable = trim((string) $this->option('table'));

        if ($requestedTable !== '') {
            if (! Schema::connection('old_db')->hasTable($requestedTable)) {
                throw new \RuntimeException("Old database table [{$requestedTable}] was not found.");
            }

            return $requestedTable;
        }

        foreach (self::OLD_TABLE_CANDIDATES as $table) {
            if (Schema::connection('old_db')->hasTable($table)) {
                return $table;
            }
        }

        throw new \RuntimeException(
            'Could not auto-detect the old student/company link table. Pass --table=... explicitly.'
        );
    }

    private function resolveColumn(string $optionName, array $columns, array $candidates): ?string
    {
        $requestedColumn = trim((string) $this->option($optionName));

        if ($requestedColumn !== '') {
            return $this->hasColumn($columns, $requestedColumn) ? $requestedColumn : null;
        }

        return $this->firstExistingColumn($columns, $candidates);
    }

    private function firstExistingColumn(array $columns, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            foreach ($columns as $column) {
                if (strtolower($column) === strtolower($candidate)) {
                    return $column;
                }
            }
        }

        return null;
    }

    private function hasColumn(array $columns, string $candidate): bool
    {
        return $this->firstExistingColumn($columns, [$candidate]) !== null;
    }

    private function selectColumns(string $studentColumn, string $companyColumn, ?string $statusColumn, ?string $registrationColumn): array
    {
        return array_values(array_unique(array_filter([
            $studentColumn,
            $companyColumn,
            $statusColumn,
            $registrationColumn,
        ])));
    }

    private function orderColumn(array $columns, string $fallback): string
    {
        return $this->firstExistingColumn($columns, ['id', 'sc_id', 'student_company_id']) ?? $fallback;
    }

    private function resolveStudentProfile(mixed $identifier, string $studentColumn): ?StudentProfile
    {
        $cacheKey = $studentColumn.':'.trim((string) $identifier);

        if (array_key_exists($cacheKey, $this->studentProfileCache)) {
            return $this->studentProfileCache[$cacheKey];
        }

        $profile = $this->findStudentProfileByIdentifier($identifier);

        if (! $profile && $this->looksLikeOldUserIdColumn($studentColumn)) {
            $oldUser = $this->oldUserById($identifier);

            foreach ($this->studentIdentifiersFromOldUser($oldUser) as $studentIdentifier) {
                $profile = $this->findStudentProfileByIdentifier($studentIdentifier);

                if ($profile) {
                    break;
                }
            }
        }

        return $this->studentProfileCache[$cacheKey] = $profile;
    }

    private function findStudentProfileByIdentifier(mixed $identifier): ?StudentProfile
    {
        $identifier = trim((string) $identifier);

        if ($identifier === '') {
            return null;
        }

        $profile = StudentProfile::where('student_number', $identifier)->first();

        if ($profile) {
            return $profile;
        }

        $digitsOnly = preg_replace('/\D+/', '', $identifier);

        if ($digitsOnly && $digitsOnly !== $identifier) {
            $profile = StudentProfile::where('student_number', $digitsOnly)->first();

            if ($profile) {
                return $profile;
            }
        }

        if (str_contains($identifier, '@')) {
            return User::where('email', $identifier)->first()?->studentProfile;
        }

        return User::where('email', $identifier.'@ppu.edu.ps')->first()?->studentProfile;
    }

    private function looksLikeOldUserIdColumn(string $column): bool
    {
        return $this->firstExistingColumn([$column], self::OLD_USER_ID_COLUMNS) !== null;
    }

    private function oldUserById(mixed $oldUserId): ?object
    {
        $oldUserId = trim((string) $oldUserId);

        if ($oldUserId === '') {
            return null;
        }

        if (array_key_exists($oldUserId, $this->oldUserCache)) {
            return $this->oldUserCache[$oldUserId];
        }

        if (! Schema::connection('old_db')->hasTable('users')) {
            return $this->oldUserCache[$oldUserId] = null;
        }

        $columns = $this->oldUsersColumns();
        $idColumn = $this->firstExistingColumn($columns, ['u_id', 'id', 'user_id']);

        if (! $idColumn) {
            return $this->oldUserCache[$oldUserId] = null;
        }

        $select = array_values(array_unique(array_filter([
            $idColumn,
            $this->firstExistingColumn($columns, ['u_username', 'username', 'student_number', 'student_no']),
            $this->firstExistingColumn($columns, ['email']),
        ])));

        return $this->oldUserCache[$oldUserId] = DB::connection('old_db')
            ->table('users')
            ->select($select)
            ->where($idColumn, $oldUserId)
            ->first();
    }

    private function oldUsersColumns(): array
    {
        if ($this->oldUsersColumns !== null) {
            return $this->oldUsersColumns;
        }

        return $this->oldUsersColumns = Schema::connection('old_db')->getColumnListing('users');
    }

    private function oldRegistrationFromLink(object $oldLink, ?string $oldRegistrationColumn): ?object
    {
        if (! $oldRegistrationColumn || blank($oldLink->{$oldRegistrationColumn} ?? null)) {
            return null;
        }

        return $this->oldRegistrationById($oldLink->{$oldRegistrationColumn});
    }

    private function oldRegistrationById(mixed $oldRegistrationId): ?object
    {
        $oldRegistrationId = trim((string) $oldRegistrationId);

        if ($oldRegistrationId === '') {
            return null;
        }

        if (array_key_exists($oldRegistrationId, $this->oldRegistrationCache)) {
            return $this->oldRegistrationCache[$oldRegistrationId];
        }

        if (! Schema::connection('old_db')->hasTable('registration')) {
            return $this->oldRegistrationCache[$oldRegistrationId] = null;
        }

        return $this->oldRegistrationCache[$oldRegistrationId] = DB::connection('old_db')
            ->table('registration')
            ->select(['r_id', 'r_student_id', 'r_course_id', 'r_semester', 'r_year'])
            ->where('r_id', $oldRegistrationId)
            ->first();
    }

    private function resolveStudentProfileFromOldRegistration(object $oldRegistration): ?StudentProfile
    {
        if (blank($oldRegistration->r_student_id ?? null)) {
            return null;
        }

        $oldUser = $this->oldUserById($oldRegistration->r_student_id);

        foreach ($this->studentIdentifiersFromOldUser($oldUser) as $studentIdentifier) {
            $profile = $this->findStudentProfileByIdentifier($studentIdentifier);

            if ($profile) {
                return $profile;
            }
        }

        return null;
    }

    private function studentIdentifiersFromOldUser(?object $oldUser): array
    {
        if (! $oldUser) {
            return [];
        }

        $identifiers = [];

        foreach (['u_username', 'username', 'student_number', 'student_no', 'email'] as $column) {
            if (! blank($oldUser->{$column} ?? null)) {
                $identifiers[] = trim((string) $oldUser->{$column});
            }
        }

        foreach ($identifiers as $identifier) {
            if (str_contains($identifier, '@')) {
                $identifiers[] = strstr($identifier, '@', true);
            }
        }

        return array_values(array_unique(array_filter($identifiers)));
    }

    private function resolveCompany(mixed $oldCompanyIdentifier): ?Company
    {
        $oldCompanyIdentifier = trim((string) $oldCompanyIdentifier);

        if ($oldCompanyIdentifier === '') {
            return null;
        }

        if (array_key_exists($oldCompanyIdentifier, $this->companyCache)) {
            return $this->companyCache[$oldCompanyIdentifier];
        }

        $company = null;

        if ($this->companiesHaveOldCompanyId() && is_numeric($oldCompanyIdentifier)) {
            $company = Company::where('old_company_id', (int) $oldCompanyIdentifier)->first();
        }

        if (! $company && is_numeric($oldCompanyIdentifier)) {
            $company = Company::find((int) $oldCompanyIdentifier);
        }

        if (! $company) {
            $company = Company::whereTranslation('name', $oldCompanyIdentifier, 'ar')->first();
        }

        if (! $company) {
            $company = Company::whereTranslation('name', $oldCompanyIdentifier, 'en')->first();
        }

        return $this->companyCache[$oldCompanyIdentifier] = $company;
    }

    private function companiesHaveOldCompanyId(): bool
    {
        if ($this->companiesHaveOldCompanyIdColumn !== null) {
            return $this->companiesHaveOldCompanyIdColumn;
        }

        return $this->companiesHaveOldCompanyIdColumn = Schema::hasColumn((new Company)->getTable(), 'old_company_id');
    }

    private function registrationsForStudent(int $studentId, int $year, int $semester): EloquentCollection
    {
        $cacheKey = "{$studentId}:{$year}:{$semester}";

        if (array_key_exists($cacheKey, $this->registrationCache)) {
            return $this->registrationCache[$cacheKey];
        }

        return $this->registrationCache[$cacheKey] = Registration::query()
            ->where('student_id', $studentId)
            ->where('year', $year)
            ->where('semester', $semester)
            ->get();
    }

    private function registrationsForDirectOldRegistrationId(object $oldLink, ?string $oldRegistrationColumn, int $studentId): EloquentCollection
    {
        if (! $oldRegistrationColumn || blank($oldLink->{$oldRegistrationColumn} ?? null)) {
            return new EloquentCollection;
        }

        return Registration::query()
            ->whereKey($oldLink->{$oldRegistrationColumn})
            ->where('student_id', $studentId)
            ->get();
    }

    private function registrationsForOldRegistration(object $oldRegistration, int $studentId, int $year, int $semester): EloquentCollection
    {
        $oldCourse = $this->oldCourseById($oldRegistration->r_course_id ?? null);
        $courseCodes = $this->courseCodesFromOldCourse($oldCourse);

        if (empty($courseCodes)) {
            return new EloquentCollection;
        }

        $courseIds = Course::query()
            ->whereIn('course_code', $courseCodes)
            ->pluck('id');

        if ($courseIds->isEmpty()) {
            return new EloquentCollection;
        }

        return Registration::query()
            ->where('student_id', $studentId)
            ->where('year', $year)
            ->where('semester', $semester)
            ->whereIn('course_id', $courseIds)
            ->get();
    }

    private function oldCourseById(mixed $oldCourseId): ?object
    {
        $oldCourseId = trim((string) $oldCourseId);

        if ($oldCourseId === '') {
            return null;
        }

        if (array_key_exists($oldCourseId, $this->oldCourseCache)) {
            return $this->oldCourseCache[$oldCourseId];
        }

        if (! Schema::connection('old_db')->hasTable('courses')) {
            return $this->oldCourseCache[$oldCourseId] = null;
        }

        return $this->oldCourseCache[$oldCourseId] = DB::connection('old_db')
            ->table('courses')
            ->select(['c_id', 'c_course_code', 'c_reference_code'])
            ->where('c_id', $oldCourseId)
            ->first();
    }

    private function courseCodesFromOldCourse(?object $oldCourse): array
    {
        if (! $oldCourse) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            fn ($code) => trim((string) $code),
            [
                $oldCourse->c_course_code ?? null,
                $oldCourse->c_reference_code ?? null,
            ]
        ))));
    }

    private function placementForCompany(Company $company): array
    {
        if (array_key_exists($company->id, $this->companyPlacementCache)) {
            return $this->companyPlacementCache[$company->id];
        }

        $branch = $company->branches()
            ->wherePivot('is_main', true)
            ->first()
            ?: $company->branches()->first();

        $branchId = $branch?->id;

        return $this->companyPlacementCache[$company->id] = [
            'branch_id' => $branchId,
            'department_id' => $branchId ? $this->departmentIdForBranch($branchId) : null,
        ];
    }

    private function departmentIdForBranch(int $branchId): ?int
    {
        return DB::table(config('ppuds.table_prefix').'branch_department')
            ->where('branch_id', $branchId)
            ->value('company_department_id');
    }

    private function trainingStatus(object $oldLink, ?string $statusColumn): int
    {
        if (! $statusColumn || blank($oldLink->{$statusColumn} ?? null)) {
            return TrainingStatus::AVAILABLE->value;
        }

        return TrainingStatus::tryFrom((int) $oldLink->{$statusColumn})?->value
            ?? TrainingStatus::AVAILABLE->value;
    }

    private function createdById(): int
    {
        return (int) (auth()->id() ?: User::query()->value('id') ?: 1);
    }
}
