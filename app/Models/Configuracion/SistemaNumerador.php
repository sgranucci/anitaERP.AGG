<?php

namespace App\Models\Configuracion;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SistemaNumerador extends Model
{
    protected $table = 'sistema_numerador';

    protected $fillable = [
        'codigo',
        'nombre',
        'empresa_id',
        'modulo',
        'ultimo_numero',
        'anita_sistema',
        'anita_fuente',
        'anita_clave',
        'activo',
        'observacion',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'ultimo_numero' => 'integer',
        'activo' => 'boolean',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    /**
     * Atributo virtual para logos en export/listado.
     */
    public function getNombreempresaAttribute(): string
    {
        return (string) ($this->empresa->nombre ?? '');
    }
}
