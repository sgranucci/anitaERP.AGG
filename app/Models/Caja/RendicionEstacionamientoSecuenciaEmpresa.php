<?php

namespace App\Models\Caja;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;

class RendicionEstacionamientoSecuenciaEmpresa extends Model
{
    protected $table = 'rendicion_estacionamiento_secuencia_empresa';

    protected $primaryKey = 'empresa_id';

    public $incrementing = false;

    protected $fillable = [
        'empresa_id',
        'ultimo_nro_anita',
        'ultimo_nro_erp',
        'proximo_nro',
        'consultado_anita_en',
    ];

    protected $casts = [
        'ultimo_nro_anita' => 'integer',
        'ultimo_nro_erp' => 'integer',
        'proximo_nro' => 'integer',
        'consultado_anita_en' => 'datetime',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }
}
