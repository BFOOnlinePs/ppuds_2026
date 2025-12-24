<?php

namespace Modules\Coupon\Interfaces;

interface Discountable
{
    public function getDiscountablePrice(): float;
}
