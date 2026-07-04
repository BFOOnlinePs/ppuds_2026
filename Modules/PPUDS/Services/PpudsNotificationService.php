<?php

namespace Modules\PPUDS\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\Core\Notifications\GeneralNotification;
use Modules\PPUDS\Entities\Announcement;
use Modules\PPUDS\Entities\LeaveRequest;
use Modules\PPUDS\Entities\Payment;
use Modules\PPUDS\Entities\StudentAttendance;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Entities\Survey;
use Modules\PPUDS\Enums\PaymentStatus;
use Throwable;
use Wirechat\Wirechat\Models\Message;

class PpudsNotificationService
{
    public function attendanceCheckedIn(StudentAttendance $attendance): void
    {
        $this->notifyAttendanceSupervisor($attendance, __('Student Check In'), __(':student checked in at :time', [
            'student' => $this->studentName($attendance->studentCompany),
            'time' => optional($attendance->check_in)->format('H:i') ?? now()->format('H:i'),
        ]));
    }

    public function attendanceCheckedOut(StudentAttendance $attendance): void
    {
        $this->notifyAttendanceSupervisor($attendance, __('Student Check Out'), __(':student checked out at :time', [
            'student' => $this->studentName($attendance->studentCompany),
            'time' => optional($attendance->check_out)->format('H:i') ?? now()->format('H:i'),
        ]));
    }

    public function attendanceStatusUpdated(StudentAttendance $attendance): void
    {
        $attendance->loadMissing('studentCompany.student');

        $status = $attendance->status;

        $this->notifyUsers($this->recipients([
            $attendance->studentCompany?->student,
        ]), new GeneralNotification(
            title: __('Attendance Status Updated'),
            message: __(':side updated your attendance status to :status.', [
                'side' => $this->actorSide(),
                'status' => is_object($status) && method_exists($status, 'getLabel') ? $status->getLabel() : (string) $status,
            ]),
            url: $this->routeUrl('student-attendances.index'),
            icon: 'heroicon-o-clipboard-document-check',
            color: 'text-primary',
        ));
    }

    public function leaveRequestCreated(LeaveRequest $leaveRequest): void
    {
        $leaveRequest->loadMissing('studentCompany.registration.supervisor', 'studentCompany.student');

        $this->notifyUsers($this->recipients([
            $this->companySupervisor($leaveRequest->studentCompany),
            $leaveRequest->studentCompany?->registration?->supervisor,
        ]), new GeneralNotification(
            title: __('New Leave Request'),
            message: __(':student submitted a leave request.', [
                'student' => $this->studentName($leaveRequest->studentCompany),
            ]),
            url: $this->routeUrl('leave-requests.index'),
            icon: 'heroicon-o-clock',
            color: 'text-warning',
        ));
    }

    public function leaveRequestDecisionUpdated(LeaveRequest $leaveRequest, string $approvalField): void
    {
        $leaveRequest->loadMissing('studentCompany.student');

        $status = $leaveRequest->{$approvalField};

        $this->notifyUsers($this->recipients([
            $leaveRequest->studentCompany?->student,
        ]), new GeneralNotification(
            title: __('Leave Request Status Updated'),
            message: __(':side updated your leave request status to :status.', [
                'side' => $approvalField === 'company_approval' ? __('Company Supervisor') : __('University Supervisor'),
                'status' => $status?->getLabel() ?? (string) $status,
            ]),
            url: $this->routeUrl('leave-requests.index'),
            icon: 'heroicon-o-clipboard-document-check',
            color: 'text-primary',
        ));
    }

    public function paymentCreated(Payment $payment): void
    {
        $payment->loadMissing('studentCompany.student', 'currency');

        $this->notifyUsers($this->recipients([
            $payment->studentCompany?->student,
        ]), new GeneralNotification(
            title: __('New Payment Added'),
            message: __('A payment of :amount :currency was added by the company.', [
                'amount' => number_format((float) $payment->payment_value, 2),
                'currency' => $payment->currency?->name ?? '',
            ]),
            url: $this->studentCompanyUrl($payment->studentCompany),
            icon: 'heroicon-o-banknotes',
            color: 'text-success',
        ));
    }

    public function paymentApprovedByStudent(Payment $payment): void
    {
        $payment->loadMissing('studentCompany.student');

        if ($payment->status !== PaymentStatus::PAID) {
            return;
        }

        $this->notifyUsers($this->recipients([
            $this->companySupervisor($payment->studentCompany),
            $payment->supervisor,
        ]), new GeneralNotification(
            title: __('Payment Approved By Student'),
            message: __(':student approved payment :reference.', [
                'student' => $this->studentName($payment->studentCompany),
                'reference' => $payment->reference_id ?: '#'.$payment->id,
            ]),
            url: $this->studentCompanyUrl($payment->studentCompany),
            icon: 'heroicon-o-check-badge',
            color: 'text-success',
        ));
    }

    public function surveyCreated(Survey $survey): void
    {
        if (! $survey->is_active) {
            return;
        }

        $notification = new GeneralNotification(
            title: __('New Survey'),
            message: $this->plainText($survey->title ?: __('You have a new survey to submit.')),
            url: $this->routeUrl('surveys.survey-form', [$survey]),
            icon: 'heroicon-o-clipboard-document-list',
            color: 'text-info',
        );

        $this->targetSurveyUsers($survey)
            ->chunkById(100, fn (Collection $users) => $this->notifyUsers($users, $notification));
    }

