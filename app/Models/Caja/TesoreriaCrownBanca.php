<?php

namespace App\Models\Caja;

use App\Models\Configuracion\Sala;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class TesoreriaCrownBanca extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'tesoreria_crown_banca';

    protected $fillable = [
        'sala_id',
        'codigo',
        'nombre',
        'vigencia_desde',
    ];

    protected $casts = [
        'codigo' => 'integer',
        'vigencia_desde' => 'date',
    ];

    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class, 'sala_id');
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(TesoreriaCrownBancaLinea::class, 'banca_id');
    }
}
