<?php

namespace Spatie\LaravelFlare\Tests\stubs\Models;

class Car
{
    public $brand;
    public $color;

    public function __construct($brand, $color)
    {
        $this->brand = $brand;
        $this->color = $color;
    }
}
