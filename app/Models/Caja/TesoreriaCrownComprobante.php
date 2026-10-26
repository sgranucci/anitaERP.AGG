<?php

namespace App\Models\Caja;

use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Sala;
use App\Models\Seguridad\Usuario;
use App\Support\Caja\TesoreriaCrownComprobanteSupport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class TesoreriaCrownComprobante extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'tesoreria_crown_comprobante';

    protected $fillable = [
        'sala_id',
        'empresa_id',
        'jornada_id',
        'asignacion_id',
        'tipo',
        'numero',
        'estado',
        'total_x',
        'invitacion',
        'total_z',
        'nro_z',
        'ult_ticket',
        'total_nc',
        'iva_facturas',
        'iva_nc',
        'nro_z_cash',
        'venta_cash',
        'vta_apuesta',
        'venta_online',
        'tarjeta_debito',
        'tarjeta_credito',
        'recargo_fin',
        'ctacte_hotel',
        'transferencia_hotel',
        'venta_voucher',
        'pago_voucher',
        'total_qr',
        'observacion',
        'entrega_usuario_id',
        'entrega_at',
        'recibe_usuario_id',
        'recibe_at',
        'revierte_id',
        'qr_token',
    ];

    protected $casts = [
        'entrega_at' => 'datetime',
        'recibe_at' => 'datetime',
        'numero' => 'integer',
        'ult_ticket' => 'integer',
        'total_x' => 'decimal:2',
        'invitacion' => 'decimal:2',
        'total_z' => 'decimal:2',
        'total_nc' => 'decimal:2',
        'iva_facturas' => 'decimal:2',
        'iva_nc' => 'decimal:2',
        'venta_cash' => 'decimal:2',
        'vta_apuesta' => 'decimal:2',
        'venta_online' => 'decimal:2',
        'tarjeta_debito' => 'decimal:2',
        'tarjeta_credito' => 'decimal:2',
        'recargo_fin' => 'decimal:2',
        'ctacte_hotel' => 'decimal:2',
        'transferencia_hotel' => 'decimal:2',
        'venta_voucher' => 'decimal:2',
        'pago_voucher' => 'decimal:2',
        'total_qr' => 'decimal:2',
    ];

    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class, 'sala_id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function jornada(): BelongsTo
    {
        return $this->belongsTo(TesoreriaCrownJornada::class, 'jornada_id');
    }

    public function asignacion(): BelongsTo
    {
        return $this->belongsTo(TesoreriaCrownAsignacion::class, 'asignacion_id');
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(TesoreriaCrownComprobanteLinea::class, 'comprobante_id');
    }

    public function cierresFiscales(): HasMany
    {
        return $this->hasMany(TesoreriaCrownCierreFiscal::class, 'comprobante_id');
    }

    public function entregaUsuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'entrega_usuario_id');
    }

    public function recibeUsuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'recibe_usuario_id');
    }

    public function revierte(): BelongsTo
    {
        return $this->belongsTo(self::class, 'revierte_id');
    }

    public function esBorrador(): bool
    {
        return $this->estado === TesoreriaCrownComprobanteSupport::BORRADOR;
    }

    public function firmasCompletas(): bool
    {
        return $this->entrega_at !== null && $this->recibe_at !== null;
    }
}
