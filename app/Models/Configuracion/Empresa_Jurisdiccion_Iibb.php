<?php

declare(strict_types=1);

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class Empresa_Jurisdiccion_Iibb extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'empresa_jurisdiccion_iibb';

    protected $fillable = [
        'empresa_id',
        'provincia_id',
        'es_agente_percepcion',
        'es_agente_retencion',
        'consulta_padron',
    ];

    protected $casts = [
        'es_agente_percepcion' => 'boolean',
        'es_agente_retencion' => 'boolean',
        'consulta_padron' => 'boolean',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function provincia(): BelongsTo
    {
        return $this->belongsTo(Provincia::class, 'provincia_id');
    }
}
