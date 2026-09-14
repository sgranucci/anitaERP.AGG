<?php

namespace App\Models\Ventas;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CotGuia extends Model
{
    public const ESTADO_BORRADOR = 'borrador';

    public const ESTADO_ENVIADA = 'enviada';

    public const ESTADO_ANULADA = 'anulada';

    protected $table = 'cot_guia';

    protected $fillable = [
        'numero',
        'fecha',
        'transporte_id',
        'cuit_chofer',
        'dominio',
        'estado',
        'usuario_id',
        'cot_sesion_envio_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'numero' => 'integer',
        'transporte_id' => 'integer',
        'usuario_id' => 'integer',
        'cot_sesion_envio_id' => 'integer',
    ];

    public function lineas(): HasMany
    {
        return $this->hasMany(CotGuiaLinea::class, 'cot_guia_id')->orderBy('orden');
    }

    public function transportes(): BelongsTo
    {
        return $this->belongsTo(Transporte::class, 'transporte_id');
    }

    public function usuarios(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function cotSesionEnvio(): BelongsTo
    {
        return $this->belongsTo(CotSesionEnvio::class, 'cot_sesion_envio_id');
    }

    public function esBorrador(): bool
    {
        return $this->estado === self::ESTADO_BORRADOR;
    }

    public function esEnviada(): bool
    {
        return $this->estado === self::ESTADO_ENVIADA;
    }
}
