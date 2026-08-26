<?php

namespace App\Models\Sala;

use App\Models\Stock\Articulo;
use App\Models\Stock\Depmae;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CumplimientoRequisicionSalaArticulo extends Model
{
    protected $table = 'cumplimiento_requisicion_sala_articulo';

    protected $fillable = [
        'cumplimiento_requisicion_sala_id',
        'requisicion_sala_id',
        'requisicion_sala_articulo_id',
        'articulo_id',
        'articulo_id_original',
        'cantidad_entrega',
        'cantidad_pendiente_antes',
        'cantidadentregada_antes',
        'deposito_origen_id',
        'tecnico_laboratorio_id',
        'numeroparte',
        'uid',
        'destino',
        'estado_linea',
        'estadoparcial',
        'fecha_entrega',
        'numeroremito',
        'nombreresponsable',
        'estado_linea_antes',
        'estadoparcial_antes',
        'fecha_entrega_antes',
        'numeroremito_antes',
        'nombreresponsable_antes',
        'tecnico_laboratorio_id_antes',
        'deposito_origen_id_antes',
        'numeroparte_antes',
    ];

    protected $casts = [
        'cantidad_entrega' => 'float',
        'cantidad_pendiente_antes' => 'float',
        'cantidadentregada_antes' => 'float',
        'fecha_entrega' => 'date',
        'fecha_entrega_antes' => 'date',
    ];

    public function cumplimiento(): BelongsTo
    {
        return $this->belongsTo(CumplimientoRequisicionSala::class, 'cumplimiento_requisicion_sala_id');
    }

    public function requisicionSala(): BelongsTo
    {
        return $this->belongsTo(RequisicionSala::class, 'requisicion_sala_id');
    }

    public function requisicionSalaArticulo(): BelongsTo
    {
        return $this->belongsTo(RequisicionSalaArticulo::class, 'requisicion_sala_articulo_id');
    }

    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }

    public function articuloOriginal(): BelongsTo
    {
        return $this->belongsTo(Articulo::class, 'articulo_id_original');
    }

    public function depositoOrigen(): BelongsTo
    {
        return $this->belongsTo(Depmae::class, 'deposito_origen_id');
    }

    public function tecnicoLaboratorio(): BelongsTo
    {
        return $this->belongsTo(TecnicoLaboratorio::class, 'tecnico_laboratorio_id');
    }
}
