<?php

declare(strict_types=1);

namespace App\Models\Contable;

use App\Models\Compras\Proveedor;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Moneda;
use App\Models\Solicitudpago\Concepto_Solicitudpago;
use App\Models\Solicitudpago\Formapagosol;
use App\Models\Solicitudpago\Sector_Solicitudpago;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Configuración SUSS por empresa: sigla del detalle, solicitud de pago y régimen 748 → 754.
 */
class Suss_Empresa_Config extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'suss_empresa_config';

    protected $fillable = [
        'empresa_id',
        'sigla',
        'proveedor_id',
        'sector_solicitudpago_id',
        'concepto_solicitudpago_id',
        'formapagosol_id',
        'moneda_id',
        'centrocosto_id',
        'cuentacontable_suss_id',
        'cuentacontable_banco_id',
        'exportar_regimen_748_como_754',
        'activo',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'exportar_regimen_748_como_754' => 'boolean',
        'activo' => 'boolean',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function sectorSolicitudpago(): BelongsTo
    {
        return $this->belongsTo(Sector_Solicitudpago::class, 'sector_solicitudpago_id');
    }

    public function conceptoSolicitudpago(): BelongsTo
    {
        return $this->belongsTo(Concepto_Solicitudpago::class, 'concepto_solicitudpago_id');
    }

    public function formapagosol(): BelongsTo
    {
        return $this->belongsTo(Formapagosol::class, 'formapagosol_id');
    }

    public function moneda(): BelongsTo
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }

    public function centrocosto(): BelongsTo
    {
        return $this->belongsTo(Centrocosto::class, 'centrocosto_id');
    }

    public function cuentacontableSuss(): BelongsTo
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontable_suss_id');
    }

    public function cuentacontableBanco(): BelongsTo
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontable_banco_id');
    }

    public static function paraEmpresa(int $empresaId): ?self
    {
        if ($empresaId <= 0) {
            return null;
        }

        return static::query()->where('empresa_id', $empresaId)->first();
    }

    public static function exportaRegimen748Como754(int $empresaId): bool
    {
        if ($empresaId <= 0) {
            return false;
        }

        return (bool) static::query()
            ->where('empresa_id', $empresaId)
            ->where('exportar_regimen_748_como_754', true)
            ->exists();
    }

    public function configSolicitudCompleta(): bool
    {
        return (int) $this->proveedor_id > 0
            && (int) $this->concepto_solicitudpago_id > 0
            && (int) $this->formapagosol_id > 0
            && (int) $this->sector_solicitudpago_id > 0
            && (int) $this->moneda_id > 0
            && (int) $this->centrocosto_id > 0
            && (int) $this->cuentacontable_suss_id > 0
            && (int) $this->cuentacontable_banco_id > 0;
    }
}
