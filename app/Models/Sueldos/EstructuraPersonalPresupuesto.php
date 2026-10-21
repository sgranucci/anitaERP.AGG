<?php

namespace App\Models\Sueldos;

use App\Models\Configuracion\Empresa;
use App\Models\Contable\Centrocosto;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class EstructuraPersonalPresupuesto extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'estructura_personal_presupuesto';

    protected $fillable = [
        'anio',
        'empresa_id',
        'centrocosto_id',
        'cantidad',
    ];

    protected $casts = [
        'anio' => 'integer',
        'empresa_id' => 'integer',
        'centrocosto_id' => 'integer',
        'cantidad' => 'integer',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function centrocosto()
    {
        return $this->belongsTo(Centrocosto::class, 'centrocosto_id');
    }
}
