<?php

namespace Modules\PPUDS\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Modules\Core\Actions\StudentCompanyAssistant\FindCompaniesForCompanyAssistant;
use Modules\Core\Actions\StudentCompanyAssistant\FindStudentsForCompanyAssistant;
use Modules\Core\Actions\StudentCompanyAssistant\LinkSuggestedCompanyToStudent;
use Modules\Core\Actions\StudentCompanyAssistant\ResolveStudentCompanyRegistration;
use Modules\Core\Entities\User;
use Modules\Core\Services\StudentCompanySuggestionService;
use Modules\Core\Traits\ApiResponse;
use Modules\PPUDS\Entities\Registration;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Http\Controllers\Api\V1\Concerns\EnsuresCurrentRegistration;
use Modules\PPUDS\Http\Requests\StudentCompanyAssistant\LinkAllAssistantCompaniesRequest;
use Modules\PPUDS\Http\Requests\StudentCompanyAssistant\LinkAssistantCompanyRequest;
use Modules\PPUDS\Http\Requests\StudentCompanyAssistant\SearchAssistantCompaniesRequest;
use Modules\PPUDS\Http\Requests\StudentCompanyAssistant\SearchAssistantStudentsRequest;
use Modules\PPUDS\Http\Requests\StudentCompanyAssistant\SuggestAssistantCompaniesRequest;
use Modules\PPUDS\Transformers\V1\RegistrationResource;
use Modules\PPUDS\Transformers\V1\StudentCompanyAssistant\StudentCompanyAssistantCompanyResource;
use Modules\PPUDS\Transformers\V1\StudentCompanyAssistant\StudentCompanyAssistantStudentResource;
use Modules\PPUDS\Transformers\V1\StudentCompanyAssistant\StudentCompanyAssistantSuggestionResource;
use Modules\PPUDS\Transformers\V1\StudentCompanyResource;

class StudentCompanyAssistantController extends Controller
{
    use ApiResponse;
    use EnsuresCurrentRegistration;

    public function searchStudents(
        SearchAssistantStudentsRequest $request,
        FindStudentsForCompanyAssistant $findStudents,
    ): JsonResponse {
        $data = $request->validated();
        $students = $findStudents->handle($data['query'], $data['limit'] ?? 8);

        return $this->successResponse([
            'students' => StudentCompanyAssistantStudentResource::collection($students)->resolve($request),
            'exact_match_id' => $findStudents->exactMatch($students, $data['query'])?->id,
        ], __('Students retrieved successfully'));
    }

    public function suggestCompanies(
        SuggestAssistantCompaniesRequest $request,
        ResolveStudentCompanyRegistration $resolveRegistration,
        StudentCompanySuggestionService $suggestionService,
    ): JsonResponse {
        $data = $request->validated();
        $student = $this->student($data['student_id']);

        if (! $student) {
            return $this->errorResponse(__('The selected user is not a student.'), 422);
        }

        [$registration, $warning] = $this->registrationForStudent(
            $student,
            $data['registration_id'] ?? null,
            $resolveRegistration,
        );

        if (! $registration) {
            return $this->errorResponse(__('No valid registration was found for this student.'), 422);
        }

        if ($response = $this->ensureRegistrationInCurrentSemester($registration)) {
            return $response;
        }

        $result = $suggestionService->suggest($student, $registration, $data['limit'] ?? 5);

        return $this->successResponse([
            'student' => StudentCompanyAssistantStudentResource::make($student)->resolve($request),
            'registration' => RegistrationResource::make($registration)->resolve($request),
            'message' => $result['message'],
            'warnings' => array_values(array_filter([$warning])),
            'used_ai' => (bool) ($result['used_ai'] ?? false),
            'suggestions' => StudentCompanyAssistantSuggestionResource::collection(
                collect($result['suggestions'] ?? [])
            )->resolve($request),
        ], __('Company suggestions generated successfully'));
    }

    public function searchCompanies(
        SearchAssistantCompaniesRequest $request,
        FindCompaniesForCompanyAssistant $findCompanies,
    ): JsonResponse {
        $data = $request->validated();
        $companies = $findCompanies->handle($data['query'], $data['limit'] ?? 8);

        return $this->successResponse([
            'companies' => StudentCompanyAssistantCompanyResource::collection($companies)->resolve($request),
        ], __('Companies retrieved successfully'));
    }

