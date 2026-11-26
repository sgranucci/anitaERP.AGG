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
 * Parámetros por empresa para «Generar solicitud de pago» de retenciones ARBA.
 */
class IibbSolicitudpagoConfig extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'iibb_solicitudpago_config';

    /** Valores sugeridos al crear la configuración (la pantalla los muestra y el operador los cambia). */
    public const PLANTILLA_DETALLE_DEFAULT = 'Retenciones ARBA {1Q|2Q} {MM.AAAA} | {EMP}';

    public const DIAS_DUPLICADO_DEFAULT = 45;

    public const DIAS_AVISO_PENDIENTE_DEFAULT = 90;

    /**
     * Diferencia entre el VEP que carga el usuario y el VEP de la liquidación
     * a partir de la cual se pide confirmación. Por debajo, los centavos no avisan.
     */
    public const TOLERANCIA_VEP = 1.0;

    protected $fillable = [
        'empresa_id',
        'proveedor_id',
        'sector_solicitudpago_id',
        'concepto_solicitudpago_id',
        'formapagosol_id',
        'moneda_id',
        'centrocosto_id',
        'cuentacontable_debe_id',
        'cuentacontable_banco_id',
        'plantilla_detalle',
        'dias_duplicado',
        'dias_aviso_pendiente',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'dias_duplicado' => 'integer',
        'dias_aviso_pendiente' => 'integer',
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

    public function cuentacontableDebe(): BelongsTo
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontable_debe_id');
    }

    public function cuentacontableBanco(): BelongsTo
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontable_banco_id');
    }

    public function configCompleta(): bool
    {
        return (int) $this->proveedor_id > 0
            && (int) $this->sector_solicitudpago_id > 0
            && (int) $this->concepto_solicitudpago_id > 0
            && (int) $this->formapagosol_id > 0
            && (int) $this->centrocosto_id > 0
            && (int) $this->cuentacontable_debe_id > 0
            && (int) $this->cuentacontable_banco_id > 0;
    }
}
