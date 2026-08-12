<?php

namespace App\Models\Compras;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;

class ConfiguracionPropuestaPago extends Model
{
    protected $table = 'configuracion_propuesta_pago';

    protected $fillable = [
        'empresa_id',
        'modo',
        'exige_arbol_aprobacion',
        'ejecutar_confirmada',
        'permite_op_sin_propuesta',
    ];

    protected $casts = [
        'exige_arbol_aprobacion' => 'boolean',
        'ejecutar_confirmada' => 'boolean',
        'permite_op_sin_propuesta' => 'boolean',
    ];

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }
}
