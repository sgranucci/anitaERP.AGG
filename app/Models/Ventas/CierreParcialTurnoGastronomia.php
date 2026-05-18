<?php

namespace App\Models\Ventas;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class CierreParcialTurnoGastronomia extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public $timestamps = false;

    protected $table = 'cierre_parcial_turno_gastronomia';

    protected $fillable = [
        'turno_operativo_gastronomia_id',
        'numero_parcial',
        'identificador_pc',
        'total_facturacion_turno',
        'totales_json',
        'usuario_id',
        'created_at',
    ];

    protected $casts = [
        'totales_json' => 'array',
        'total_facturacion_turno' => 'float',
        'created_at' => 'datetime',
    ];

    public function turnoOperativo()
    {
        return $this->belongsTo(TurnoOperativoGastronomia::class, 'turno_operativo_gastronomia_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
