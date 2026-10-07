<?php

namespace App\Models\Logistica;

use App\Models\Contable\Centrocosto;
use App\Models\Stock\Articulo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class ArticuloCentrocostoPedido extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'articulo_centrocosto_pedido';

    protected $fillable = ['articulo_id', 'centrocosto_id'];

    protected $casts = [
        'articulo_id' => 'integer',
        'centrocosto_id' => 'integer',
    ];

    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }

    public function centrocosto(): BelongsTo
    {
        return $this->belongsTo(Centrocosto::class, 'centrocosto_id');
    }
}
