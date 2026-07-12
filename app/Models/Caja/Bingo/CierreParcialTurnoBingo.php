<?php

namespace App\Models\Caja\Bingo;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;

class CierreParcialTurnoBingo extends Model
{
    public $timestamps = false;

    protected $table = 'cierre_parcial_turno_bingo';

    protected $fillable = [
        'turno_operativo_bingo_id',
        'numero_parcial',
        'identificador_pc',
        'total_rendicion_turno',
        'totales_json',
        'usuario_id',
        'created_at',
    ];

    protected $casts = [
        'totales_json' => 'array',
        'total_rendicion_turno' => 'float',
        'created_at' => 'datetime',
    ];

    public function turnoOperativo()
    {
        return $this->belongsTo(TurnoOperativoBingo::class, 'turno_operativo_bingo_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
