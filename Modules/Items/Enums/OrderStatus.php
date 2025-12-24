<?php

namespace Modules\Items\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Modules\Core\View\Components\Dashboard\search;

enum OrderStatus: int implements HasLabel, HasColor
{
    case PENDING            = 1; // قيد الانتظار
    case CONFIRMED          = 2; // تم التأكيد
    case PREPARING          = 3; // قيد التحضير
    case READY_FOR_PICKUP   = 4; // جاهز للاستلام
    case OUT_FOR_DELIVERY   = 5; // جاري التوصيل
    case DELIVERED          = 6; // تم التسليم
    case CANCELLED          = 7; // ملغي
    case REJECTED           = 8; // مرفوض
    case RETURNED           = 9; // مرتجع

    /**
     * Provides a translatable label for each status.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING          => __('Pending'),
            self::CONFIRMED        => __('Confirmed'),
            self::PREPARING        => __('Preparing'),
            self::OUT_FOR_DELIVERY => __('Out for Delivery'),
            self::DELIVERED        => __('Delivered'),
            self::CANCELLED        => __('Cancelled'),
            self::READY_FOR_PICKUP => __('Ready for Pickup'),
            self::REJECTED         => __('Rejected'),
            self::RETURNED         => __('Returned'),
        };
    }

    /**
     * Provides a color for each status, compatible with Filament's color palette.
     */
    public function getColor(): string
    {
        return match ($this) {
            self::PENDING          => 'warning',
            self::CONFIRMED        => 'info',
            self::PREPARING        => 'primary',
            self::OUT_FOR_DELIVERY => 'secondary',
            self::DELIVERED        => 'success',
            self::CANCELLED        => 'danger',
            self::READY_FOR_PICKUP => 'success',
            self::REJECTED         => 'danger',
            self::RETURNED         => 'danger',
        };
    }

    /**
     * Generates an array of options for use in Filament Select fields.
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn(self $case) => [$case->value => $case->getLabel()])
            ->toArray();
    }

    public function canTransitionTo(self $newStatus): bool
    {
        if ($this === $newStatus) return true;

        if (in_array($this, [
            self::CANCELLED,
            self::REJECTED,
            self::RETURNED,
            self::DELIVERED
        ])) {
            return false;
        }

        $transitions = [
            self::PENDING->value          => [self::CONFIRMED->value, self::REJECTED->value, self::CANCELLED->value],
            self::CONFIRMED->value        => [self::PREPARING->value],
            self::PREPARING->value        => [self::READY_FOR_PICKUP->value, self::OUT_FOR_DELIVERY->value],
            self::READY_FOR_PICKUP->value => [self::OUT_FOR_DELIVERY->value, self::DELIVERED->value],
            self::OUT_FOR_DELIVERY->value => [self::RETURNED->value],
        ];

        return in_array($newStatus->value, $transitions[$this->value] ?? []);
    }
}
