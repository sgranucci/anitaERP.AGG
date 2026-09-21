<?php

namespace App\Models\Ventas;

use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\Usuario;
use App\Models\Stock\Depmae;
use App\Models\Stock\MovimientoStock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class RemitoInterno extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'remito_interno';

    protected $fillable = [
        'numero',
        'fecha',
        'local_venta_id',
        'empresa_id',
        'deposito_id',
        'usuario_id',
        'movimientostock_id',
        'estado',
        'destinatario',
        'leyenda',
        'observacion',
    ];

    protected $casts = [
        'fecha' => 'date',
        'numero' => 'integer',
    ];

    public function lineas(): HasMany
    {
        return $this->hasMany(RemitoInternoLinea::class, 'remito_interno_id')->orderBy('orden');
    }

    public function localVenta(): BelongsTo
    {
        return $this->belongsTo(LocalVenta::class, 'local_venta_id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function deposito(): BelongsTo
    {
        return $this->belongsTo(Depmae::class, 'deposito_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function movimientoStock(): BelongsTo
    {
        return $this->belongsTo(MovimientoStock::class, 'movimientostock_id');
    }
}
