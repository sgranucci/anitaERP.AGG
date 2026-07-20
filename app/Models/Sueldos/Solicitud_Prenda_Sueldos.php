<?php

namespace App\Models\Sueldos;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;

class Solicitud_Prenda_Sueldos extends Model
{
    protected $table = 'solicitud_prenda_sueldos';

    public const BORRADOR = 'BORRADOR';

    public const PENDIENTE = 'PENDIENTE';

    public const APROBADA = 'APROBADA';

    public const RECHAZADA = 'RECHAZADA';

    public const ENTREGADA = 'ENTREGADA';

    public const ANULADA = 'ANULADA';

    public const ESTADOS = [
        self::BORRADOR => 'Borrador',
        self::PENDIENTE => 'Pendiente de aprobación',
        self::APROBADA => 'Aprobada',
        self::RECHAZADA => 'Rechazada',
        self::ENTREGADA => 'Entregada',
        self::ANULADA => 'Anulada',
    ];

    protected $fillable = [
        'empleado_id',
        'empresa_id',
        'agrupamiento_id',
        'fecha',
        'estado',
        'nivel_actual',
        'observacion',
        'solicitante_usuario_id',
        'entrega_id',
    ];

    protected $casts = [
        'empleado_id' => 'integer',
        'empresa_id' => 'integer',
        'agrupamiento_id' => 'integer',
        'fecha' => 'date',
        'nivel_actual' => 'integer',
        'solicitante_usuario_id' => 'integer',
        'entrega_id' => 'integer',
    ];

    public function articulos()
    {
        return $this->hasMany(Solicitud_Prenda_Articulo_Sueldos::class, 'solicitud_id');
    }

    public function aprobaciones()
    {
        return $this->hasMany(Solicitud_Prenda_Aprobacion_Sueldos::class, 'solicitud_id');
    }

    public function empleado()
    {
        return $this->belongsTo(Empleado_Sueldos::class, 'empleado_id');
    }

    public function solicitante()
    {
        return $this->belongsTo(Usuario::class, 'solicitante_usuario_id');
    }

    public function entrega()
    {
        return $this->belongsTo(Entrega_Prenda_Sueldos::class, 'entrega_id');
    }

    public function etiquetaEstado(): string
    {
        return self::ESTADOS[$this->estado] ?? (string) $this->estado;
    }
}
