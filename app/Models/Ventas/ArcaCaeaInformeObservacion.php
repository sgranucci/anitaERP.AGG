<?php

declare(strict_types=1);

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Texto de observación ARCA al informar un CAEA. No es maestro: se rota a 3 meses.
 */
class ArcaCaeaInformeObservacion extends Model
{
    protected $table = 'arca_caea_informe_observacion';

    protected $fillable = [
        'empresa_id',
        'arca_caea_id',
        'venta_id',
        'fuente',
        'pto_vta',
        'tipo_afip',
        'tipo_anita',
        'letra',
        'numero',
        'fecha_comprobante',
        'texto',
        'informado_at',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'arca_caea_id' => 'integer',
        'venta_id' => 'integer',
        'pto_vta' => 'integer',
        'tipo_afip' => 'integer',
        'numero' => 'integer',
        'fecha_comprobante' => 'date',
        'informado_at' => 'datetime',
    ];

    public function caea(): BelongsTo
    {
        return $this->belongsTo(ArcaCaea::class, 'arca_caea_id');
    }

    public function etiqueta(): string
    {
        $tipo = trim((string) ($this->tipo_anita ?? ''));
        $letra = trim((string) ($this->letra ?? ''));
        $prefijo = trim($tipo.' '.$letra);
        if ($prefijo === '') {
            $prefijo = 'T'.(int) $this->tipo_afip;
        }

        return sprintf('%s %05d #%d', $prefijo, (int) $this->pto_vta, (int) $this->numero);
    }
}
