<?php

namespace App\Models\Stock;

use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;

class Recuento extends Model
{

    public const ESTADO_PENDIENTE = 'PENDIENTE';

    public const ESTADO_SUSPENDIDO = 'SUSPENDIDO';

    public const ESTADO_CERRADO_PARCIAL = 'CERRADO_PARCIAL';

    public const ESTADO_CERRADO_TOTAL = 'CERRADO_TOTAL';

    public const ESTADO_ANULADO = 'ANULADO';

    public const TIPO_MANUAL = 'MANUAL';

    public const TIPO_ALEATORIO = 'ALEATORIO';

    public const TIPO_IMPORTADO = 'IMPORTADO';

    public const MODO_CIERRE_FECHA_RECUENTO = 'FECHA_RECUENTO';

    public const MODO_CIERRE_SALDO_ACTUAL = 'SALDO_ACTUAL';

    protected $table = 'recuento';

    protected $fillable = [
        'codigo',
        'fecha',
        'deposito_id',
        'empresa_id',
        'usuario_id',
        'estado',
        'tipo',
        'cantidad_aleatoria',
        'comentario',
        'movimientostock_cierre_id',
        'movimientostock_anulacion_id',
        'modo_cierre',
    ];

    protected $casts = [
        'fecha' => 'date',
        'cantidad_aleatoria' => 'integer',
    ];

    public function items()
    {
        return $this->hasMany(Recuento_Item::class, 'recuento_id');
    }

    public function estados()
    {
        return $this->hasMany(Recuento_Estado::class, 'recuento_id')->orderByDesc('ocurrio_el');
    }

    public function archivos()
    {
        return $this->hasMany(Recuento_Archivo::class, 'recuento_id');
    }

    public function deposito()
    {
        return $this->belongsTo(Depmae::class, 'deposito_id');
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function movimientoCierre()
    {
        return $this->belongsTo(MovimientoStock::class, 'movimientostock_cierre_id');
    }

    public function movimientoAnulacion()
    {
        return $this->belongsTo(MovimientoStock::class, 'movimientostock_anulacion_id');
    }

    public function esEditable(): bool
    {
        return in_array($this->estado, [self::ESTADO_PENDIENTE, self::ESTADO_SUSPENDIDO], true);
    }

    public function estaCerrado(): bool
    {
        return in_array($this->estado, [self::ESTADO_CERRADO_PARCIAL, self::ESTADO_CERRADO_TOTAL], true);
    }

    public static function etiquetaEstado(?string $estado): string
    {
        return match ($estado) {
            self::ESTADO_PENDIENTE => 'Pendiente',
            self::ESTADO_SUSPENDIDO => 'Suspendido',
            self::ESTADO_CERRADO_PARCIAL => 'Cerrado parcial',
            self::ESTADO_CERRADO_TOTAL => 'Cerrado total',
            self::ESTADO_ANULADO => 'Anulado',
            default => (string) $estado,
        };
    }
}
