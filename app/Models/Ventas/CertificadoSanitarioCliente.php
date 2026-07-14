<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificadoSanitarioCliente extends Model
{
    protected $table = 'certificado_sanitario_cliente';

    protected $fillable = [
        'certificado_sanitario_id',
        'linea',
        'cliente_id',
        'codigo_cliente',
    ];

    public function certificado(): BelongsTo
    {
        return $this->belongsTo(CertificadoSanitario::class, 'certificado_sanitario_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }
}
