<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;

class TipoempresaCliente extends Model
{
    protected $fillable = ['nombre', 'codigo'];

    protected $table = 'tipoempresa_cliente';
}
