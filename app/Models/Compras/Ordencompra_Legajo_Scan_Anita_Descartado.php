<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Ordencompra_Legajo_Scan_Anita_Descartado extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'ordencompra_legajo_scan_anita_descartado';

    protected $fillable = [
        'ordencompra_id',
        'empresa_id',
        'numeroordencompra',
        'documento_id',
        'letra',
        'sucursal',
        'numerocomprobante',
        'precarga_id_origen',
        'motivo',
        'user_id',
        'revertido_at',
        'revertido_user_id',
        'revertido_motivo',
    ];

    protected $casts = [
        'revertido_at' => 'datetime',
    ];

    /**
     * Un descarte revertido dejó de ocultar el scan: sigue en la tabla como rastro, pero
     * el legajo lo vuelve a mostrar.
     */
    public function scopeVigente(Builder $query): Builder
    {
        return $query->whereNull('revertido_at');
    }

    public function estaVigente(): bool
    {
        return $this->revertido_at === null;
    }
}
