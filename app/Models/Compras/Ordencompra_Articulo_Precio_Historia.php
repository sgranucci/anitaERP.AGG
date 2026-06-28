<?php

namespace App\Models\Compras;

use App\Models\Seguridad\Usuario;
use App\Models\Stock\Articulo;
use App\Models\Stock\Recepcion_Proveedor;
use App\Models\Stock\Recepcion_Proveedor_Articulo;
use Illuminate\Database\Eloquent\Model;

class Ordencompra_Articulo_Precio_Historia extends Model
{
    protected $table = 'ordencompra_articulo_precio_historia';

    protected $fillable = [
        'ordencompra_id',
        'ordencompra_articulo_id',
        'articulo_id',
        'precio_anterior',
        'precio_nuevo',
        'recepcion_proveedor_id',
        'recepcion_proveedor_articulo_id',
        'origen',
        'comentario',
        'usuario_id',
        'fecha',
    ];

    protected $casts = [
        'precio_anterior' => 'float',
        'precio_nuevo' => 'float',
        'fecha' => 'datetime',
    ];

    public function ordencompras()
    {
        return $this->belongsTo(Ordencompra::class, 'ordencompra_id');
    }

    public function ordencompra_articulos()
    {
        return $this->belongsTo(Ordencompra_Articulo::class, 'ordencompra_articulo_id');
    }

    public function articulos()
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }

    public function recepcion_proveedores()
    {
        return $this->belongsTo(Recepcion_Proveedor::class, 'recepcion_proveedor_id');
    }

    public function recepcion_proveedor_articulos()
    {
        return $this->belongsTo(Recepcion_Proveedor_Articulo::class, 'recepcion_proveedor_articulo_id');
    }

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
