<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use App\Models\Seguridad\Usuario;
use OwenIt\Auditing\Contracts\Auditable;

class Pedido_Articulo_Caja extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = ['pedido_id', 'pedido_articulo_id', 'numerocaja', 'pieza', 'kilo', 'lote', 'fechavencimiento', 'creousuario_id'];
    protected $table = 'pedido_articulo_caja';

    public function pedido_articulos()
    {
        return $this->belongsTo(Pedido_Articulo::class, 'pedido_articulo_id', 'id')->with('articulos');
    }

    public function creousuarios()
    {
        return $this->belongsTo(Usuario::class, 'creousuario_id');
    }
}

