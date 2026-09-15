<?php

namespace App\Models\Ventas;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pedido_Picking extends Model
{
    protected $table = 'pedido_picking';

    protected $fillable = [
        'codigo',
        'fecha',
        'usuario_id',
        'observacion',
    ];

    protected $casts = [
        'fecha' => 'date',
        'codigo' => 'integer',
    ];

    public function lineas(): HasMany
    {
        return $this->hasMany(Pedido_Combinacion::class, 'picking_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
