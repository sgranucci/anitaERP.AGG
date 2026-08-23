<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class ComprobanteImpresionRegla extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'comprobante_impresion_regla';

    protected $fillable = ['programa_id', 'clave', 'valor_id', 'prioridad'];

    public function programa(): BelongsTo
    {
        return $this->belongsTo(ComprobanteImpresionPrograma::class, 'programa_id');
    }
}
