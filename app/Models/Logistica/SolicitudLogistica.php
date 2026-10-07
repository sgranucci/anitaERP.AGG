<?php

namespace App\Models\Logistica;

use App\Models\Compras\Ordencompra;
use App\Models\Compras\Requisicion;
use App\Models\Configuracion\Empresa;
use App\Models\Contable\Centrocosto;
use App\Models\Seguridad\Usuario;
use App\Models\Stock\Depmae;
use App\Models\Stock\MovimientoStock;
use App\Models\Stock\Transferencia_Mercaderia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class SolicitudLogistica extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'solicitud_logistica';

    protected $fillable = [
        'numero', 'fecha', 'usuario_id', 'centrocosto_id', 'tipo_solicitud_id',
        'prioridad', 'estado', 'observacion',
        'trabajo_tipo_id', 'ubicacion_origen_id', 'ubicacion_destino_id', 'empresa_id',
        'ordencompra_id', 'cantidad', 'fecha_tentativa', 'motivo', 'detalle', 'accesorios',
        'accesorios_detalle', 'tipo_butaca', 'uid_bien', 'direccion_retiro',
        'modo_cumplimiento', 'deposito_id', 'deposito_destino_id', 'total_estimado',
        'responsable_snapshot',
        'fecha_compromiso', 'fecha_preparacion', 'fecha_entrega',
        'receptor_nombre', 'receptor_en',
        'movimientostock_id', 'transferencia_mercaderia_id', 'requisicion_id',
    ];

    protected $casts = [
        'numero' => 'integer',
        'fecha' => 'date',
        'fecha_tentativa' => 'date',
        'fecha_compromiso' => 'datetime',
        'fecha_preparacion' => 'datetime',
        'fecha_entrega' => 'datetime',
        'receptor_en' => 'datetime',
        'movimientostock_id' => 'integer',
        'transferencia_mercaderia_id' => 'integer',
        'requisicion_id' => 'integer',
        'usuario_id' => 'integer',
        'centrocosto_id' => 'integer',
        'tipo_solicitud_id' => 'integer',
        'trabajo_tipo_id' => 'integer',
        'ubicacion_origen_id' => 'integer',
        'ubicacion_destino_id' => 'integer',
        'empresa_id' => 'integer',
        'ordencompra_id' => 'integer',
        'deposito_id' => 'integer',
        'deposito_destino_id' => 'integer',
        'cantidad' => 'float',
        'total_estimado' => 'float',
        'accesorios' => 'boolean',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function centrocosto(): BelongsTo
    {
        return $this->belongsTo(Centrocosto::class, 'centrocosto_id');
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(LogisticaTipoSolicitud::class, 'tipo_solicitud_id');
    }

    public function trabajoTipo(): BelongsTo
    {
        return $this->belongsTo(LogisticaTrabajoTipo::class, 'trabajo_tipo_id');
    }

    public function ubicacionOrigen(): BelongsTo
    {
        return $this->belongsTo(LogisticaUbicacion::class, 'ubicacion_origen_id');
    }

    public function ubicacionDestino(): BelongsTo
    {
        return $this->belongsTo(LogisticaUbicacion::class, 'ubicacion_destino_id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function ordencompra(): BelongsTo
    {
        return $this->belongsTo(Ordencompra::class, 'ordencompra_id');
    }

    public function deposito(): BelongsTo
    {
        return $this->belongsTo(Depmae::class, 'deposito_id');
    }

    public function depositoDestino(): BelongsTo
    {
        return $this->belongsTo(Depmae::class, 'deposito_destino_id');
    }

    public function movimientoStock(): BelongsTo
    {
        return $this->belongsTo(MovimientoStock::class, 'movimientostock_id');
    }

    public function transferencia(): BelongsTo
    {
        return $this->belongsTo(Transferencia_Mercaderia::class, 'transferencia_mercaderia_id');
    }

    public function requisicion(): BelongsTo
    {
        return $this->belongsTo(Requisicion::class, 'requisicion_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SolicitudLogisticaItem::class, 'solicitud_logistica_id');
    }

    public function archivos(): HasMany
    {
        return $this->hasMany(SolicitudLogisticaArchivo::class, 'solicitud_logistica_id');
    }

    public function numeroVisible(): string
    {
        $anio = $this->fecha ? $this->fecha->format('Y') : date('Y');
        $prefijo = 'SOL';
        $tipo = $this->relationLoaded('tipo') ? $this->tipo : null;
        if ($tipo !== null && $tipo->codigo === 'trabajos') {
            $trabajo = $this->relationLoaded('trabajoTipo') ? $this->trabajoTipo : null;
            $prefijo = ($trabajo !== null && $trabajo->codigo === 'retiro') ? 'RET' : 'TRB';
        }

        return $prefijo.'-'.$anio.'-'.str_pad((string) $this->numero, 4, '0', STR_PAD_LEFT);
    }

    public function etiquetaEstado(): string
    {
        return match ($this->estado) {
            'pendiente_aprobacion' => 'Pendiente de aprobación',
            'aprobada' => 'Aprobada',
            'rechazada' => 'Rechazada',
            'en_preparacion' => 'En preparación',
            'entregada' => 'Entregada',
            'cerrada' => 'Cerrada',
            default => 'Enviada',
        };
    }

    public static function siguienteNumero(): int
    {
        $ultimo = self::query()
            ->whereYear('fecha', now()->year)
            ->orderByDesc('numero')
            ->lockForUpdate()
            ->first();

        return ((int) ($ultimo->numero ?? 0)) + 1;
    }
}
