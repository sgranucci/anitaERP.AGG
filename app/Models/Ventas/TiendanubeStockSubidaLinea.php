<?php

namespace App\Models\Ventas;

use App\Models\Stock\Articulo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TiendanubeStockSubidaLinea extends Model
{
    public const ESTADO_OK = 'ok';

    public const ESTADO_ERROR = 'error';

    public const ESTADO_OMITIDA = 'omitida';

    public const ESTADO_PREVISTA = 'prevista';

    protected $table = 'tiendanube_stock_subida_linea';

    protected $fillable = [
        'subida_id',
        'articulo_id',
        'sku',
        'variante_sku',
        'combinacion_codigo',
        'talle',
        'stock',
        'precio',
        'precio_promocional',
        'estado',
        'mensaje',
    ];

    protected $casts = [
        'subida_id' => 'integer',
        'articulo_id' => 'integer',
        'stock' => 'integer',
        'precio' => 'float',
        'precio_promocional' => 'float',
    ];

    public function subida(): BelongsTo
    {
        return $this->belongsTo(TiendanubeStockSubida::class, 'subida_id');
    }

    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }

    public static function etiquetaEstado(?string $estado): string
    {
        return match ($estado) {
            self::ESTADO_PREVISTA => 'A enviar',
            self::ESTADO_OK => 'OK',
            self::ESTADO_ERROR => 'Error',
            self::ESTADO_OMITIDA => 'Omitida',
            default => (string) $estado,
        };
    }
}
