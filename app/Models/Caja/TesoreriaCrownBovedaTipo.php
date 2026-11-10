<?php

namespace App\Models\Caja;

use App\Models\Configuracion\Sala;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class TesoreriaCrownBovedaTipo extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'tesoreria_crown_boveda_tipo';

    protected $fillable = [
        'sala_id',
        'codigo',
        'abreviatura',
        'nombre',
        'signo',
        'operacion',
        'activo',
    ];

    protected $casts = [
        'codigo' => 'integer',
        'activo' => 'boolean',
    ];

    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class, 'sala_id');
    }

    public function subtipos(): HasMany
    {
        return $this->hasMany(TesoreriaCrownBovedaSubtipo::class, 'tipo_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(TesoreriaCrownBoveda::class, 'tipo_id');
    }

    public function etiqueta(): string
    {
        $abrev = trim((string) $this->abreviatura);
        $nombre = trim((string) $this->nombre);

        return trim($abrev.($nombre !== '' ? ' — '.$nombre : ''));
    }
}
