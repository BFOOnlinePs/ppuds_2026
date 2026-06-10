<?php

namespace Modules\PPUDS\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Branch\Entities\Branch;
use Modules\Branch\Entities\BranchWorkingHour;
use Modules\Branch\Enums\BranchStatus;
use Modules\Branch\Enums\WeekDay;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\GeoLocation\Entities\City;
use Modules\GeoLocation\Entities\Country;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\CompanyCategory;
use Modules\PPUDS\Entities\CompanyDepartment;
use Modules\PPUDS\Entities\Registration;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Entities\StudentProfile;
use Modules\PPUDS\Enums\CompanyStatus;
use Modules\PPUDS\Enums\TrainingStatus;
use Modules\PPUDS\Settings\GeneralSettings;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Spatie\Permission\Models\Role;
use Throwable;

class StudentCompanyPlacementImporter
{
    private const DEFAULT_CATEGORY_NAME_AR = 'تصنيف عام';

    private const DEFAULT_CATEGORY_NAME_EN = 'General Category';

    private const DEFAULT_DEPARTMENT_NAME_AR = 'القسم الأول';

    private const DEFAULT_DEPARTMENT_NAME_EN = 'First Department';

    private const PORTAL_SHEET_NAME = 'potral';

    private array $options = [];

    private array $stats = [];

    private array $issues = [];

    private ?CompanyCategory $defaultCategory = null;

    private ?CompanyDepartment $defaultDepartment = null;

    private ?Country $defaultCountry = null;

    private ?City $defaultCity = null;

    private array $studentProfileCache = [];

    private array $registrationCache = [];

    private array $companyCache = [];

    private array $supervisorCache = [];

    private array $cityCache = [];

    private int $createdBy;

