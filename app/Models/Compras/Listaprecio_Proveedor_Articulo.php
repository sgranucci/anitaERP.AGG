<?php

namespace App\Models\Compras;

use App\Models\Seguridad\Usuario;
use App\Models\Stock\Articulo;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Listaprecio_Proveedor_Articulo extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'listaprecio_proveedor_articulo';

    protected $fillable = [
        'listaprecio_proveedor_id', 'articulo_id', 'precio', 'codigo_articulo_proveedor', 'descuento',
        'fechavigencia', 'usuarioultcambio_id',
    ];

    public function listaprecio_proveedores()
    {
        return $this->belongsTo(Listaprecio_Proveedor::class, 'listaprecio_proveedor_id');
    }

    public function articulos()
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }

    public function usuarioultcambio()
    {
        return $this->belongsTo(Usuario::class, 'usuarioultcambio_id');
    }
}
