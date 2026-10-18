<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use App\ApiAnita;
use OwenIt\Auditing\Contracts\Auditable;

class Cliente_Comision extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
}
