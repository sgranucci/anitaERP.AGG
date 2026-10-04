<?php

declare(strict_types=1);

namespace App\Models\Finanzas;

use App\Models\Caja\Caja_Movimiento;
use App\Models\Caja\Cuentacaja;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Moneda;
use App\Models\Contable\Cuentacontable;
use App\Models\Seguridad\Usuario;
use App\Support\Finanzas\FinanzaMovimientoPrecargaRubro;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class FinanzaMovimientoPrecarga extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const ESTADO_ABIERTO = 'abierto';

    public const ESTADO_CONVERTIDO = 'convertido';

    protected $table = 'finanza_movimiento_precarga';

    protected $fillable = [
        'empresa_id',
        'fecha',
        'tipo',
        'rubro',
        'detalle',
        'cuentacaja_id',
        'cuentacaja_desde_id',
        'cuentacaja_hasta_id',
        'moneda_id',
        'monto',
        'cotizacion',
        'cuentacontable_contrapartida_id',
        'estado',
        'caja_movimiento_id',
        'usuario_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'monto' => 'decimal:2',
        'cotizacion' => 'decimal:6',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function cuentacaja(): BelongsTo
    {
        return $this->belongsTo(Cuentacaja::class, 'cuentacaja_id');
    }

    public function cuentacajaDesde(): BelongsTo
    {
        return $this->belongsTo(Cuentacaja::class, 'cuentacaja_desde_id');
    }

    public function cuentacajaHasta(): BelongsTo
    {
        return $this->belongsTo(Cuentacaja::class, 'cuentacaja_hasta_id');
    }

    public function moneda(): BelongsTo
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }

    public function cuentacontableContrapartida(): BelongsTo
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontable_contrapartida_id');
    }

    public function cajaMovimiento(): BelongsTo
    {
        return $this->belongsTo(Caja_Movimiento::class, 'caja_movimiento_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function movimientoRevertido(): bool
    {
        $mov = $this->cajaMovimiento;
        if ($mov === null) {
            return false;
        }

        return (int) ($mov->caja_movimiento_revertido_por_id ?? 0) > 0;
    }

    /**
     * Cerrada mientras el ingreso/egreso generado siga vigente.
     * Si ese comprobante se revierte, la precarga vuelve a editarse.
     */
    public function estaCerrada(): bool
    {
        if ($this->estado !== self::ESTADO_CONVERTIDO || (int) ($this->caja_movimiento_id ?? 0) <= 0) {
            return false;
        }

        return ! $this->movimientoRevertido();
    }

    public function etiquetaTipo(): string
    {
        return FinanzaMovimientoPrecargaRubro::tipoEtiqueta((string) $this->tipo);
    }

    public function etiquetaRubro(): string
    {
        return FinanzaMovimientoPrecargaRubro::etiqueta((string) $this->rubro);
    }

    public function etiquetaCuenta(): string
    {
        if ($this->tipo === 'transferencia') {
            return self::etiquetaCuentaModelo($this->cuentacajaDesde).' → '.self::etiquetaCuentaModelo($this->cuentacajaHasta);
        }

        return self::etiquetaCuentaModelo($this->cuentacaja);
    }

    public function etiquetaEstado(): string
    {
        if ($this->estaCerrada()) {
            return 'Contabilizada';
        }
        if ($this->estado === self::ESTADO_CONVERTIDO && $this->movimientoRevertido()) {
            return 'Reabierta';
        }

        return 'Abierta';
    }

    private static function etiquetaCuentaModelo(?Cuentacaja $cuenta): string
    {
        if ($cuenta === null) {
            return '—';
        }
        $codigo = trim((string) ($cuenta->codigo ?? ''));
        $nombre = trim((string) ($cuenta->nombre ?? ''));

        return trim($codigo.($codigo !== '' && $nombre !== '' ? ' · ' : '').$nombre);
    }
}
