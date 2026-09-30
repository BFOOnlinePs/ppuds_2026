<?php

namespace Modules\PPUDS\Support;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Masmerise\Toaster\Toaster;
use Modules\Branch\Entities\Branch;
use Modules\Core\Entities\User;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\CompanyDepartment;
use Modules\PPUDS\Services\PpuApiService;
use Throwable;

/**
 * إجلاس مشرف الشركة في مقعد (فرع + قسم) وإرساله لنظام الجامعة.
 * مشترك بين شاشة مشرفي الشركات وتنبيهات الصفحة الرئيسية حتى يبقى
 * الإسناد بنفس المنطق في المكانين.
 */
trait ManagesCompanySupervisorSeats
{
    // ===================== حقول اختيار القسم =====================

    /**
     * شركة ← فرع ← قسم، كل حقل يُفتح بعد سابقه ويُصفّر ما بعده.
     * الحقل الأخير يوضّح من يشغل المقعد حالياً قبل استبداله.
     *
     * @return array<int, mixed>
     */
    protected function departmentPickerFields(): array
    {
        return [
            Select::make('company_id')
                ->label(__('Company'))
                ->options(fn (): array => Company::query()->get()->pluck('name', 'id')->toArray())
                ->required()
                ->searchable()
                ->preload()
                ->live()
                ->afterStateUpdated(function (Set $set): void {
                    $set('branch_id', null);
                    $set('department_id', null);
                })
                ->prefixIcon('solar-city-linear'),

            Select::make('branch_id')
                ->label(__('Branch'))
                ->required()
                ->searchable()
                ->preload()
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('department_id', null))
                ->disabled(fn (Get $get): bool => blank($get('company_id')))
                ->placeholder(fn (Get $get): string => filled($get('company_id')) ? __('Select Branch') : __('Select Company First'))
                ->options(fn (Get $get): array => blank($get('company_id'))
                    ? []
                    : Branch::query()
                        ->whereHas('companies', fn (Builder $query) => $query->whereKey($get('company_id')))
                        ->get()
                        ->pluck('name', 'id')
                        ->toArray())
                ->prefixIcon('solar-map-point-linear'),

            Select::make('department_id')
                ->label(__('Department'))
                ->required()
                ->searchable()
                ->preload()
                ->live()
                ->disabled(fn (Get $get): bool => blank($get('branch_id')))
                ->placeholder(fn (Get $get): string => filled($get('branch_id')) ? __('Select Department') : __('Select Branch First'))
                ->options(fn (Get $get): array => blank($get('branch_id'))
                    ? []
                    : CompanyDepartment::query()
                        ->whereHas('branches', fn (Builder $query) => $query->whereKey($get('branch_id')))
                        ->get()
                        ->pluck('name', 'id')
                        ->toArray())
                ->prefixIcon('solar-users-group-two-rounded-linear'),

            Placeholder::make('current_seat_holder')
                ->label(__('Current Supervisor Of This Department'))
                ->content(fn (Get $get): HtmlString => $this->seatHolderNotice($get('branch_id'), $get('department_id')))
                ->visible(fn (Get $get): bool => filled($get('department_id'))),
        ];
    }

    /**
     * تحذير صريح قبل الاستبدال: المقعد يتسع لمشرف واحد، والإسناد يزيح من فيه.
     */
    protected function seatHolderNotice(mixed $branchId, mixed $departmentId): HtmlString
    {
        if (blank($branchId) || blank($departmentId)) {
            return new HtmlString('');
        }

        $holderId = DB::table(config('ppuds.table_prefix').'branch_department')
            ->where('branch_id', $branchId)
            ->where('company_department_id', $departmentId)
            ->value('user_id');

        if (blank($holderId)) {
            return new HtmlString(
                '<span class="text-sm font-medium text-success-600">'.e(__('Vacant — no supervisor assigned')).'</span>'
            );
        }

        $holder = User::withTrashed()->find($holderId);

        return new HtmlString(
            '<span class="text-sm font-medium text-warning-600">'
            .e(__(':name is currently assigned and will be replaced.', ['name' => $holder?->name ?? '—']))
            .'</span>'
        );
    }

    // ===================== الإرسال والإجلاس =====================

    /**
     * إرسال المشرف إلى نظام الجامعة عبر نقطة إضافة الشركة، وهي نفس الطريقة
     * التي تستخدمها شاشة تفاصيل الشركة.
     *
     * الـ payload يبني رقم الجوال من هاتف المشرف ويسقط الإرسال كلياً إن كان
     * فارغاً، ولذلك الهاتف إجباري عند الإضافة. وكلمة المرور إن لم تُمرَّر
     * يستعمل النظام رقم الهاتف بدلاً منها.
     */
    protected function sendSupervisorToUniversity(int $companyId, int $supervisorId, ?string $plainPassword): void
    {
        // بلا هاتف يُرسل الـ payload هاتف الفرع بدلاً منه، فيُسجَّل في الجامعة
        // رقم ليس للمشرف. نوقف الإرسال ونطلب الرقم بدل تسجيل رقم خاطئ.
        if (blank(User::query()->whereKey($supervisorId)->value('phone'))) {
            Toaster::error(__('The supervisor has no phone number, so they were not sent to the university system. Add their phone number, then assign the department again.'));

            return;
        }

        $company = Company::query()
            ->with(['branches.supervisors', 'translations'])
            ->find($companyId);

        if (! $company) {
            return;
        }

        $apiService = app(PpuApiService::class);

        try {
            $result = $apiService->addCompanyToUniversity(
                $company,
                $plainPassword,
                $supervisorId,
                sendEvenIfCompanyExists: true,
            );
        } catch (Throwable $exception) {
            report($exception);

            Toaster::error(__('Unable to send company supervisor to university system'));

            return;
        }

        // ردّ الجامعة يُعرض كما هو: «مضاف مسبقاً» قد يخص الشركة لا المشرف،
        // فلا نحوّله إلى رسالة نجاح عامة تُخفي أن رقم المشرف لم يُضف.
        $universityMessage = $apiService->universityResponseMessage($result);
        $universityMessage = filled($universityMessage)
            ? Str::limit(Str::squish(strip_tags($universityMessage)), 300)
            : null;

        if (($result['operation'] ?? null) === 'already_exists') {
            filled($universityMessage)
                ? Toaster::warning(__('University system response: :message', ['message' => $universityMessage]))
                : Toaster::success(__('Company supervisor already exists in university system'));

            return;
        }

        if ($result === null || ($result['success'] ?? null) === false) {
            Toaster::error(filled($universityMessage)
                ? __('The university system rejected the supervisor: :message', ['message' => $universityMessage])
                : __('Unable to send company supervisor to university system'));

            return;
        }

        Toaster::success(__('Company supervisor sent to university successfully'));

        if (filled($universityMessage)) {
            Toaster::info(__('University system response: :message', ['message' => $universityMessage]));
        }
    }

    /**
     * المقعد واحد لكل (فرع + قسم): نُحدِّث الصف إن وُجد وننشئه إن كان مفقوداً.
     * الصف قد يكون مفقوداً تماماً بسبب عمليات حذف قديمة كانت تُسقطه.
     */
    protected function seatSupervisor(int $branchId, int $departmentId, int $userId): void
    {
        $pivot = config('ppuds.table_prefix').'branch_department';

        $exists = DB::table($pivot)
            ->where('branch_id', $branchId)
            ->where('company_department_id', $departmentId)
            ->exists();

        if ($exists) {
            DB::table($pivot)
                ->where('branch_id', $branchId)
                ->where('company_department_id', $departmentId)
                ->update(['user_id' => $userId, 'updated_at' => now()]);

            return;
        }

        DB::table($pivot)->insert([
            'branch_id' => $branchId,
            'company_department_id' => $departmentId,
            'user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
