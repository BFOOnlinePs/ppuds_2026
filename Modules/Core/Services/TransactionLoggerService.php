<?php

namespace Modules\Core\Services;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Entities\CoreTransaction;
use Carbon\Carbon;
use UnitEnum;

class TransactionLoggerService
{
    public function log(
        Model $sourceDocument,
        string $flow,
        float $amount,
        int $paymentMethod,
        ?string $referenceNo = null,
        UnitEnum $sourceTypeEnum,
        ?string $description = null,
        ?Model $relatedEntity = null,
    ): CoreTransaction
    {
        $moduleName = explode('\\', $sourceDocument::class)[1] ?? 'Core';

        return CoreTransaction::create([
            'amount'               => $amount,
            'flow'                 => $flow,
            'transaction_date'     => Carbon::now(),
            'description'          => $description,
            'source_module'        => $moduleName,
            'source_type'          => $sourceTypeEnum->value,
            'transactionable_id'   => $sourceDocument->id,
            'transactionable_type' => $sourceDocument::class,
            'related_entity_id'    => $relatedEntity?->id,
            'related_entity_type'  => $relatedEntity?->getMorphClass(),
            'payment_method'      => $paymentMethod ?? null,
            'reference_no'        => $referenceNo ?? null,
            'created_by'           => auth()->user()->id
        ]);
    }
}
