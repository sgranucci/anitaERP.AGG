<?php

namespace App\Models\Caja\Estacionamiento;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class CategoriaAutomovil extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'categoria_automovil_estacionamiento';

    protected $fillable = ['nombre'];
}