    public function linkCompany(
        LinkAssistantCompanyRequest $request,
        FindCompaniesForCompanyAssistant $findCompanies,
        LinkSuggestedCompanyToStudent $linkCompany,
    ): JsonResponse {
        $data = $request->validated();
        $student = $this->student($data['student_id']);
        $registration = $this->registrationForStudentId($data['student_id'], $data['registration_id']);

        if (! $student || ! $registration) {
            return $this->errorResponse(__('The registration does not belong to this student.'), 422);
        }

        if ($response = $this->ensureRegistrationInCurrentSemester($registration)) {
            return $response;
        }

        $suggestion = $this->suggestionFromPayload($data, $findCompanies);

        if (! $suggestion) {
            return $this->errorResponse(__('The selected company was not found.'), 422);
        }

        $operation = $linkCompany->handle(
            $student->id,
            $registration->id,
            $suggestion,
            $request->user()->id,
        );

        $studentCompany = $this->linkedStudentCompany($registration->id, $suggestion['company_id']);

        return $this->successResponse([
            'operation' => $operation,
            'student_company' => $studentCompany
                ? StudentCompanyResource::make($studentCompany)->resolve($request)
                : null,
        ], __('Company linked to student successfully'));
    }

    public function linkAllCompanies(
        LinkAllAssistantCompaniesRequest $request,
        FindCompaniesForCompanyAssistant $findCompanies,
        LinkSuggestedCompanyToStudent $linkCompany,
    ): JsonResponse {
        $data = $request->validated();
        $student = $this->student($data['student_id']);
        $registration = $this->registrationForStudentId($data['student_id'], $data['registration_id']);

        if (! $student || ! $registration) {
            return $this->errorResponse(__('The registration does not belong to this student.'), 422);
        }

        if ($response = $this->ensureRegistrationInCurrentSemester($registration)) {
            return $response;
        }

        $items = collect($data['companies'])
            ->map(function (array $companyPayload) use ($student, $registration, $findCompanies, $linkCompany, $request) {
                $suggestion = $this->suggestionFromPayload($companyPayload, $findCompanies);

                if (! $suggestion) {
                    return null;
                }

                $operation = $linkCompany->handle(
                    $student->id,
                    $registration->id,
                    $suggestion,
                    $request->user()->id,
                );

                $studentCompany = $this->linkedStudentCompany($registration->id, $suggestion['company_id']);

                return [
                    'operation' => $operation,
                    'student_company' => $studentCompany
                        ? StudentCompanyResource::make($studentCompany)->resolve($request)
                        : null,
                ];
            })
            ->filter()
            ->values();

        return $this->successResponse([
            'linked_count' => $items->count(),
            'items' => $items->all(),
        ], __('Companies linked to student successfully'));
    }

    private function student(int $studentId): ?User
    {
        return User::query()
            ->with('studentProfile.major')
            ->whereHas('studentProfile')
            ->find($studentId);
    }

    private function registrationForStudent(
        User $student,
        ?int $registrationId,
        ResolveStudentCompanyRegistration $resolveRegistration,
    ): array {
        if ($registrationId) {
            return [
                $this->registrationForStudentId($student->id, $registrationId),
                null,
            ];
        }

        return $resolveRegistration->handle($student);
    }

    private function registrationForStudentId(int $studentId, int $registrationId): ?Registration
    {
        return Registration::query()
            ->with('course')
            ->where('student_id', $studentId)
            ->find($registrationId);
    }

    private function suggestionFromPayload(
        array $payload,
        FindCompaniesForCompanyAssistant $findCompanies,
    ): ?array {
        $company = $findCompanies->find((int) $payload['company_id']);

        if (! $company) {
            return null;
        }

        return array_merge(
            $findCompanies->toSuggestion($company, Arr::get($payload, 'reason')),
            Arr::only($payload, ['branch_id', 'department_id', 'fit_score'])
        );
    }

    private function linkedStudentCompany(int $registrationId, int $companyId): ?StudentCompany
    {
        return StudentCompany::query()
            ->with([
                'registration.course',
                'student.studentProfile.major',
                'company.branches.departments',
                'branch.departments',
                'department',
            ])
            ->where('registration_id', $registrationId)
            ->where('company_id', $companyId)
            ->first();
    }
}
