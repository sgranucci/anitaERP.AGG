<?php

namespace App\Models\Stock;

use App\Models\Ventas\Cliente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificadoSenasaSurmarCliente extends Model
{
    protected $table = 'certificado_senasa_surmar_cliente';

    protected $fillable = [
        'certificado_senasa_surmar_id',
        'linea',
        'cliente_id',
        'codigo_cliente',
    ];

    public function certificado(): BelongsTo
    {
        return $this->belongsTo(CertificadoSenasaSurmar::class, 'certificado_senasa_surmar_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }
}
