<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificadoSenasaSurmarDestino extends Model
{
    protected $table = 'certificado_senasa_surmar_destino';

    protected $fillable = [
        'certificado_senasa_surmar_id',
        'linea',
        'zonavta_id',
        'codigo_destino',
        'localidad',
        'provincia',
        'patagonico',
    ];

    protected $casts = [
        'patagonico' => 'boolean',
    ];

    public function certificado(): BelongsTo
    {
        return $this->belongsTo(CertificadoSenasaSurmar::class, 'certificado_senasa_surmar_id');
    }
}
