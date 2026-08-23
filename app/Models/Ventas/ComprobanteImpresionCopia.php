<?php

namespace App\Models\Ventas;

use App\Models\Configuracion\Salida;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class ComprobanteImpresionCopia extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'comprobante_impresion_copia';

    protected $fillable = [
        'formulario_id', 'orden', 'codigo', 'leyenda', 'destinatario',
        'salida_id', 'incluir_en_pdf_sesion',
    ];

    protected $casts = [
        'incluir_en_pdf_sesion' => 'boolean',
    ];

    public function formularioLinea(): BelongsTo
    {
        return $this->belongsTo(ComprobanteImpresionFormularioLinea::class, 'formulario_id');
    }

    public function salida(): BelongsTo
    {
        return $this->belongsTo(Salida::class, 'salida_id');
    }
}
