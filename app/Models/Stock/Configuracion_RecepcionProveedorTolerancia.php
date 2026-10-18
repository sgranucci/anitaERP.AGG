<?php

namespace App\Models\Stock;

use App\Models\Configuracion\Empresa;
use App\Models\Contable\Centrocosto;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Configuracion_RecepcionProveedorTolerancia extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $table = 'configuracion_recepcion_proveedor_tolerancia';

    protected $fillable = [
        'empresa_id', 'centrocosto_id', 'tolerancia_cantidad_pct',
        'tolerancia_precio_pct', 'tolerancia_precio_absoluto', 'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function centrocostos()
    {
        return $this->belongsTo(Centrocosto::class, 'centrocosto_id');
    }
}
