<?php

namespace Modules\Core\Actions\AiAssistant;

use Illuminate\Support\Facades\DB;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Enums\TrainingStatus;

class UnlinkStudentCompany
{
    /**
     * نفس طريقة إلغاء الربط في مساعد الصفحة الرئيسية: الحالة "محذوف" ثم soft delete.
     */
    public function handle(StudentCompany $studentCompany): void
    {
        DB::transaction(function () use ($studentCompany) {
            $studentCompany->forceFill([
                'status' => TrainingStatus::DELETED,
            ])->save();

            $studentCompany->delete();
        });
    }
}
