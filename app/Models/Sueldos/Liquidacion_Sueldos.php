<?php

namespace App\Models\Sueldos;

use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Cabecera de la corrida de liquidacion (payroll run). Origen Anita: maeliq.
 */
class Liquidacion_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'liquidacion_sueldos';

    protected $fillable = [
        'empresa_id',
        'numero',
        'descripcion',
        'tipo',
        'motivoegreso_id',
        'periodo',
        'periodo_anio',
        'periodo_mes',
        'periodo_desde',
        'periodo_hasta',
        'fecha_liquidacion',
        'fecha_pago',
        'lugar_pago',
        'estado',
        'simulacion',
        'acumula_novedades',
        'alcance',
        'filtros_json',
        'banco_deposito',
        'periodo_deposito',
        'fecha_ultimo_deposito',
        'cantidad_recibos',
        'total_bruto',
        'total_remunerativo',
        'total_no_remunerativo',
        'total_descuentos',
        'total_neto',
        'contabilizado',
        'asiento_id',
        'fecha_contabilizacion',
        'fecha_calculo',
        'fecha_cierre',
        'usuario_id',
        'usuario_cierre_id',
        'observacion',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'numero' => 'integer',
        'motivoegreso_id' => 'integer',
        'periodo_anio' => 'integer',
        'periodo_mes' => 'integer',
        'periodo_desde' => 'date',
        'periodo_hasta' => 'date',
        'fecha_liquidacion' => 'date',
        'fecha_pago' => 'date',
        'simulacion' => 'boolean',
        'acumula_novedades' => 'boolean',
        'fecha_ultimo_deposito' => 'date',
        'cantidad_recibos' => 'integer',
        'total_bruto' => 'decimal:2',
        'total_remunerativo' => 'decimal:2',
        'total_no_remunerativo' => 'decimal:2',
        'total_descuentos' => 'decimal:2',
        'total_neto' => 'decimal:2',
        'contabilizado' => 'boolean',
        'asiento_id' => 'integer',
        'fecha_contabilizacion' => 'datetime',
        'fecha_calculo' => 'datetime',
        'fecha_cierre' => 'datetime',
        'usuario_id' => 'integer',
        'usuario_cierre_id' => 'integer',
    ];

    public const ESTADOS = [
        'borrador' => 'Borrador',
        'calculada' => 'Calculada',
        'revisada' => 'Revisada',
        'cerrada' => 'Cerrada',
        'contabilizada' => 'Contabilizada',
        'pagada' => 'Pagada',
        'anulada' => 'Anulada',
    ];

    public const TIPOS = [
        'mensual' => 'Mensual',
        'quincena_1' => '1ra quincena',
        'quincena_2' => '2da quincena',
        'sac' => 'SAC / Aguinaldo',
        'vacaciones' => 'Vacaciones',
        'final' => 'Liquidacion final',
        'complementaria' => 'Complementaria',
        'ajuste' => 'Ajuste / Retroactivo',
        'gratificacion' => 'Gratificacion',
        'no_remunerativo' => 'No remunerativo',
        'especial' => 'Especial',
    ];

    // Estados en los que la cabecera aun puede editarse / recalcularse.
    public const ESTADOS_EDITABLES = ['borrador', 'calculada', 'revisada'];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function motivoegreso()
    {
        return $this->belongsTo(Motivoegreso_Sueldos::class, 'motivoegreso_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function usuarioCierre()
    {
        return $this->belongsTo(Usuario::class, 'usuario_cierre_id');
    }

    public function recibos()
    {
        return $this->hasMany(Liquidacion_Recibo_Sueldos::class, 'liquidacion_id');
    }

    public function detalles()
    {
        return $this->hasMany(Liquidacion_Detalle_Sueldos::class, 'liquidacion_id');
    }

    public function estadoLabel(): string
    {
        return self::ESTADOS[$this->estado] ?? (string) $this->estado;
    }

    public function tipoLabel(): string
    {
        return self::TIPOS[$this->tipo] ?? (string) $this->tipo;
    }

    public function esEditable(): bool
    {
        return in_array($this->estado, self::ESTADOS_EDITABLES, true);
    }

    public function estaCerrada(): bool
    {
        return in_array($this->estado, ['cerrada', 'contabilizada', 'pagada'], true);
    }
}
