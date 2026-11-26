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
 * Configuración de las solicitudes de pago del canon entidades de bien público (F2015), por empresa.
 */
class Canon_Entidades_Config extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const PLANTILLA_MAQUINAS_DEFAULT = 'ENTIDADES {EMP} MAQ {MM.AA}';

    public const PLANTILLA_BINGO_DEFAULT = 'ENTIDADES {EMP} BINGO {MM.AA}';

    protected $table = 'canon_entidades_config';

    protected $fillable = [
        'empresa_id',
        'proveedor_id',
        'sector_solicitudpago_id',
        'concepto_maquinas_solicitudpago_id',
        'concepto_bingo_solicitudpago_id',
        'formapagosol_id',
        'moneda_id',
        'centrocosto_id',
        'cuentacontable_debe_id',
        'cuentacontable_haber_id',
        'plantilla_maquinas',
        'plantilla_bingo',
        'activo',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'empresa_id' => 'integer',
        'proveedor_id' => 'integer',
        'sector_solicitudpago_id' => 'integer',
        'concepto_maquinas_solicitudpago_id' => 'integer',
        'concepto_bingo_solicitudpago_id' => 'integer',
        'formapagosol_id' => 'integer',
        'moneda_id' => 'integer',
        'centrocosto_id' => 'integer',
        'cuentacontable_debe_id' => 'integer',
        'cuentacontable_haber_id' => 'integer',
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

    public function conceptoMaquinas(): BelongsTo
    {
        return $this->belongsTo(Concepto_Solicitudpago::class, 'concepto_maquinas_solicitudpago_id');
    }

    public function conceptoBingo(): BelongsTo
    {
        return $this->belongsTo(Concepto_Solicitudpago::class, 'concepto_bingo_solicitudpago_id');
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

    public function cuentacontableDebe(): BelongsTo
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontable_debe_id');
    }

    public function cuentacontableHaber(): BelongsTo
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontable_haber_id');
    }

    public function configCompletaParaSolicitud(): bool
    {
        return (int) ($this->proveedor_id ?? 0) > 0
            && (int) ($this->sector_solicitudpago_id ?? 0) > 0
            && (int) ($this->concepto_maquinas_solicitudpago_id ?? 0) > 0
            && (int) ($this->concepto_bingo_solicitudpago_id ?? 0) > 0
            && (int) ($this->formapagosol_id ?? 0) > 0
            && (int) ($this->moneda_id ?? 0) > 0
            && (int) ($this->centrocosto_id ?? 0) > 0
            && (int) ($this->cuentacontable_debe_id ?? 0) > 0
            && (int) ($this->cuentacontable_haber_id ?? 0) > 0
            && trim((string) ($this->plantilla_maquinas ?? '')) !== ''
            && trim((string) ($this->plantilla_bingo ?? '')) !== '';
    }
}
