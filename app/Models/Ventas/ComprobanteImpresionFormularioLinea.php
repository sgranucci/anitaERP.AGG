<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class ComprobanteImpresionFormularioLinea extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'comprobante_impresion_formulario';

    protected $fillable = ['programa_id', 'orden', 'formulario'];

    public function programa(): BelongsTo
    {
        return $this->belongsTo(ComprobanteImpresionPrograma::class, 'programa_id');
    }

    public function copias(): HasMany
    {
        return $this->hasMany(ComprobanteImpresionCopia::class, 'formulario_id')
            ->orderBy('orden');
    }
}
