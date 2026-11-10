<?php

namespace App\Models\Ventas\GastronomiaCrown;

use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Sala;
use App\Models\Seguridad\Usuario;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\DescuentoGastronomia;
use App\Models\Ventas\MesaGastronomia;
use App\Models\Ventas\MozoGastronomia;
use App\Models\Ventas\Venta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class GastroCrownCuenta extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const ESTADO_ABIERTA = 'abierta';

    public const ESTADO_COBRANDO = 'cobrando';

    public const ESTADO_CERRADA = 'cerrada';

    public const ESTADO_ANULADA = 'anulada';

    protected $table = 'gastro_crown_cuenta';

    protected $fillable = [
        'empresa_id',
        'configuracion_puntoventa_gastronomia_id',
        'identificador_pc',
        'modo_pos',
        'sala_id',
        'mesa_gastronomia_id',
        'mozo_gastronomia_id',
        'cubiertos',
        'estado',
        'tipo_cobro',
        'importe_cobrado',
        'cliente_id',
        'listaprecio_id',
        'descuento_gastronomia_id',
        'porcentaje_descuento',
        'detalle_descuento',
        'tipo_descuento',
        'cliente_interno_descuento_id',
        'autorizante_usuario_id',
        'autorizante_nombre',
        'centrocosto_id',
        'habitacion',
        'reserva_hotel',
        'tarjeta_gst',
        'observacion',
        'venta_id',
        'usuario_apertura_id',
        'cerrada_at',
    ];

    public const COBRO_EFECTIVO = 'efectivo';

    public const COBRO_INTERNA = 'interna';

    public const COBRO_HOTEL = 'hotel';

    public const COBRO_FACTURA_SOLA = 'factura_sola';

    public const COBRO_MEDIOS = 'medios';

    /** Paridad Anita fl_tipo_descuento: 1=Invitados, 2=Staff, 3=Descuento */
    public const TIPO_DTO_INVITADOS = 'invitados';

    public const TIPO_DTO_STAFF = 'staff';

    public const TIPO_DTO_DESCUENTO = 'descuento';

    protected $casts = [
        'cubiertos' => 'integer',
        'porcentaje_descuento' => 'float',
        'importe_cobrado' => 'float',
        'cerrada_at' => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function configuracion(): BelongsTo
    {
        return $this->belongsTo(ConfiguracionPuntoventaGastronomia::class, 'configuracion_puntoventa_gastronomia_id');
    }

    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class, 'sala_id');
    }

    public function mesa(): BelongsTo
    {
        return $this->belongsTo(MesaGastronomia::class, 'mesa_gastronomia_id');
    }

    public function mozo(): BelongsTo
    {
        return $this->belongsTo(MozoGastronomia::class, 'mozo_gastronomia_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function clienteInternoDescuento(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_interno_descuento_id');
    }

    public function autorizanteUsuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'autorizante_usuario_id');
    }

    public function descuentoGastronomia(): BelongsTo
    {
        return $this->belongsTo(DescuentoGastronomia::class, 'descuento_gastronomia_id');
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function usuarioApertura(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_apertura_id');
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(GastroCrownCuentaLinea::class, 'gastro_crown_cuenta_id')->orderBy('orden');
    }

    public function comandas(): HasMany
    {
        return $this->hasMany(GastroCrownComanda::class, 'gastro_crown_cuenta_id');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(GastroCrownCuentaEvento::class, 'gastro_crown_cuenta_id');
    }
}
