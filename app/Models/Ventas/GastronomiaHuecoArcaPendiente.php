<?php

declare(strict_types=1);

namespace App\Models\Ventas;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GastronomiaHuecoArcaPendiente extends Model
{
    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_ARCA_INDISPONIBLE = 'arca_indisponible';

    public const ESTADO_RECUPERABLE = 'recuperable';

    public const ESTADO_RESUELTO = 'resuelto';

    public const ESTADO_INEXISTENTE_ARCA = 'inexistente_arca';

    public const ESTADO_DESCARTADO = 'descartado';

    protected $table = 'gastronomia_hueco_arca_pendiente';

    protected $fillable = [
        'empresa_id',
        'fecha_jornada',
        'puntoventa_id',
        'numero_comprobante',
        'turno_operativo_gastronomia_id',
        'identificador_pc',
        'estado',
        'ultimo_error',
        'venta_factura_id',
        'venta_nc_id',
        'diagnosticado_en',
        'resuelto_en',
    ];

    protected $casts = [
        'fecha_jornada' => 'date',
        'diagnosticado_en' => 'datetime',
        'resuelto_en' => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function puntoventa(): BelongsTo
    {
        return $this->belongsTo(Puntoventa::class, 'puntoventa_id');
    }

    public function turnoOperativo(): BelongsTo
    {
        return $this->belongsTo(TurnoOperativoGastronomia::class, 'turno_operativo_gastronomia_id');
    }

    public function ventaFactura(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_factura_id');
    }

    public function ventaNc(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_nc_id');
    }
}
