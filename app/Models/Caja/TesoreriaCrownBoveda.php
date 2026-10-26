<?php

namespace App\Models\Caja;

use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Sala;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class TesoreriaCrownBoveda extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const BORRADOR = 'B';

    public const CONFIRMADO = 'C';

    public const ANULADO = 'N';

    protected $table = 'tesoreria_crown_boveda';

    protected $fillable = [
        'sala_id',
        'empresa_id',
        'jornada_id',
        'deposito_id',
        'tipo_id',
        'numero',
        'estado',
        'comentario',
        'comprobante_id',
        'usuario_id',
        'confirmado_at',
    ];

    protected $casts = [
        'numero' => 'integer',
        'confirmado_at' => 'datetime',
    ];

    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class, 'sala_id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function jornada(): BelongsTo
    {
        return $this->belongsTo(TesoreriaCrownJornada::class, 'jornada_id');
    }

    public function deposito(): BelongsTo
    {
        return $this->belongsTo(TesoreriaCrownDeposito::class, 'deposito_id');
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TesoreriaCrownBovedaTipo::class, 'tipo_id');
    }

    public function comprobante(): BelongsTo
    {
        return $this->belongsTo(TesoreriaCrownComprobante::class, 'comprobante_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(TesoreriaCrownBovedaLinea::class, 'boveda_id');
    }

    public function esBorrador(): bool
    {
        return $this->estado === self::BORRADOR;
    }
}
