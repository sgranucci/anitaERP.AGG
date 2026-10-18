<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class TipoempresaCliente extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['nombre', 'codigo'];

    protected $table = 'tipoempresa_cliente';
}
