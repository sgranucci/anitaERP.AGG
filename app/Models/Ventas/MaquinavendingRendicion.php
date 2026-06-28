<?php

namespace App\Models\Ventas;

use App\Models\Caja\RendicionMaquinavendingCaja;
use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class MaquinavendingRendicion extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'empresa_id',
        'maquinavending_id',
        'numero_cierre',
        'codigo',
        'nro_oper_anita',
        'fuente_nro_oper',
        'anita_sincronizado_en',
        'fecha_rendicion',
        'fecha_jornada',
        'total_ventas',
        'total_cobrado',
        'observacion',
        'usuario_id',
    ];

    protected $casts = [
        'numero_cierre' => 'integer',
        'nro_oper_anita' => 'integer',
        'anita_sincronizado_en' => 'datetime',
        'fecha_rendicion' => 'datetime',
        'fecha_jornada' => 'date',
        'total_ventas' => 'float',
        'total_cobrado' => 'float',
    ];

    protected $table = 'maquinavending_rendicion';

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function maquinavending()
    {
        return $this->belongsTo(Maquinavending::class, 'maquinavending_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function articulos()
    {
        return $this->hasMany(MaquinavendingRendicionArticulo::class, 'maquinavending_rendicion_id')
            ->orderBy('numero_rulo');
    }

    public function mediosPago()
    {
        return $this->hasMany(MaquinavendingRendicionMedioPago::class, 'maquinavending_rendicion_id');
    }

    public function rendicionCaja()
    {
        return $this->hasOne(RendicionMaquinavendingCaja::class, 'maquinavending_rendicion_id');
    }

    public function estaPresentadaEnCaja(): bool
    {
        return $this->relationLoaded('rendicionCaja')
            ? $this->rendicionCaja !== null
            : RendicionMaquinavendingCaja::query()
                ->where('maquinavending_rendicion_id', $this->id)
                ->exists();
    }
}
