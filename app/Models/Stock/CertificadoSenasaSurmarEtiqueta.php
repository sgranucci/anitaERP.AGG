<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificadoSenasaSurmarEtiqueta extends Model
{
    protected $table = 'certificado_senasa_surmar_etiqueta';

    protected $fillable = [
        'empresa_id',
        'certificado_senasa_surmar_id',
        'certificado_senasa_surmar_articulo_id',
        'etiqueta_id',
        'articulo_id',
        'cant_pieza',
        'peso_bruto',
        'peso_neto',
        'lote_proveedor',
        'hora_piqueo',
    ];

    protected $casts = [
        'cant_pieza' => 'float',
        'peso_bruto' => 'float',
        'peso_neto' => 'float',
    ];

    public function certificado(): BelongsTo
    {
        return $this->belongsTo(CertificadoSenasaSurmar::class, 'certificado_senasa_surmar_id');
    }

    public function linea(): BelongsTo
    {
        return $this->belongsTo(CertificadoSenasaSurmarArticulo::class, 'certificado_senasa_surmar_articulo_id');
    }

    public function etiqueta(): BelongsTo
    {
        return $this->belongsTo(Stock_Etiqueta::class, 'etiqueta_id');
    }

    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }
}
