<?php

namespace App\Models\Ventas;

use App\Models\Configuracion\Empresa;
use App\Support\Ventas\TipotransaccionCodigoAfipSupport;
use App\Support\Ventas\VentaNumeradorFiscalSupport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class Venta_Serie_Numerador extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'venta_serie_numerador';

    protected $fillable = [
        'empresa_id',
        'puntoventa_id',
        'codigo_afip',
        'ultimo_numero',
        'piso',
        'observacion',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'puntoventa_id' => 'integer',
        'codigo_afip' => 'integer',
        'ultimo_numero' => 'integer',
        'piso' => 'integer',
    ];

    public function puntoventa(): BelongsTo
    {
        return $this->belongsTo(Puntoventa::class, 'puntoventa_id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function getEtiquetaAttribute(): string
    {
        return TipotransaccionCodigoAfipSupport::etiqueta((int) $this->codigo_afip);
    }

    public function getProximoAttribute(): int
    {
        return VentaNumeradorFiscalSupport::proximoNumero(
            (int) $this->ultimo_numero,
            (int) $this->piso,
        );
    }

    public function getNombreempresaAttribute(): string
    {
        return (string) ($this->empresa->nombre ?? '');
    }
}
