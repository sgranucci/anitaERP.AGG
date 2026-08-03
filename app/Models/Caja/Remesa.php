<?php

declare(strict_types=1);

namespace App\Models\Caja;

use App\Models\Configuracion\Empresa;
use App\Models\Contable\Asiento;
use App\Models\Seguridad\Usuario;
use App\Support\Caja\Remesa\RemesaSupport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class Remesa extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'remesa';

    protected $fillable = [
        'empresa_id',
        'numero',
        'fecha',
        'tipo',
        'estado',
        'remito',
        'bolsa',
        'precinto',
        'importe_destino',
        'importe_origen',
        'asiento_id',
        'caja_movimiento_id',
        'usuario_id',
        'observacion',
        'nro_oper_anita',
    ];

    protected $casts = [
        'fecha' => 'date',
        'importe_destino' => 'float',
        'importe_origen' => 'float',
    ];

    public static array $enumTipo = [
        ['valor' => RemesaSupport::TIPO_INTERNA, 'nombre' => 'Interna'],
        ['valor' => RemesaSupport::TIPO_EXTERNA, 'nombre' => 'Externa'],
    ];

    public static array $enumEstado = [
        ['valor' => RemesaSupport::ESTADO_CONFIRMADA, 'nombre' => 'Confirmada'],
        ['valor' => RemesaSupport::ESTADO_REVERTIDA, 'nombre' => 'Revertida'],
        ['valor' => RemesaSupport::ESTADO_ANULADA, 'nombre' => 'Anulada'],
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function asiento(): BelongsTo
    {
        return $this->belongsTo(Asiento::class, 'asiento_id');
    }

    public function cajaMovimiento(): BelongsTo
    {
        return $this->belongsTo(Caja_Movimiento::class, 'caja_movimiento_id');
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(RemesaLinea::class)->orderBy('lado')->orderBy('orden')->orderBy('id');
    }

    public function lineasDestino(): HasMany
    {
        return $this->hasMany(RemesaLinea::class)
            ->where('lado', RemesaSupport::LADO_DESTINO)
            ->orderBy('orden')
            ->orderBy('id');
    }

    public function lineasOrigen(): HasMany
    {
        return $this->hasMany(RemesaLinea::class)
            ->where('lado', RemesaSupport::LADO_ORIGEN)
            ->orderBy('orden')
            ->orderBy('id');
    }

    public function esInterna(): bool
    {
        return $this->tipo === RemesaSupport::TIPO_INTERNA;
    }

    public function esExterna(): bool
    {
        return $this->tipo === RemesaSupport::TIPO_EXTERNA;
    }

    public function estaAnulada(): bool
    {
        return $this->estado === RemesaSupport::ESTADO_ANULADA;
    }

    public function estaRevertida(): bool
    {
        return $this->estado === RemesaSupport::ESTADO_REVERTIDA;
    }

    /** No editable ni operativa (revertida o anulada). */
    public function estaInactiva(): bool
    {
        return $this->estaAnulada() || $this->estaRevertida();
    }
}
