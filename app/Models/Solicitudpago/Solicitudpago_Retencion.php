<?php

namespace App\Models\Solicitudpago;

use App\Models\Compras\Pagoproveedor_Retencion;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Solicitudpago_Retencion extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'solicitudpago_retencion';

    protected $fillable = [
        'solicitudpago_id',
        'tiporetencion',
        'retencionganancia_id',
        'retencioniva_id',
        'retencionsuss_id',
        'provincia_id',
        'codigo_regimen',
        'codigo_retencion',
        'base_calculo',
        'alicuota',
        'importe',
        'detalle_calculo',
        'motivo',
    ];

    protected $casts = [
        'solicitudpago_id' => 'integer',
        'retencionganancia_id' => 'integer',
        'retencioniva_id' => 'integer',
        'retencionsuss_id' => 'integer',
        'provincia_id' => 'integer',
        'base_calculo' => 'float',
        'alicuota' => 'float',
        'importe' => 'float',
        'detalle_calculo' => 'array',
    ];

    public function solicitudpagos()
    {
        return $this->belongsTo(Solicitudpago::class, 'solicitudpago_id');
    }

    public function etiquetaTipo(): string
    {
        return match ((string) $this->tiporetencion) {
            Pagoproveedor_Retencion::TIPO_GANANCIAS => 'Ganancias',
            Pagoproveedor_Retencion::TIPO_IVA => 'IVA',
            Pagoproveedor_Retencion::TIPO_SUSS => 'SUSS',
            Pagoproveedor_Retencion::TIPO_IIBB => 'IIBB',
            default => (string) $this->tiporetencion,
        };
    }
}
