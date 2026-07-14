<?php

namespace App\Models\Ventas;

use App\Models\Stock\Articulo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificadoSanitarioArticulo extends Model
{
    protected $table = 'certificado_sanitario_articulo';

    protected $fillable = [
        'certificado_sanitario_id',
        'linea',
        'articulo_id',
        'sku',
        'cantidad',
        'cajas',
        'cert_tercero',
        'partida',
    ];

    protected $casts = [
        'cantidad' => 'float',
        'cajas' => 'float',
    ];

    public function certificado(): BelongsTo
    {
        return $this->belongsTo(CertificadoSanitario::class, 'certificado_sanitario_id');
    }

    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }
}
