<?php

namespace Modules\Core\Interfaces;

use Illuminate\Database\Eloquent\Model;
use UnitEnum;

interface TransactionLoggerInterface {
    public function log(
        Model $sourceDocument,
        string $flow,
        float $amount,
        int $paymentMethod,
        ?string $referenceNo = null,
        UnitEnum $sourceTypeEnum,
        ?string $description = null,
        ?Model $relatedEntity = null
    ): Model;
}
