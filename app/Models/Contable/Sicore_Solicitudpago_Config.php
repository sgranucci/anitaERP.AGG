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

class Sicore_Solicitudpago_Config extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'sicore_solicitudpago_config';

    protected $fillable = [
        'empresa_id',
        'proveedor_id',
        'sector_solicitudpago_id',
        'concepto_solicitudpago_id',
        'formapagosol_id',
        'moneda_id',
        'centrocosto_id',
        'cuentacontable_217_id',
        'cuentacontable_767_id',
        'cuentacontable_787_id',
        'cuentacontable_banco_id',
        'activo',
    ];

    /** @var array<string, string> */
    protected $casts = [
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

    public function cuentacontable217(): BelongsTo
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontable_217_id');
    }

    public function cuentacontable767(): BelongsTo
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontable_767_id');
    }

    public function cuentacontable787(): BelongsTo
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontable_787_id');
    }

    public function cuentacontableBanco(): BelongsTo
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontable_banco_id');
    }

    public function configCompleta(): bool
    {
        return (int) $this->proveedor_id > 0
            && (int) $this->concepto_solicitudpago_id > 0
            && (int) $this->formapagosol_id > 0
            && (int) $this->sector_solicitudpago_id > 0
            && (int) $this->centrocosto_id > 0
            && (int) $this->cuentacontable_217_id > 0
            && (int) $this->cuentacontable_767_id > 0
            && (int) $this->cuentacontable_787_id > 0
            && (int) $this->cuentacontable_banco_id > 0;
    }

    /**
     * @return array<string, int> codigo impuesto => cuentacontable_id
     */
    public function mapaCuentasDebe(): array
    {
        return [
            '217' => (int) $this->cuentacontable_217_id,
            '767' => (int) $this->cuentacontable_767_id,
            '787' => (int) $this->cuentacontable_787_id,
        ];
    }
}
