<?php

namespace App\Models\Caja\Bingo;

use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class TurnoOperativoBingo extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const ESTADO_HABILITADO = 'habilitado';

    public const ESTADO_CERRADO = 'cerrado';

    protected $table = 'turno_operativo_bingo';

    protected $fillable = [
        'empresa_id',
        'jornada_bingo_id',
        'turno_bingo_id',
        'configuracion_puntoventa_bingo_id',
        'identificador_pc',
        'estado',
        'usuario_habilitacion_id',
        'usuario_habilitado_id',
        'monto_habilitacion',
        'observacion_habilitacion',
        'habilitacion_en',
        'usuario_cierre_id',
        'cierre_en',
        'numero_cierre',
        'monto_rendicion_turno',
        'monto_rendicion_dia',
        'redondeo',
        'sobrante_faltante',
        'vales',
        'deposito',
        'medios_contado_cierre_json',
        'cartones_rendicion_json',
        'conceptos_rendicion_json',
        'rendicion_presentada',
        'observacion_cierre',
    ];

    protected $casts = [
        'habilitacion_en' => 'datetime',
        'cierre_en' => 'datetime',
        'numero_cierre' => 'integer',
        'monto_habilitacion' => 'float',
        'monto_rendicion_turno' => 'float',
        'monto_rendicion_dia' => 'float',
        'redondeo' => 'float',
        'sobrante_faltante' => 'float',
        'vales' => 'float',
        'deposito' => 'float',
        'medios_contado_cierre_json' => 'array',
        'cartones_rendicion_json' => 'array',
        'conceptos_rendicion_json' => 'array',
        'rendicion_presentada' => 'boolean',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function jornada()
    {
        return $this->belongsTo(JornadaBingo::class, 'jornada_bingo_id');
    }

    public function turno()
    {
        return $this->belongsTo(TurnoBingo::class, 'turno_bingo_id');
    }

    public function configuracionPuntoventa()
    {
        return $this->belongsTo(ConfiguracionPuntoventaBingo::class, 'configuracion_puntoventa_bingo_id');
    }

    public function usuarioHabilitacion()
    {
        return $this->belongsTo(Usuario::class, 'usuario_habilitacion_id');
    }

    public function usuarioHabilitado()
    {
        return $this->belongsTo(Usuario::class, 'usuario_habilitado_id');
    }

    public function usuarioCierre()
    {
        return $this->belongsTo(Usuario::class, 'usuario_cierre_id');
    }

    public function cierresParciales()
    {
        return $this->hasMany(CierreParcialTurnoBingo::class, 'turno_operativo_bingo_id')
            ->orderBy('numero_parcial');
    }
}
