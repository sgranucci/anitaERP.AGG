<?php

namespace App\Models\Caja;

use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Sala;
use App\Models\Seguridad\Usuario;
use App\Models\Ventas\MozoGastronomia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class CajeroCrown extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'cajero_crown';

    protected $fillable = [
        'codigo',
        'clave',
        'nombre',
        'empresa_id',
        'sala_id',
        'usuario_id',
        'legajo',
        'caja_id',
        'mozo_gastronomia_id',
        'activo',
    ];

    protected $hidden = [
        'clave',
    ];

    protected $casts = [
        'codigo' => 'integer',
        'clave' => 'hashed',
        'empresa_id' => 'integer',
        'sala_id' => 'integer',
        'usuario_id' => 'integer',
        'legajo' => 'integer',
        'caja_id' => 'integer',
        'mozo_gastronomia_id' => 'integer',
        'activo' => 'boolean',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class, 'sala_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class, 'caja_id');
    }

    public function mozoGastronomia(): BelongsTo
    {
        return $this->belongsTo(MozoGastronomia::class, 'mozo_gastronomia_id');
    }

    public function externos(): HasMany
    {
        return $this->hasMany(CajeroCrownExterno::class, 'cajero_crown_id');
    }
}
