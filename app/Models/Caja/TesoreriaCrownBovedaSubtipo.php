<?php

namespace App\Models\Caja;

use App\Models\Configuracion\Sala;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class TesoreriaCrownBovedaSubtipo extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'tesoreria_crown_boveda_subtipo';

    protected $fillable = [
        'sala_id',
        'tipo_id',
        'codigo',
        'nombre',
        'activo',
        'mueve_billetaje',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'mueve_billetaje' => 'boolean',
    ];

    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class, 'sala_id');
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TesoreriaCrownBovedaTipo::class, 'tipo_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(TesoreriaCrownBoveda::class, 'subtipo_id');
    }

    public function etiqueta(): string
    {
        return trim($this->codigo.' — '.$this->nombre);
    }
}
