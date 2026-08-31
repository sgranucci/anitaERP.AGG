<?php

namespace App\Models\Sueldos;

use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Lsd_Presentacion_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'lsd_presentacion_sueldos';

    public const ESTADOS = [
        'generada' => 'Generada',
        'presentada' => 'Presentada en ARCA',
        'rechazada' => 'Rechazada',
    ];

    protected $fillable = [
        'empresa_id',
        'periodo',
        'liquidacion_id',
        'nro_liquidacion_afip',
        'identificacion',
        'tipo_liquidacion',
        'dias_base',
        'fecha_pago',
        'fecha_rubrica',
        'estado',
        'es_rectificativa',
        'presentacion_orig_id',
        'cantidad_registros_04',
        'cantidad_trabajadores',
        'archivo_hash',
        'archivo_nombre',
        'archivo_bytes',
        'validaciones_json',
        'observacion',
        'usuario_id',
        'generado_at',
        'presentado_at',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'periodo' => 'integer',
        'liquidacion_id' => 'integer',
        'nro_liquidacion_afip' => 'integer',
        'dias_base' => 'integer',
        'fecha_pago' => 'date',
        'fecha_rubrica' => 'date',
        'es_rectificativa' => 'boolean',
        'presentacion_orig_id' => 'integer',
        'cantidad_registros_04' => 'integer',
        'cantidad_trabajadores' => 'integer',
        'archivo_bytes' => 'integer',
        'validaciones_json' => 'array',
        'usuario_id' => 'integer',
        'generado_at' => 'datetime',
        'presentado_at' => 'datetime',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function liquidacion()
    {
        return $this->belongsTo(Liquidacion_Sueldos::class, 'liquidacion_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function origen()
    {
        return $this->belongsTo(self::class, 'presentacion_orig_id');
    }

    public function registros()
    {
        return $this->hasMany(Lsd_Presentacion_Registro_Sueldos::class, 'presentacion_id')
            ->orderBy('nro_linea');
    }

    public function estadoLabel(): string
    {
        return self::ESTADOS[$this->estado] ?? (string) $this->estado;
    }

    public function getNombreempresaAttribute(): string
    {
        return (string) ($this->empresa->nombre ?? '');
    }
}
