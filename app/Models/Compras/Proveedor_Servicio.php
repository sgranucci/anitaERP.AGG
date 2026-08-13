<?php

namespace App\Models\Compras;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Proveedor_Servicio extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'proveedor_servicio';

    protected $fillable = [
        'proveedor_id',
        'empresa_id',
        'cliente',
        'detalle',
    ];

    public function proveedores()
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id', 'id');
    }

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }
}
