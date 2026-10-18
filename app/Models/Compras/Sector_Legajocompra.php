<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Sector_Legajocompra extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $table = 'sector_legajocompra';

    protected $fillable = ['nombre'];
}
