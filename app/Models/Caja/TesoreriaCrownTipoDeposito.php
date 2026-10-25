<?php

namespace App\Models\Caja;

use App\Models\Configuracion\Sala;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class TesoreriaCrownTipoDeposito extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'tesoreria_crown_tipo_deposito';

    protected $fillable = [
        'sala_id',
        'codigo',
        'nombre',
        'orden',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class, 'sala_id');
    }

    public function depositos(): HasMany
    {
        return $this->hasMany(TesoreriaCrownDeposito::class, 'tipo_deposito_id');
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
