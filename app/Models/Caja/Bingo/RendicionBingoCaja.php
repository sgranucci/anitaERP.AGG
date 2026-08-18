<?php

namespace App\Models\Caja\Bingo;

use App\Models\Caja\Cuentacaja;
use App\Models\Configuracion\Empresa;
use App\Models\Contable\Asiento;
use App\Models\Seguridad\Usuario;
use App\Models\Ventas\Venta;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class RendicionBingoCaja extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'rendicion_bingo_caja';

    protected $fillable = [
        'codigo',
        'nro_oper_anita',
        'fuente_nro_oper',
        'anita_sincronizado_en',
        'empresa_id',
        'cuentacaja_id',
        'turno_operativo_bingo_id',
        'jornada_bingo_id',
        'creousuario_id',
        'fecharendicion',
        'fecha_jornada',
        'cant_cartones',
        'total_cartones',
        'saldo_final',
        'sobrante_faltante',
        'vales',
        'redondeo',
        'deposito',
        'refuerzo_prestamo',
        'cartones_json',
        'conceptos_json',
        'medios_contado_json',
        'observacion',
        'cerro_turno',
        'asiento_id',
        'venta_id',
        'asientos_cierre_ids_json',
        'cierre_contable_en',
        'cierre_contable_usuario_id',
        'factura_tipo',
        'factura_letra',
        'factura_sucursal',
        'factura_nro',
        'factura_fecha',
        'estado_facturacion',
    ];

    protected $casts = [
        'fecharendicion' => 'datetime',
        'fecha_jornada' => 'date',
        'anita_sincronizado_en' => 'datetime',
        'cant_cartones' => 'integer',
        'total_cartones' => 'float',
        'saldo_final' => 'float',
        'sobrante_faltante' => 'float',
        'vales' => 'float',
        'redondeo' => 'float',
        'deposito' => 'float',
        'refuerzo_prestamo' => 'float',
        'cartones_json' => 'array',
        'conceptos_json' => 'array',
        'medios_contado_json' => 'array',
        'cerro_turno' => 'boolean',
        'cierre_contable_en' => 'datetime',
        'asientos_cierre_ids_json' => 'array',
        'factura_fecha' => 'date',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function cuentacaja()
    {
        return $this->belongsTo(Cuentacaja::class, 'cuentacaja_id');
    }

    public function turnoOperativo()
    {
        return $this->belongsTo(TurnoOperativoBingo::class, 'turno_operativo_bingo_id');
    }

    public function jornada()
    {
        return $this->belongsTo(JornadaBingo::class, 'jornada_bingo_id');
    }

    public function creousuario()
    {
        return $this->belongsTo(Usuario::class, 'creousuario_id');
    }

    public function asiento()
    {
        return $this->belongsTo(Asiento::class, 'asiento_id');
    }

    public function venta()
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function cierreContableUsuario()
    {
        return $this->belongsTo(Usuario::class, 'cierre_contable_usuario_id');
    }

    public function tieneCierreContable(): bool
    {
        return (int) ($this->asiento_id ?? 0) > 0 && $this->cierre_contable_en !== null;
    }

    public function puedeCerrarContablemente(): bool
    {
        return ! $this->tieneCierreContable();
    }
}
