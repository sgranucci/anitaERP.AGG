<?php

namespace App\Queries\Contable;

interface AsientoQueryInterface
{
    public function first();
    public function all();
    public function allQuery(array $campos);
}

