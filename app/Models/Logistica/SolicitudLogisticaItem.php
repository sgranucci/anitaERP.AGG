<?php

namespace App\Models\Logistica;

use App\Models\Stock\Articulo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class SolicitudLogisticaItem extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'solicitud_logistica_item';

    protected $fillable = [
        'solicitud_logistica_id', 'articulo_id', 'cantidad', 'precio_estimado',
        'cantidad_preparada', 'cantidad_entregada',
    ];

    protected $casts = [
        'solicitud_logistica_id' => 'integer',
        'articulo_id' => 'integer',
        'cantidad' => 'float',
        'precio_estimado' => 'float',
        'cantidad_preparada' => 'float',
        'cantidad_entregada' => 'float',
    ];

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudLogistica::class, 'solicitud_logistica_id');
    }

    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }
}
