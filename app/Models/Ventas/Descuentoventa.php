<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;
use App\Traits\Ventas\DescuentoventaTrait;

class Descuentoventa extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use DescuentoventaTrait;

    protected $fillable = ['nombre', 'tipodescuento', 'porcentajedescuento', 'montodescuento', 'cantidadventa', 'cantidaddescuento', 'estado'];
    protected $table = 'descuentoventa';
}

