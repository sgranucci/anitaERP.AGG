<?php

namespace App\Models\Ventas\GastronomiaCrown;

use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Salida;
use App\Models\Seguridad\Usuario;
use App\Models\Ventas\AreaComandaGastronomia;
use App\Models\Ventas\MesaGastronomia;
use App\Models\Ventas\MozoGastronomia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class GastroCrownComanda extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const TIPO_NORMAL = 'normal';

    public const TIPO_POSTRES = 'postres';

    public const ESTADO_FIRED = 'fired';

    public const ESTADO_PREPARING = 'preparing';

    public const ESTADO_READY = 'ready';

    public const ESTADO_DELIVERED = 'delivered';

    public const ESTADO_VOID = 'void';

    protected $table = 'gastro_crown_comanda';

    protected $fillable = [
        'empresa_id',
        'gastro_crown_cuenta_id',
        'numero',
        'tipo',
        'area_comanda_gastronomia_id',
        'salida_id',
        'mesa_gastronomia_id',
        'mozo_gastronomia_id',
        'estado',
        'canal_impresion',
        'ruta_pdf',
        'usuario_id',
        'observacion',
        'fired_at',
    ];

    protected $casts = [
        'fired_at' => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(GastroCrownCuenta::class, 'gastro_crown_cuenta_id');
    }

    public function areaComanda(): BelongsTo
    {
        return $this->belongsTo(AreaComandaGastronomia::class, 'area_comanda_gastronomia_id');
    }

    public function salida(): BelongsTo
    {
        return $this->belongsTo(Salida::class, 'salida_id');
    }

    public function mesa(): BelongsTo
    {
        return $this->belongsTo(MesaGastronomia::class, 'mesa_gastronomia_id');
    }

    public function mozo(): BelongsTo
    {
        return $this->belongsTo(MozoGastronomia::class, 'mozo_gastronomia_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(GastroCrownComandaLinea::class, 'gastro_crown_comanda_id')->orderBy('orden');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(GastroCrownComandaEvento::class, 'gastro_crown_comanda_id');
    }
}
