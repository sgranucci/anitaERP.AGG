<?php

namespace App\Models\Configuracion;

use App\Support\Configuracion\SeteoSalidaProgramaSupport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use OwenIt\Auditing\Contracts\Auditable;

class UsoSalidaImpresora extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'uso_salida_impresora';

    protected $fillable = ['nombre', 'descripcion', 'programas_destino'];

    protected $casts = [
        'programas_destino' => 'array',
    ];

    /** @var list<string> */
    protected $appends = ['programas_destino_etiqueta'];

    public function salidas(): BelongsToMany
    {
        return $this->belongsToMany(
            Salida::class,
            'salida_uso_salida_impresora',
            'uso_salida_impresora_id',
            'salida_id'
        );
    }

    public function getProgramasDestinoEtiquetaAttribute(): string
    {
        $programas = array_values(array_filter((array) ($this->programas_destino ?? [])));

        return SeteoSalidaProgramaSupport::etiquetasProgramas($programas);
    }
}
