<?php

namespace App\Models\Compras;

use App\Models\Configuracion\Empresa;
use App\Models\Contable\Centrocosto;
use Illuminate\Database\Eloquent\Model;

class Configuracion_ComprobanteProveedorTolerancia extends Model
{
    protected $table = 'configuracion_comprobante_proveedor_tolerancia';

    protected $fillable = [
        'empresa_id',
        'centrocosto_id',
        'tolerancia_importe_pct',
        'tolerancia_exceso_pct',
        'tolerancia_defecto_pct',
        'tolerancia_exceso_abs',
        'tolerancia_defecto_abs',
        'accion_fuera_tolerancia',
        'activo',
    ];

    // Los límites por sentido no se castean a float: null significa «no verificar ese sentido»
    // y un cast lo convertiría en 0, que es un límite válido y muy distinto.
    protected $casts = [
        'tolerancia_importe_pct' => 'float',
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
