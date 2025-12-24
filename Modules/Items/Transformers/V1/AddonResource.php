<?php

namespace Modules\Items\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class AddonResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name, // من ملف Addon.php
            'description' => $this->description, // من ملف Addon.php
            'type' => $this->type->getLabel(),
            'image' => $this->image,
            $this->mergeWhen(isset($this->pivot), function () {
                // هذه البيانات من جدول addon_product
                return [
                    'is_required' => (bool) $this->pivot->is_required,
                    'min_selection' => (int) $this->pivot->min_selection,
                    'max_selection' => $this->pivot->max_selection ? (int) $this->pivot->max_selection : null,
                    'max_quantity_per_option' => (int) $this->pivot->max_quantity_per_option,
                ];
            }),

            // ---== بداية الكود المُصحح لـ options ==---
            'options' => $this->when(
            // الحالة الأولى: هذا الريسورس معروض ضمن منتج (Pivot موجود)
                isset($this->pivot) && $this->pivot->id,

                // ماذا نفعل إذا كان ضمن منتج:
                function () {
                    // 1. ابحث عن الخيارات المسموحة وأسعارها المخصصة
                    // [ 'option_id' => 'price' ]
                    $customPrices = DB::table(config('items.table_prefix') . 'addon_product_option')
                        ->where('addon_product_id', $this->pivot->id) // $this->pivot->id هو id من جدول addon_product
                        ->pluck('price', 'addon_option_id'); // [ 10 => 1.50, 12 => null ]

                    $customIsDefault = DB::table(config('items.table_prefix') . 'addon_product_option')
                        ->where('addon_product_id', $this->pivot->id)
                        ->pluck('is_default', 'addon_option_id');

                    // 2. جهز الكويري الأساسي للخيارات
                    $optionsQuery = $this->addonOptions(); // (العلاقة من موديل Addon.php)

                    // 3. إذا وجدنا خيارات مخصصة (القائمة ليست فارغة)
                    if ($customPrices->isNotEmpty()) {
                        // قم بفلترة الكويري ليشمل هذه الخيارات فقط
                        $optionsQuery->whereIn('id', $customPrices->keys());
                    }
                    // 4. إذا كانت القائمة فارغة ($customPrices->isEmpty())
                    // هذا يعني "السماح بكل الخيارات"

                    // 5. (مهم جداً) تحميل الترجمات بشكل مسبق لضمان عرض الاسم
                    $options = $optionsQuery->with('translations')->get();

                    // 6. أرجع الخيارات مع تطبيق السعر المخصص
                    return $options->map(function ($option) use ($customPrices, $customIsDefault) {

                        // احصل على السعر المخصص
                        $overridePrice = $customPrices->get($option->id);

                        $overrideIsDefault = $customIsDefault->get($option->id);

                        // (!!!) هذا هو التصحيح (!!!)
                        // إذا كان السعر المخصص موجوداً (وليس null)
                        if ($overridePrice !== null) {
                            // قم بتعديل السعر على الموديل الأصلي (في الذاكرة فقط)
                            // ** لا تستخدم replicate() **
                            $option->price = $overridePrice;
                        }

                        if ($overrideIsDefault !== null) {
                            // قم بتعديل السعر على الموديل الأصلي (في الذاكرة فقط)
                            // ** لا تستخدم replicate() **
                            $option->is_default = (bool) $overrideIsDefault;
                        }

                        // مرر الموديل الأصلي (الذي يحمل ID صحيح وترجمات محملة)
                        return new AddonOptionResource($option);
                    });
                },

                // الحالة الثانية: هذا الريسورس معروض بشكل مستقل
                function () {
                    // أرجع كل الخيارات كالمعتاد (مع تحميل الترجمات)
                    return AddonOptionResource::collection($this->whenLoaded('addonOptions', function() {
                        //
                        return $this->addonOptions()->with('translations')->get();
                    }));
                }
            )
            // ---== نهاية الكود المُصحح لـ options ==---
        ];
    }

    public static function allowedIncludes(): array
    {
        return [];
    }
}
