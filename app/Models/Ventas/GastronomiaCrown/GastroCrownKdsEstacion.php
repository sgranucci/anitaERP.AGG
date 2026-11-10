<?php

namespace App\Models\Ventas\GastronomiaCrown;

use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Sala;
use App\Models\Ventas\AreaComandaGastronomia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class GastroCrownKdsEstacion extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'gastro_crown_kds_estacion';

    protected $fillable = [
        'identificador_pc',
        'descripcion',
        'empresa_id',
        'sala_id',
        'area_comanda_gastronomia_id',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function areaComanda(): BelongsTo
    {
        return $this->belongsTo(AreaComandaGastronomia::class, 'area_comanda_gastronomia_id');
    }

    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class, 'sala_id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }
}
