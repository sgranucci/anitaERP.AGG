<?php

namespace App\Models\Ventas;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class ComprobanteImpresionPrograma extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'comprobante_impresion_programa';

    protected $fillable = ['codigo', 'nombre', 'empresa_id', 'permite_disparo_al_grabar'];

    protected $casts = [
        'permite_disparo_al_grabar' => 'boolean',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function formularios(): HasMany
    {
        return $this->hasMany(ComprobanteImpresionFormularioLinea::class, 'programa_id')
            ->orderBy('orden');
    }

    public function reglas(): HasMany
    {
        return $this->hasMany(ComprobanteImpresionRegla::class, 'programa_id')
            ->orderByDesc('prioridad');
    }
}
