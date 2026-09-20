<?php

namespace App\Models\Compras;

use App\Models\Configuracion\Moneda;
use App\Models\Configuracion\Provincia;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Auditable: la migración 2026_08_18_153000 quitó los softdeletes de las tablas de proceso
 * con el criterio "baja física + audits", pero la mitad de audits nunca se implementó acá.
 * Sin esto, borrar una retención no dejaba rastro de un certificado ya emitido al proveedor.
 */
class Pagoproveedor_Retencion extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const TIPO_GANANCIAS = 'G';

    public const TIPO_IVA = 'I';

    public const TIPO_SUSS = 'S';

    public const TIPO_IIBB = 'B';

    protected $table = 'pagoproveedor_retencion';

    protected $fillable = [
        'pagoproveedor_id', 'tiporetencion', 'retencionganancia_id', 'retencioniva_id',
        'retencionsuss_id', 'provincia_id', 'codigo_regimen', 'codigo_retencion',
        'base_calculo', 'alicuota', 'importe', 'nro_certificado', 'moneda_id',
        'cotizacion', 'detalle_calculo', 'motivo',
    ];

    protected $casts = [
        'detalle_calculo' => 'array',
        'base_calculo' => 'float',
        'alicuota' => 'float',
        'importe' => 'float',
        'cotizacion' => 'float',
    ];

    public function pagoproveedores()
    {
        return $this->belongsTo(Pagoproveedor::class, 'pagoproveedor_id');
    }

    public function retencionganancias()
    {
        return $this->belongsTo(Retencionganancia::class, 'retencionganancia_id');
    }

    public function retencionivas()
    {
        return $this->belongsTo(Retencioniva::class, 'retencioniva_id');
    }

    public function retencionsusss()
    {
        return $this->belongsTo(Retencionsuss::class, 'retencionsuss_id');
    }

    public function provincias()
    {
        return $this->belongsTo(Provincia::class, 'provincia_id');
    }

    public function monedas()
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }

    public function etiquetaTipo(): string
    {
        return match ($this->tiporetencion) {
            self::TIPO_GANANCIAS => 'Ganancias',
            self::TIPO_IVA => 'IVA',
            self::TIPO_SUSS => 'SUSS',
            self::TIPO_IIBB => 'IIBB',
            default => $this->tiporetencion,
        };
    }
}
