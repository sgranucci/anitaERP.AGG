<?php

namespace App\Models\Compras;

use App\Models\Configuracion\Moneda;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Listaprecio_Proveedor extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'listaprecio_proveedor';

    protected $fillable = [
        'proveedor_id', 'fecha', 'nombre', 'observaciones', 'condicionpago_id', 'condicionentrega_id',
        'condicioncompra_id', 'moneda_id', 'estado', 'creousuario_id',
    ];

    public function listaprecio_proveedor_estados()
    {
        return $this->hasMany(Listaprecio_Proveedor_Estado::class, 'listaprecio_proveedor_id');
    }

    public function listaprecio_proveedor_articulos()
    {
        return $this->hasMany(Listaprecio_Proveedor_Articulo::class, 'listaprecio_proveedor_id');
    }

    public function listaprecio_proveedor_archivos()
    {
        return $this->hasMany(Listaprecio_Proveedor_Archivo::class, 'listaprecio_proveedor_id');
    }

    public function proveedores()
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function condicionpagos()
    {
        return $this->belongsTo(Condicionpago::class, 'condicionpago_id');
    }

    public function condicionentregas()
    {
        return $this->belongsTo(Condicionentrega::class, 'condicionentrega_id');
    }

    public function condicioncompras()
    {
        return $this->belongsTo(Condicioncompra::class, 'condicioncompra_id');
    }

    public function monedas()
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'creousuario_id');
    }
}
