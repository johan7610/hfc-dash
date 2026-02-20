<?php

namespace App\Services\SaleProbability\Support;

class Sigmoid
{
    public static function compute(float $x): float
    {
        return 1.0 / (1.0 + exp(-$x));
    }
}
