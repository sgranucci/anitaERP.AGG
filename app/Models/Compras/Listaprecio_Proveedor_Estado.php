<?php

namespace App\Models\Compras;

use App\Models\Seguridad\Usuario;
use App\Traits\Compras\Listaprecio_Proveedor_EstadoTrait;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Listaprecio_Proveedor_Estado extends Model implements Auditable
{
    use Listaprecio_Proveedor_EstadoTrait;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'listaprecio_proveedor_estado';

    protected $fillable = ['listaprecio_proveedor_id', 'estado', 'usuario_id', 'observacion'];

    public function listaprecio_proveedores()
    {
        return $this->belongsTo(Listaprecio_Proveedor::class, 'listaprecio_proveedor_id');
    }

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
