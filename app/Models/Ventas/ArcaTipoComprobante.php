<?php

namespace App\Models\Ventas;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class ArcaTipoComprobante extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $table = 'arca_tipo_comprobante';

    protected $fillable = [
        'empresa_id',
        'webservice',
        'codigo_numerico',
        'codigo_afip',
        'descripcion',
        'sincronizado_at',
    ];

    protected $casts = [
        'codigo_numerico' => 'integer',
        'sincronizado_at' => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }
}
