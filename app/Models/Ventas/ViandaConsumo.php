<?php

namespace App\Models\Ventas;

use App\Models\Configuracion\Empresa;
use App\Models\Contable\Centrocosto;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class ViandaConsumo extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'vianda_consumo';

    protected $fillable = [
        'empresa_id',
        'configuracion_terminal_vianda_id',
        'vianda_usuario_id',
        'vianda_tipo_menu_id',
        'centrocosto_id',
        'jornada_gastronomia_id',
        'usuario_id',
        'login_usuario',
        'nombre_usuario',
        'codigo_retiro',
        'fecha',
        'fecha_jornada',
        'hora',
        'observacion',
        'cantidad_items',
        'total_costo',
        'total_venta',
        'estado',
        'anulado_at',
        'anulado_usuario_id',
        'anulado_motivo',
    ];

    protected $casts = [
        'fecha' => 'date',
        'fecha_jornada' => 'date',
        'cantidad_items' => 'integer',
        'total_costo' => 'decimal:4',
        'total_venta' => 'decimal:4',
        'anulado_at' => 'datetime',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function terminal()
    {
        return $this->belongsTo(ConfiguracionTerminalVianda::class, 'configuracion_terminal_vianda_id');
    }

    public function viandaUsuario()
    {
        return $this->belongsTo(ViandaUsuario::class, 'vianda_usuario_id');
    }

    public function tipoMenu()
    {
        return $this->belongsTo(ViandaTipoMenu::class, 'vianda_tipo_menu_id');
    }

    public function centrocosto()
    {
        return $this->belongsTo(Centrocosto::class, 'centrocosto_id');
    }

    public function jornada()
    {
        return $this->belongsTo(JornadaGastronomia::class, 'jornada_gastronomia_id');
    }

    public function operador()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function anuladoPor()
    {
        return $this->belongsTo(Usuario::class, 'anulado_usuario_id');
    }

    public function lineas()
    {
        return $this->hasMany(ViandaConsumoLinea::class, 'vianda_consumo_id')->orderBy('orden');
    }

    public function movimientos()
    {
        return $this->hasMany(\App\Models\Stock\Articulo_Movimiento::class, 'vianda_consumo_id');
    }

    public function anulado(): bool
    {
        return $this->estado === 'N';
    }

    /**
     * Nombre de empresa para EmpresaLogoArchivo (logos de cabecera en reportes/export).
     */
    public function getNombreempresaAttribute(): ?string
    {
        return $this->empresa?->nombre;
    }

    /**
     * Código de retiro correlativo derivado del id del consumo (numerador global).
     */
    public static function formatearCodigoRetiro(int $id): string
    {
        return 'V'.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    public function etiquetaEstado(): string
    {
        return match ($this->estado) {
            'A' => 'Activo',
            'N' => 'Anulado',
            default => (string) $this->estado,
        };
    }
}
