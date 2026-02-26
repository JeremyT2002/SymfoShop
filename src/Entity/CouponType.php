<?php

namespace App\Entity;

enum CouponType: string
{
    case PERCENTAGE = 'percentage';
    case FIXED = 'fixed';
}