    public function import(string $filePath, array $options = []): array
    {
        $this->reset($options);
        $this->createdBy = $this->resolveCreatedBy($this->options['created_by']);

        if (! is_file($filePath)) {
            throw new \InvalidArgumentException(__('File [:file] was not found.', ['file' => $filePath]));
        }

        if ($this->options['dry_run']) {
            DB::beginTransaction();
        }

        try {
            $reader = IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);

            $spreadsheet = $reader->load($filePath);
            $sheetNames = $spreadsheet->getSheetNames();

            foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
                if (! $this->shouldImportSheet($sheet, $sheetNames)) {
                    $this->stats['sheets_skipped']++;
                    continue;
                }

                $this->stats['sheets_imported']++;
                $this->importSheet($sheet);
            }
        } finally {
            if ($this->options['dry_run'] && DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }

        return [
            'stats' => $this->stats,
            'issues' => $this->issues,
        ];
    }

    private function reset(array $options): void
    {
        $settings = app(GeneralSettings::class);

        $this->options = [
            'year' => (int) $settings->year,
            'semester' => (int) $settings->semester_type->value,
            'created_by' => null,
            'dry_run' => false,
            'update_existing' => false,
            'include_portal' => false,
            'sheets' => [],
            'use_latest_registration' => false,
        ];

        foreach ($options as $key => $value) {
            if ($value !== null) {
                $this->options[$key] = $value;
            }
        }

        $this->options['sheets'] = collect($this->options['sheets'])
            ->map(fn (string $sheet): string => $this->normaliseForCompare($sheet))
            ->filter()
            ->values()
            ->all();

        $this->stats = [
            'sheets_imported' => 0,
            'sheets_skipped' => 0,
            'rows_seen' => 0,
            'rows_imported' => 0,
            'rows_incomplete' => 0,
            'companies_created' => 0,
            'companies_updated' => 0,
            'supervisors_created' => 0,
            'supervisors_updated' => 0,
            'branches_created' => 0,
            'branches_updated' => 0,
            'student_company_created' => 0,
            'student_company_updated' => 0,
            'student_company_skipped_existing' => 0,
            'missing_students' => 0,
            'missing_registrations' => 0,
            'errors' => 0,
        ];

        $this->issues = [];
        $this->defaultCategory = null;
        $this->defaultDepartment = null;
        $this->defaultCountry = null;
        $this->defaultCity = null;
        $this->studentProfileCache = [];
        $this->registrationCache = [];
        $this->companyCache = [];
        $this->supervisorCache = [];
        $this->cityCache = [];
    }

    private function shouldImportSheet(Worksheet $sheet, array $sheetNames): bool
    {
        $requestedSheets = $this->options['sheets'];
        $normalisedTitle = $this->normaliseForCompare($sheet->getTitle());

        if ($requestedSheets !== []) {
            return in_array($normalisedTitle, $requestedSheets, true);
        }

        if (
            ! $this->options['include_portal']
            && $normalisedTitle === $this->normaliseForCompare(self::PORTAL_SHEET_NAME)
            && count($sheetNames) > 1
        ) {
            return false;
        }

        return true;
    }

    private function importSheet(Worksheet $sheet): void
    {
        $highestRow = $sheet->getHighestDataRow();
        $highestColumn = $sheet->getHighestDataColumn();
        $layout = $this->detectSheetLayout($sheet, $highestColumn);

        for ($rowNumber = 2; $rowNumber <= $highestRow; $rowNumber++) {
            $row = $sheet->rangeToArray("A{$rowNumber}:{$highestColumn}{$rowNumber}", null, true, false)[0] ?? [];

            if ($this->isEmptyRow($row)) {
                continue;
            }

            $this->stats['rows_seen']++;
            $placement = $this->placementFromRow($row, $layout, $sheet->getTitle(), $rowNumber);

            if (! $placement['student_number'] || ! $placement['company_name']) {
                $this->stats['rows_incomplete']++;
                $this->addIssue($sheet->getTitle(), $rowNumber, __('Missing student number or company name.'));
                continue;
            }

            try {
                DB::transaction(function () use ($placement): void {
                    $this->importPlacement($placement);
                });
            } catch (Throwable $e) {
                $this->stats['errors']++;
                $this->addIssue($sheet->getTitle(), $rowNumber, $e->getMessage());
            }
        }
    }

    private function detectSheetLayout(Worksheet $sheet, string $highestColumn): string
    {
        $headers = $sheet->rangeToArray("A1:{$highestColumn}1", null, true, false)[0] ?? [];
        $firstHeader = $this->cleanText($headers[0] ?? null);
        $secondHeader = $this->cleanText($headers[1] ?? null);

        if ($firstHeader === 'الرقم الجامعي' && $secondHeader === 'جهة التدريب') {
            return 'portal';
        }

        return 'student';
    }

    private function placementFromRow(array $row, string $layout, string $sheetName, int $rowNumber): array
    {
        if ($layout === 'portal') {
            $studentNumber = $this->cleanIdentifier($row[0] ?? null);
            $companyName = $this->cleanText($row[1] ?? null);
            $location = $this->cleanText($row[9] ?? null);
            $mainAddress = $this->cleanText($row[4] ?? null);
            $details = $this->cleanText($row[5] ?? null);

            return [
                'sheet' => $sheetName,
                'row' => $rowNumber,
                'student_number' => $studentNumber,
                'company_name' => $companyName,
                'company_location' => $location ?: $mainAddress,
                'main_address' => $mainAddress,
                'details' => $details,
                'supervisor_name' => $this->cleanText($row[6] ?? null),
                'supervisor_phone' => $this->cleanPhone($row[7] ?? null),
                'supervisor_email' => $this->cleanEmail($row[8] ?? null),
            ];
        }

        $location = $this->cleanText($row[10] ?? null);
        $mainAddress = $this->cleanText($row[11] ?? null);
        $details = $this->cleanText($row[12] ?? null);

        return [
            'sheet' => $sheetName,
            'row' => $rowNumber,
            'student_number' => $this->cleanIdentifier($row[2] ?? null),
            'company_name' => $this->cleanText($row[9] ?? null),
            'company_location' => $location ?: $mainAddress,
            'main_address' => $mainAddress,
            'details' => $details,
            'supervisor_name' => $this->cleanText($row[13] ?? null),
            'supervisor_phone' => $this->cleanPhone($row[14] ?? null),
            'supervisor_email' => $this->cleanEmail($row[15] ?? null),
        ];
    }

    private function importPlacement(array $placement): void
    {
        $supervisor = $this->supervisorForPlacement($placement);
        $company = $this->companyForPlacement($placement, $supervisor);
        $branch = $this->branchForPlacement($company, $placement, $supervisor);
        $department = $this->defaultDepartment();

        $this->ensureDepartmentSupervisor($branch, $department, $supervisor);
        $this->ensureWorkingHours($branch);

        $studentProfile = $this->studentProfile($placement['student_number']);

        if (! $studentProfile) {
            $this->stats['missing_students']++;
            $this->addIssue($placement['sheet'], $placement['row'], __('Student [:student] was not found.', [
                'student' => $placement['student_number'],
            ]));

            return;
        }

        $registrations = $this->registrationsForStudent($studentProfile->user_id);

        if ($registrations->isEmpty()) {
            $this->stats['missing_registrations']++;
            $this->addIssue($placement['sheet'], $placement['row'], __('No registration found for student [:student] in :year/:semester.', [
                'student' => $placement['student_number'],
                'year' => $this->options['year'],
                'semester' => $this->options['semester'],
            ]));

            return;
        }

        foreach ($registrations as $registration) {
            $result = $this->upsertStudentCompany($registration, $company, $branch, $department);
            $this->stats["student_company_{$result}"]++;
        }

        $this->stats['rows_imported']++;
    }

    private function supervisorForPlacement(array $placement): User
    {
        $email = $placement['supervisor_email'];

        if (! $email) {
            $email = $this->generatedSupervisorEmail($placement);
        }

        if (isset($this->supervisorCache[$email])) {
            return $this->supervisorCache[$email];
        }

        $supervisor = User::where('email', $email)->first();
        $exists = $supervisor instanceof User;

        if (! $supervisor) {
            $supervisor = new User([
                'email' => $email,
                'password' => Hash::make('123456789'),
            ]);
        }

        if (! $exists || $this->options['update_existing']) {
            $supervisor->fill([
                'name' => $placement['supervisor_name'] ?: $placement['company_name'],
                'name_en' => $placement['supervisor_name'] ?: $placement['company_name'],
                'phone' => $placement['supervisor_phone'],
            ]);
            $supervisor->save();

            $this->stats[$exists ? 'supervisors_updated' : 'supervisors_created']++;
        }

        $this->ensureCompanySupervisorRole($supervisor);

        return $this->supervisorCache[$email] = $supervisor;
    }

    private function companyForPlacement(array $placement, User $supervisor): Company
    {
        $companyName = $placement['company_name'];
        $cacheKey = $this->normaliseForCompare($companyName);

        if (isset($this->companyCache[$cacheKey])) {
            return $this->companyCache[$cacheKey];
        }

        $company = Company::withTrashed()
            ->whereTranslation('name', $companyName, 'ar')
            ->orWhereTranslation('name', $companyName, 'en')
            ->first();

        $exists = $company instanceof Company;

        if (! $company) {
            $company = new Company;
        } elseif ($company->trashed()) {
            $company->restore();
        }

        if (! $exists || $this->options['update_existing']) {
            $company->fill([
                'company_category_id' => $this->defaultCategory()->id,
                'status' => CompanyStatus::ACTIVE->value,
                'created_by' => $company->created_by ?: $supervisor->id,
            ]);
            $company->fill([
                'ar' => [
                    'name' => $companyName,
                    'description' => $this->companyDescription($placement),
                ],
                'en' => [
                    'name' => $companyName,
                    'description' => $this->companyDescription($placement),
                ],
            ]);
            $company->save();

            $this->stats[$exists ? 'companies_updated' : 'companies_created']++;
        }

        return $this->companyCache[$cacheKey] = $company;
    }

    private function branchForPlacement(Company $company, array $placement, User $supervisor): Branch
    {
        $branchName = $this->branchName($placement);
        $address = $this->branchAddress($placement);
        $branch = $this->findCompanyBranch($company, $branchName, $address);
        $exists = $branch instanceof Branch;
        $location = $this->resolveLocation($placement['company_location']);

        if (! $branch) {
            $branch = new Branch;
        }

        if (! $exists || $this->options['update_existing']) {
            $branch->fill([
                'phone' => $placement['supervisor_phone'],
                'email' => $placement['supervisor_email'],
                'city_id' => $location['city_id'],
                'country_id' => $location['country_id'],
                'latitude' => $location['latitude'],
                'longitude' => $location['longitude'],
                'status' => BranchStatus::OPEN->value,
                'created_by' => $branch->created_by ?: $supervisor->id,
            ]);
            $branch->fill([
                'ar' => [
                    'name' => $branchName,
                    'address' => $address,
                    'description' => $placement['details'],
                ],
                'en' => [
                    'name' => $branchName,
                    'address' => $address,
                    'description' => $placement['details'],
                ],
            ]);
            $branch->save();

            $this->stats[$exists ? 'branches_updated' : 'branches_created']++;
        }

        $company->branches()->syncWithoutDetaching([
            $branch->id => ['is_main' => ! $company->branches()->wherePivot('is_main', true)->exists()],
        ]);

        return $branch;
    }

    private function findCompanyBranch(Company $company, string $branchName, string $address): ?Branch
    {
        $branches = $company->branches()->with('translations')->get();

        if ($address !== '') {
            $branch = $branches->first(function (Branch $branch) use ($address): bool {
                return $this->normaliseForCompare($branch->address) === $this->normaliseForCompare($address);
            });

            if ($branch) {
                return $branch;
            }
        }

        if ($branchName !== '') {
            $branch = $branches->first(function (Branch $branch) use ($branchName): bool {
                return $this->normaliseForCompare($branch->name) === $this->normaliseForCompare($branchName);
            });

            if ($branch) {
                return $branch;
            }
        }

        return $branches->count() === 1 && $address === '' ? $branches->first() : null;
    }

    private function upsertStudentCompany(
        Registration $registration,
        Company $company,
        Branch $branch,
        CompanyDepartment $department,
    ): string {
        $studentCompany = StudentCompany::withTrashed()
            ->where('registration_id', $registration->id)
            ->first();

        if ($studentCompany && ! $this->options['update_existing']) {
            return 'skipped_existing';
        }

        $attributes = [
            'registration_id' => $registration->id,
            'student_id' => $registration->student_id,
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'status' => TrainingStatus::AVAILABLE->value,
            'created_by' => $this->createdBy,
        ];

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

    private function studentProfile(string $studentNumber): ?StudentProfile
    {
        if (array_key_exists($studentNumber, $this->studentProfileCache)) {
            return $this->studentProfileCache[$studentNumber];
        }

        $profile = StudentProfile::where('student_number', $studentNumber)->first();

        if (! $profile) {
            $profile = User::where('email', $studentNumber.'@ppu.edu.ps')->first()?->studentProfile;
        }

        return $this->studentProfileCache[$studentNumber] = $profile;
    }

    private function registrationsForStudent(int $studentId)
    {
        $cacheKey = implode(':', [
            $studentId,
            $this->options['year'],
            $this->options['semester'],
            (int) $this->options['use_latest_registration'],
        ]);

        if (array_key_exists($cacheKey, $this->registrationCache)) {
            return $this->registrationCache[$cacheKey];
        }

        $registrations = Registration::query()
            ->where('student_id', $studentId)
            ->where('year', $this->options['year'])
            ->where('semester', $this->options['semester'])
            ->get();

        if ($registrations->isEmpty() && $this->options['use_latest_registration']) {
            $registration = Registration::query()
                ->where('student_id', $studentId)
                ->latest('year')
                ->latest('id')
                ->first();

            $registrations = $registration ? collect([$registration]) : collect();
        }

        return $this->registrationCache[$cacheKey] = $registrations;
    }

    private function defaultCategory(): CompanyCategory
    {
        if ($this->defaultCategory) {
            return $this->defaultCategory;
        }

        $category = CompanyCategory::whereTranslation('name', self::DEFAULT_CATEGORY_NAME_AR, 'ar')->first();

        if (! $category) {
            $category = new CompanyCategory(['created_by' => $this->createdBy]);
            $category->fill([
                'ar' => ['name' => self::DEFAULT_CATEGORY_NAME_AR],
                'en' => ['name' => self::DEFAULT_CATEGORY_NAME_EN],
            ]);
            $category->save();
        }

        return $this->defaultCategory = $category;
    }

    private function defaultDepartment(): CompanyDepartment
    {
        if ($this->defaultDepartment) {
            return $this->defaultDepartment;
        }

        $department = CompanyDepartment::whereTranslation('name', self::DEFAULT_DEPARTMENT_NAME_AR, 'ar')->first();

        if (! $department) {
            $department = new CompanyDepartment(['created_by' => $this->createdBy]);
            $department->fill([
                'ar' => ['name' => self::DEFAULT_DEPARTMENT_NAME_AR],
                'en' => ['name' => self::DEFAULT_DEPARTMENT_NAME_EN],
            ]);
            $department->save();
        }

        return $this->defaultDepartment = $department;
    }

    private function ensureDepartmentSupervisor(Branch $branch, CompanyDepartment $department, User $supervisor): void
    {
        $table = config('ppuds.table_prefix').'branch_department';

        $existing = DB::table($table)
            ->where('branch_id', $branch->id)
            ->where('company_department_id', $department->id)
            ->first();

        if ($existing) {
            if ($this->options['update_existing'] && (int) $existing->user_id !== $supervisor->id) {
                DB::table($table)
                    ->where('id', $existing->id)
                    ->update([
                        'user_id' => $supervisor->id,
                        'updated_at' => now(),
                    ]);
            }

            return;
        }

        DB::table($table)->insert([
            'branch_id' => $branch->id,
            'company_department_id' => $department->id,
            'user_id' => $supervisor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function ensureWorkingHours(Branch $branch): void
    {
        if ($branch->workingHours()->exists()) {
            return;
        }

        foreach (WeekDay::cases() as $day) {
            BranchWorkingHour::create([
                'branch_id' => $branch->id,
                'day' => $day->value,
                'is_closed' => $day === WeekDay::FRIDAY,
                'start_time' => $day === WeekDay::FRIDAY ? null : '08:00',
                'end_time' => $day === WeekDay::FRIDAY ? null : '16:00',
            ]);
        }
    }

    private function resolveLocation(?string $locationName): array
    {
        $country = $this->defaultCountry();
        $city = $this->cityForLocation($locationName) ?: $this->defaultCity();

        return [
            'country_id' => $city?->governorate?->country_id ?: $country->id,
            'city_id' => $city?->id,
            'latitude' => $city?->latitude,
            'longitude' => $city?->longitude,
        ];
    }

    private function defaultCountry(): Country
    {
        if ($this->defaultCountry) {
            return $this->defaultCountry;
        }

        $country = Country::whereTranslation('name', 'فلسطين', 'ar')
            ->orWhereTranslation('name', 'Palestine', 'en')
            ->first()
            ?: Country::query()->first();

        if (! $country) {
            throw new \RuntimeException('No country records exist. Seed geolocation data before importing placements.');
        }

        return $this->defaultCountry = $country;
    }

    private function defaultCity(): City
    {
        if ($this->defaultCity) {
            return $this->defaultCity;
        }

        $city = City::with('governorate')
            ->whereTranslation('name', 'الخليل', 'ar')
            ->orWhereTranslation('name', 'Hebron', 'en')
            ->first()
            ?: City::with('governorate')->first();

        if (! $city) {
            throw new \RuntimeException('No city records exist. Seed geolocation data before importing placements.');
        }

        return $this->defaultCity = $city;
    }

    private function cityForLocation(?string $locationName): ?City
    {
        $locationName = $this->cleanText($locationName);

        if ($locationName === '') {
            return null;
        }

        $cacheKey = $this->normaliseForCompare($locationName);

        if (array_key_exists($cacheKey, $this->cityCache)) {
            return $this->cityCache[$cacheKey];
        }

        $city = City::with('governorate')
            ->whereTranslation('name', $locationName, 'ar')
            ->orWhereTranslation('name', $locationName, 'en')
            ->first();

        if (! $city) {
            $city = City::with('governorate')
                ->whereTranslationLike('name', "%{$locationName}%", 'ar')
                ->orWhereTranslationLike('name', "%{$locationName}%", 'en')
                ->first();
        }

        return $this->cityCache[$cacheKey] = $city;
    }

    private function resolveCreatedBy(mixed $requestedUserId): int
    {
        if ($requestedUserId && User::whereKey((int) $requestedUserId)->exists()) {
            return (int) $requestedUserId;
        }

        $userId = User::query()->value('id');

        if (! $userId) {
            throw new \RuntimeException('No users exist. Create an admin user before importing placements.');
        }

        return (int) $userId;
    }

    private function ensureCompanySupervisorRole(User $supervisor): void
    {
        Role::firstOrCreate([
            'name' => UserRole::COMPANY_SUPERVISOR->value,
            'guard_name' => 'web',
        ]);

        if (! $supervisor->hasRole(UserRole::COMPANY_SUPERVISOR->value)) {
            $supervisor->assignRole(UserRole::COMPANY_SUPERVISOR->value);
        }
    }

    private function generatedSupervisorEmail(array $placement): string
    {
        $hash = substr(sha1(implode('|', [
            $placement['company_name'],
            $placement['supervisor_name'],
            $placement['supervisor_phone'],
        ])), 0, 12);

        return "company-supervisor-{$hash}@example.com";
    }

    private function branchName(array $placement): string
    {
        $mainAddress = $placement['main_address'];
        $location = $placement['company_location'];
        $details = $placement['details'];

        if ($details && $mainAddress && $this->normaliseForCompare($details) !== $this->normaliseForCompare($mainAddress)) {
            if ($location && $this->normaliseForCompare($mainAddress) === $this->normaliseForCompare($location)) {
                return $details;
            }
        }

        return $mainAddress ?: $location ?: 'الفرع الرئيسي';
    }

    private function branchAddress(array $placement): string
    {
        return collect([
            $placement['company_location'],
            $placement['main_address'],
            $placement['details'],
        ])
            ->filter()
            ->unique(fn (string $part): string => $this->normaliseForCompare($part))
            ->implode(' - ');
    }

    private function companyDescription(array $placement): ?string
    {
        $description = $this->branchAddress($placement);

        return $description === '' ? null : Str::limit($description, 250, '');
    }

    private function addIssue(string $sheet, int $row, string $message): void
    {
        if (count($this->issues) >= 200) {
            return;
        }

        $this->issues[] = [
            'sheet' => $sheet,
            'row' => $row,
            'message' => $message,
        ];
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($this->cleanText($value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function cleanIdentifier(mixed $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value) && floor($value) === $value) {
            return (string) (int) $value;
        }

        return preg_replace('/\.0$/', '', $this->cleanText($value));
    }

    private function cleanText(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_float($value) && floor($value) === $value) {
            $value = (string) (int) $value;
        }

        return Str::of((string) $value)
            ->replace("\xc2\xa0", ' ')
            ->squish()
            ->trim()
            ->toString();
    }

    private function cleanEmail(mixed $value): ?string
    {
        $email = Str::lower(str_replace(' ', '', $this->cleanText($value)));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    private function cleanPhone(mixed $value): ?string
    {
        $phone = $this->cleanText($value);

        if ($phone === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if (strlen($digits) === 9 && str_starts_with($digits, '5')) {
            return '0'.$digits;
        }

        if (str_starts_with($digits, '972')) {
            return '+'.$digits;
        }

        return $phone;
    }

    private function normaliseForCompare(?string $value): string
    {
        return Str::lower($this->cleanText($value));
    }
}
