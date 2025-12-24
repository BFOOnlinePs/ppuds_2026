<?php

namespace Modules\Core\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CoreTransaction extends Model
{
    // التأكد من أن اسم الجدول يطابق ما في الـ Migration
    protected $table = 'core_transactions';

    // الحقول المسموح بالتعديل عليها
    protected $fillable = [
        'amount',
        'flow',
        'transaction_date',
        'description',
        'source_module',
        'source_type',
        'related_entity_id',
        'related_entity_type',
        'transactionable_type',
        'transactionable_id',
        'reference_no',
        'payment_method',
        'meta_data',
        'created_by'
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // //////////////////////////////////////////////////
    // العلاقات (Relationships)
    // //////////////////////////////////////////////////

    /**
     * العلاقة متعددة الأشكال (Polymorphic) التي تربط الحركة بالمستند الأصلي (مثل Subscription, Invoice).
     */
    public function transactionable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * العلاقة متعددة الأشكال للربط بالطرف المرتبط (مثل Patient/Customer).
     */
    public function relatedEntity(): MorphTo
    {
        return $this->morphTo('related_entity');
    }
}
