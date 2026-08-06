<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CertificadoSenasaSurmarArticulo extends Model
{
    protected $table = 'certificado_senasa_surmar_articulo';

    protected $fillable = [
        'certificado_senasa_surmar_id',
        'linea',
        'articulo_id',
        'sku',
        'kilos',
        'cajas',
        'piezas',
        'tropa',
        'grupocarne',
        'tipocarne',
        'cert_tercero',
        'partida',
        'hora_piqueo',
    ];

    protected $casts = [
        'kilos' => 'float',
        'cajas' => 'float',
        'piezas' => 'float',
    ];

    public function certificado(): BelongsTo
    {
        return $this->belongsTo(CertificadoSenasaSurmar::class, 'certificado_senasa_surmar_id');
    }

    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }

    public function etiquetas(): HasMany
    {
        return $this->hasMany(CertificadoSenasaSurmarEtiqueta::class, 'certificado_senasa_surmar_articulo_id');
    }

    public function codTipoProdAfip(): string
    {
        return ((int) ($this->grupocarne ?? 0)).'.'.((int) ($this->tipocarne ?? 0));
    }
}
