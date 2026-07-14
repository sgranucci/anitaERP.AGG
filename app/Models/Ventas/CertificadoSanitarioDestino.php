<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificadoSanitarioDestino extends Model
{
    protected $table = 'certificado_sanitario_destino';

    protected $fillable = [
        'certificado_sanitario_id',
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
        return $this->belongsTo(CertificadoSanitario::class, 'certificado_sanitario_id');
    }

    public function zonavta(): BelongsTo
    {
        return $this->belongsTo(Zonavta::class, 'zonavta_id');
    }
}
