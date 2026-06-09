<?php

namespace App\Models\Caja\Estacionamiento;

use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class TurnoOperativoEstacionamiento extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const ESTADO_HABILITADO = 'habilitado';

    public const ESTADO_CERRADO = 'cerrado';

    protected $table = 'turno_operativo_estacionamiento';

    protected $fillable = [
        'empresa_id',
        'jornada_estacionamiento_id',
        'turno_estacionamiento_id',
        'configuracion_puntoventa_estacionamiento_id',
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
        'monto_facturacion_turno',
        'monto_facturacion_dia',
        'redondeo_invitaciones',
        'redondeo_turno',
        'sobrante_faltante',
        'medios_contado_cierre_json',
        'observacion_cierre',
    ];

    protected $casts = [
        'habilitacion_en' => 'datetime',
        'cierre_en' => 'datetime',
        'numero_cierre' => 'integer',
        'monto_habilitacion' => 'float',
        'monto_facturacion_turno' => 'float',
        'monto_facturacion_dia' => 'float',
        'redondeo_invitaciones' => 'float',
        'redondeo_turno' => 'float',
        'sobrante_faltante' => 'float',
        'medios_contado_cierre_json' => 'array',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function jornada()
    {
        return $this->belongsTo(JornadaEstacionamiento::class, 'jornada_estacionamiento_id');
    }

    public function turno()
    {
        return $this->belongsTo(TurnoEstacionamiento::class, 'turno_estacionamiento_id');
    }

    public function configuracionPuntoventa()
    {
        return $this->belongsTo(ConfiguracionPuntoventaEstacionamiento::class, 'configuracion_puntoventa_estacionamiento_id');
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
        return $this->hasMany(CierreParcialTurnoEstacionamiento::class, 'turno_operativo_estacionamiento_id')
            ->orderBy('numero_parcial');
    }

    public function tickets()
    {
        return $this->hasMany(TicketEstacionamiento::class, 'turno_operativo_estacionamiento_id');
    }
}
