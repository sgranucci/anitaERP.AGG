<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CotGuiaLinea extends Model
{
    protected $table = 'cot_guia_linea';

    protected $fillable = [
        'cot_guia_id',
        'orden',
        'tipo',
        'letra',
        'sucursal',
        'numero',
        'cliente_codigo',
        'cliente_nombre',
        'bultos',
        'cantidad',
        'valor_declarado',
        'transporte_id',
        'transporte_codigo',
        'entrega',
        'venta_id',
    ];

    protected $casts = [
        'cot_guia_id' => 'integer',
        'orden' => 'integer',
        'sucursal' => 'integer',
        'numero' => 'integer',
        'bultos' => 'float',
        'cantidad' => 'float',
        'valor_declarado' => 'float',
        'transporte_id' => 'integer',
        'venta_id' => 'integer',
    ];

    public function guia(): BelongsTo
    {
        return $this->belongsTo(CotGuia::class, 'cot_guia_id');
    }

    public function transportes(): BelongsTo
    {
        return $this->belongsTo(Transporte::class, 'transporte_id');
    }

    public function ventas(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function etiquetaFactura(): string
    {
        return trim(sprintf(
            '%s %s-%04d-%08d',
            $this->tipo,
            $this->letra,
            (int) $this->sucursal,
            (int) $this->numero
        ));
    }
}