    public function announcementCreated(Announcement $announcement): void
    {
        $notification = new GeneralNotification(
            title: $this->plainText($announcement->name ?: __('New Announcement')),
            message: $this->plainText($announcement->content ?: __('You have a new announcement.')),
            url: $this->routeUrl('announcements.details', [$announcement]),
            icon: 'heroicon-o-megaphone',
            color: 'text-primary',
            image: $announcement->getImageAttribute(),
        );

        $this->targetAnnouncementUsers($announcement)
            ->chunkById(100, fn (Collection $users) => $this->notifyUsers($users, $notification));
    }

    public function chatMessageCreated(Message $message): void
    {
        $message->loadMissing('conversation.participants.participantable', 'sendable');

        $sender = $message->sendable;
        $senderName = $sender?->name ?? __('Someone');
        $body = $this->plainText($message->body ?: __('New message'));

        $users = $message->conversation?->participants
            ?->pluck('participantable')
            ->filter(fn ($participant) => $participant instanceof User)
            ->reject(fn (User $participant) => $participant->getKey() == $message->sendable_id
                && $participant->getMorphClass() === $message->sendable_type)
            ->values() ?? collect();

        $this->notifyUsers($users, new GeneralNotification(
            title: __('New Chat Message'),
            message: __(':sender: :message', [
                'sender' => $senderName,
                'message' => Str::limit($body, 120),
            ]),
            url: $this->routeUrl('chat-messages.show', [$message->conversation_id]),
            icon: 'heroicon-o-chat-bubble-left-right',
            color: 'text-primary',
        ));
    }

    private function notifyAttendanceSupervisor(StudentAttendance $attendance, string $title, string $message): void
    {
        $attendance->loadMissing('studentCompany.student', 'studentCompany.department', 'studentCompany.branch');

        $this->notifyUsers($this->recipients([
            $this->companySupervisor($attendance->studentCompany),
        ]), new GeneralNotification(
            title: $title,
            message: $message,
            url: $this->routeUrl('student-attendances.index'),
            icon: 'heroicon-o-map-pin',
            color: 'text-primary',
        ));
    }

    private function companySupervisor(?StudentCompany $studentCompany): ?User
    {
        if (! $studentCompany?->branch_id || ! $studentCompany->department_id) {
            return null;
        }

        $department = $studentCompany->department;

        $branch = $department?->branches()
            ->whereKey($studentCompany->branch_id)
            ->first();

        $userId = $branch?->pivot?->user_id;

        return $userId ? User::find($userId) : null;
    }

    private function targetSurveyUsers(Survey $survey): Builder
    {
        return User::query()
            ->when($survey->serve_group, fn (Builder $query, string $role) => $query->role($role))
            ->when($survey->major_id, fn (Builder $query, int $majorId) => $query->whereHas('studentProfile', fn (Builder $profileQuery) => $profileQuery->where('major_id', $majorId)));
    }

    private function targetAnnouncementUsers(Announcement $announcement): Builder
    {
        $roles = collect($announcement->target_roles ?? [])->filter()->values();
        $studentMajorId = data_get($announcement->filters, 'major_id');

        if ($roles->isEmpty()) {
            return User::query()->whereRaw('1 = 0');
        }

        return User::query()
            ->where(function (Builder $roleQuery) use ($roles, $studentMajorId) {
                foreach ($roles as $role) {
                    $roleQuery->orWhere(function (Builder $query) use ($role, $studentMajorId) {
                        $query->role($role);

                        if ($role === UserRole::STUDENT->value && $studentMajorId) {
                            $query->whereHas('studentProfile', fn (Builder $profileQuery) => $profileQuery->where('major_id', $studentMajorId));
                        }
                    });
                }
            });
    }

    private function notifyUsers(Collection $users, GeneralNotification $notification): void
    {
        $users = $users
            ->filter(fn ($user) => $user instanceof User)
            ->unique('id')
            ->values();

        if ($users->isEmpty()) {
            return;
        }

        try {
            Notification::send($users, $notification);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function recipients(array $users): Collection
    {
        return collect($users);
    }

    private function studentName(?StudentCompany $studentCompany): string
    {
        return $studentCompany?->student?->name ?? __('Student');
    }

    private function actorSide(): string
    {
        $user = auth()->user();

        if ($user?->hasRole(UserRole::COMPANY_SUPERVISOR->value)) {
            return __('Company Supervisor');
        }

        if ($user?->hasRole(UserRole::PRACTICAL_TRAINING_SUPERVISOR->value)) {
            return __('University Supervisor');
        }

        return __('Supervisor');
    }

    private function studentCompanyUrl(?StudentCompany $studentCompany): string
    {
        return $studentCompany
            ? $this->routeUrl('student-companies.details', [$studentCompany])
            : '#';
    }

    private function routeUrl(string $name, array $parameters = []): string
    {
        return Route::has($name) ? route($name, $parameters) : '#';
    }

    private function plainText(string $value): string
    {
        return trim(Str::limit(strip_tags(html_entity_decode($value)), 180));
    }
}
