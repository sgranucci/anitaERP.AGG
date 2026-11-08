<?php

namespace App\Models\Caja;

use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Sala;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class TesoreriaCrownBovedaArqueo extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const BORRADOR = 'B';

    public const PENDIENTE = 'P';

    public const CONFORME = 'C';

    protected $table = 'tesoreria_crown_boveda_arqueo';

    protected $fillable = [
        'sala_id',
        'empresa_id',
        'jornada_id',
        'deposito_id',
        'numero',
        'estado',
        'corte_at',
        'arqueo_anterior_id',
        'observacion',
        'entrega_usuario_id',
        'entrega_at',
        'recibe_usuario_id',
        'recibe_at',
        'usuario_id',
    ];

    protected $casts = [
        'numero' => 'integer',
        'corte_at' => 'datetime',
        'entrega_at' => 'datetime',
        'recibe_at' => 'datetime',
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

    public function anterior(): BelongsTo
    {
        return $this->belongsTo(self::class, 'arqueo_anterior_id');
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(TesoreriaCrownBovedaArqueoLinea::class, 'arqueo_id');
    }

    public function entregaUsuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'entrega_usuario_id');
    }

    public function recibeUsuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'recibe_usuario_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function estaBorrador(): bool
    {
        return $this->estado === self::BORRADOR;
    }

    public function estaPendiente(): bool
    {
        return $this->estado === self::PENDIENTE;
    }

    public function estaConforme(): bool
    {
        return $this->estado === self::CONFORME;
    }

    public function firmasCompletas(): bool
    {
        return $this->entrega_usuario_id !== null && $this->recibe_usuario_id !== null;
    }
}
