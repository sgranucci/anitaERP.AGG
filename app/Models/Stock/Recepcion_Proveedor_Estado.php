<?php

namespace App\Models\Stock;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Recepcion_Proveedor_Estado extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $table = 'recepcion_proveedor_estado';

    protected $fillable = [
        'recepcion_proveedor_id', 'estado', 'fecha', 'usuario_id', 'observacion',
    ];

    protected $casts = [
        'fecha' => 'datetime',
    ];

    public function recepcion_proveedores()
    {
        return $this->belongsTo(Recepcion_Proveedor::class, 'recepcion_proveedor_id');
    }

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
