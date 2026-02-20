<?php

namespace App\Services\SaleProbability\Support;

class Clamp
{
    public static function between(float $v, float $min, float $max): float
    {
        return max($min, min($v, $max));
    }
}
