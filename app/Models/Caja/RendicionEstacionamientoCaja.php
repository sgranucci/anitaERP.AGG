<?php

namespace App\Models\Caja;

use App\Models\Caja\Estacionamiento\JornadaEstacionamiento;
use App\Models\Caja\Estacionamiento\TurnoOperativoEstacionamiento;
use App\Models\Configuracion\Empresa;
use App\Models\Contable\Asiento;
use App\Models\Seguridad\Usuario;
use App\Models\Ventas\Puntoventa;
use Illuminate\Database\Eloquent\Model;

class RendicionEstacionamientoCaja extends Model
{
    public const TIPO_TURNO = 'turno';

    public const TIPO_JORNADA = 'jornada';

    protected $table = 'rendicion_estacionamiento_caja';

    protected $fillable = [
        'tipo',
        'codigo',
        'nro_oper_anita',
        'fuente_nro_oper',
        'anita_sincronizado_en',
        'empresa_id',
        'puntoventa_cae_id',
        'puntoventa_caea_id',
        'caja_id',
        'creousuario_id',
        'fecharendicion',
        'iniciodelfondo',
        'totalfactura',
        'totalcobrado',
        'totalinvitacion',
        'totalnotacredito',
        'totalredondeo',
        'totalredondeoinvitacion',
        'sobrantefaltante',
        'turno_operativo_estacionamiento_id',
        'jornada_estacionamiento_id',
        'numeracion_comprobantes_json',
        'observacion',
        'asiento_id',
        'cierre_contable_en',
        'cierre_contable_usuario_id',
        'cierre_contable_legacy',
    ];

    protected $casts = [
        'nro_oper_anita' => 'integer',
        'numeracion_comprobantes_json' => 'array',
        'anita_sincronizado_en' => 'datetime',
        'fecharendicion' => 'datetime',
        'iniciodelfondo' => 'float',
        'totalfactura' => 'float',
        'totalcobrado' => 'float',
        'totalinvitacion' => 'float',
        'totalnotacredito' => 'float',
        'totalredondeo' => 'float',
        'totalredondeoinvitacion' => 'float',
        'sobrantefaltante' => 'float',
        'cierre_contable_en' => 'datetime',
        'cierre_contable_legacy' => 'boolean',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function puntoventaCae()
    {
        return $this->belongsTo(Puntoventa::class, 'puntoventa_cae_id');
    }

    public function puntoventaCaea()
    {
        return $this->belongsTo(Puntoventa::class, 'puntoventa_caea_id');
    }

    public function caja()
    {
        return $this->belongsTo(Caja::class, 'caja_id');
    }

    public function creousuario()
    {
        return $this->belongsTo(Usuario::class, 'creousuario_id');
    }

    public function turnoOperativo()
    {
        return $this->belongsTo(TurnoOperativoEstacionamiento::class, 'turno_operativo_estacionamiento_id');
    }

    public function jornada()
    {
        return $this->belongsTo(JornadaEstacionamiento::class, 'jornada_estacionamiento_id');
    }

    public function esRendicionJornada(): bool
    {
        return $this->tipo === self::TIPO_JORNADA;
    }

    public function esRendicionTurno(): bool
    {
        return $this->tipo === self::TIPO_TURNO || $this->tipo === null || $this->tipo === '';
    }

    public function movimientos()
    {
        return $this->hasMany(RendicionEstacionamientoMovimientoCaja::class, 'rendicion_estacionamiento_caja_id');
    }

    public function asiento()
    {
        return $this->belongsTo(Asiento::class, 'asiento_id');
    }

    public function cierreContableUsuario()
    {
        return $this->belongsTo(Usuario::class, 'cierre_contable_usuario_id');
    }

    public function tieneCierreContable(): bool
    {
        if ((bool) ($this->cierre_contable_legacy ?? false)) {
            return true;
        }

        return (int) ($this->asiento_id ?? 0) > 0 && $this->cierre_contable_en !== null;
    }

    public function esCierreContableLegacy(): bool
    {
        return (bool) ($this->cierre_contable_legacy ?? false);
    }

    public function puedeCerrarContablemente(): bool
    {
        return $this->esRendicionTurno() && ! $this->tieneCierreContable();
    }
}
