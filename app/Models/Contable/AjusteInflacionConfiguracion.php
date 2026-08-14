<?php

namespace App\Models\Contable;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AjusteInflacionConfiguracion extends Model
{
    protected $table = 'ajuste_inflacion_configuracion';

    protected $fillable = [
        'empresa_id',
        'cuentacontable_recpam_id',
        'centrocosto_recpam_id',
        'tipoasiento_id',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function cuentacontableRecpam(): BelongsTo
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontable_recpam_id');
    }

    /** Alias de pantalla / eager load (`cuentaRecpam`). */
    public function cuentaRecpam(): BelongsTo
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontable_recpam_id');
    }

    public function centrocostoRecpam(): BelongsTo
    {
        return $this->belongsTo(Centrocosto::class, 'centrocosto_recpam_id');
    }

    public function tipoasiento(): BelongsTo
    {
        return $this->belongsTo(Tipoasiento::class, 'tipoasiento_id');
    }
}
