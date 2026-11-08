<?php

namespace App\Models\Caja;

use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Sala;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use OwenIt\Auditing\Contracts\Auditable;

class TesoreriaCrownMesa extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const BORRADOR = 'B';

    public const CONFIRMADO = 'C';

    public const ANULADO = 'N';

    protected $table = 'tesoreria_crown_mesa';

    protected $fillable = [
        'sala_id',
        'empresa_id',
        'jornada_id',
        'deposito_id',
        'cajero_crown_id',
        'banca_id',
        'revierte_id',
        'tipo',
        'numero',
        'estado',
        'comentario',
        'propina',
        'voucher',
        'motivo',
        'usuario_id',
        'confirmado_at',
    ];

    protected $casts = [
        'numero' => 'integer',
        'propina' => 'decimal:2',
        'voucher' => 'decimal:2',
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

    public function cajero(): BelongsTo
    {
        return $this->belongsTo(CajeroCrown::class, 'cajero_crown_id');
    }

    public function banca(): BelongsTo
    {
        return $this->belongsTo(TesoreriaCrownBanca::class, 'banca_id');
    }

    public function revierte(): BelongsTo
    {
        return $this->belongsTo(self::class, 'revierte_id');
    }

    public function reversa(): HasOne
    {
        return $this->hasOne(self::class, 'revierte_id');
    }

    public function puedeRevertir(): bool
    {
        return $this->estado === self::CONFIRMADO
            && $this->revierte_id === null
            && $this->reversa === null;
    }

    public function esReversa(): bool
    {
        return $this->revierte_id !== null;
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(TesoreriaCrownMesaLinea::class, 'mesa_id');
    }

    public function esBorrador(): bool
    {
        return $this->estado === self::BORRADOR;
    }
}
