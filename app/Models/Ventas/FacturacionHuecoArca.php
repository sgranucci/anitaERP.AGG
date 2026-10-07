<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;

class FacturacionHuecoArca extends Model
{
    public const CARGADO = 'cargado';

    public const INEXISTENTE = 'inexistente';

    public const OMITIDO = 'omitido';

    public const ERROR = 'error';

    protected $table = 'facturacion_hueco_arca';

    protected $fillable = [
        'empresa_id',
        'puntoventa_id',
        'codigo_afip',
        'numerocomprobante',
        'estado',
        'venta_id',
        'cae',
        'importe',
        'fecha_comprobante',
        'detalle',
        'avisado_at',
    ];

    protected $casts = [
        'fecha_comprobante' => 'date',
        'avisado_at' => 'datetime',
        'importe' => 'float',
    ];
}
