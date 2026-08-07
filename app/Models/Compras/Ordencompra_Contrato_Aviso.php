<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;

/**
 * Log idempotente de avisos de vencimiento de contratos / OC abiertas.
 *
 * Una fila por (orden de compra, clave de umbral). La clave incluye la fecha de
 * referencia, de modo que al renovar el contrato los umbrales vuelven a dispararse.
 */
class Ordencompra_Contrato_Aviso extends Model
{
    /** Faltan N días para el fin de vigencia. */
    public const TIPO_VIGENCIA = 'VIGENCIA';

    /** Faltan N días para la fecha límite de aviso de no renovación. */
    public const TIPO_PREAVISO = 'PREAVISO';

    /** El facturado alcanzó un porcentaje del monto tope. */
    public const TIPO_CONSUMO = 'CONSUMO';

    /** La vigencia ya venció y el contrato sigue abierto. */
    public const TIPO_VENCIDO = 'VENCIDO';

    protected $table = 'ordencompra_contrato_aviso';

    protected $fillable = [
        'ordencompra_id', 'tipo_aviso', 'umbral', 'clave', 'fecha_referencia',
        'monto_consumido', 'porcentaje_consumido', 'destinatarios', 'enviado_at',
    ];

    protected $casts = [
        'fecha_referencia' => 'date',
        'enviado_at' => 'datetime',
        'umbral' => 'integer',
        'monto_consumido' => 'float',
        'porcentaje_consumido' => 'float',
    ];

    public function ordencompras()
    {
        return $this->belongsTo(Ordencompra::class, 'ordencompra_id');
    }
}
