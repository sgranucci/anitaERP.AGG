<?php

namespace App\Models\Sala;

use App\Models\Seguridad\Usuario;
use App\Models\Stock\Articulo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequisicionSalaArticuloCambio extends Model
{
    protected $table = 'requisicion_sala_articulo_cambio';

    protected $fillable = [
        'requisicion_sala_id',
        'requisicion_sala_articulo_id',
        'articulo_id_anterior',
        'articulo_id_nuevo',
        'usuario_id',
        'cumplimiento_requisicion_sala_id',
        'motivo',
    ];

    public function requisicionSala(): BelongsTo
    {
        return $this->belongsTo(RequisicionSala::class, 'requisicion_sala_id');
    }

    public function requisicionSalaArticulo(): BelongsTo
    {
        return $this->belongsTo(RequisicionSalaArticulo::class, 'requisicion_sala_articulo_id');
    }

    public function articuloAnterior(): BelongsTo
    {
        return $this->belongsTo(Articulo::class, 'articulo_id_anterior');
    }

    public function articuloNuevo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class, 'articulo_id_nuevo');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function cumplimiento(): BelongsTo
    {
        return $this->belongsTo(CumplimientoRequisicionSala::class, 'cumplimiento_requisicion_sala_id');
    }
}
