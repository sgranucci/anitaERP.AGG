<?php

namespace App\Models\Caja;

use App\Models\Configuracion\Sala;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class TesoreriaCrownTipoValor extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'tesoreria_crown_tipo_valor';

    protected $fillable = [
        'sala_id',
        'codigo',
        'nombre',
        'es_moneda_referencia',
        'controla_stock',
        'cotizacion_fija',
        'orden',
        'activo',
    ];

    protected $casts = [
        'es_moneda_referencia' => 'boolean',
        'controla_stock' => 'boolean',
        'cotizacion_fija' => 'boolean',
        'activo' => 'boolean',
    ];

    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class, 'sala_id');
    }

    public function valores(): HasMany
    {
        return $this->hasMany(TesoreriaCrownValor::class, 'tipo_valor_id');
    }

    public function cotizaciones(): HasMany
    {
        return $this->hasMany(TesoreriaCrownCotizacion::class, 'tipo_valor_id');
    }

    public function etiqueta(): string
    {
        $codigo = trim((string) $this->codigo);
        $nombre = trim((string) $this->nombre);
        if ($codigo === '') {
            return $nombre;
        }
        if ($nombre === '') {
            return $codigo;
        }

        return $codigo.' — '.$nombre;
    }
}
