<?php

namespace App\Models\Caja;

use App\Models\Ventas\MozoGastronomia;
use App\Support\Caja\CajeroCrownSistemaExternoSupport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class CajeroCrownExterno extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'cajero_crown_externo';

    protected $fillable = [
        'cajero_crown_id',
        'sistema',
        'codigo_externo',
        'mozo_gastronomia_id',
    ];

    protected $casts = [
        'cajero_crown_id' => 'integer',
        'mozo_gastronomia_id' => 'integer',
    ];

    public function setSistemaAttribute(?string $value): void
    {
        $this->attributes['sistema'] = $value === null || $value === ''
            ? $value
            : CajeroCrownSistemaExternoSupport::normalizar($value);
    }

    public function cajeroCrown(): BelongsTo
    {
        return $this->belongsTo(CajeroCrown::class, 'cajero_crown_id');
    }

    public function mozoGastronomia(): BelongsTo
    {
        return $this->belongsTo(MozoGastronomia::class, 'mozo_gastronomia_id');
    }
}
