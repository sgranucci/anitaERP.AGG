<?php

namespace App\Models\Caja\Estacionamiento;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class ItemEstacionamiento extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const ESTADO_ACTIVO = 'activo';

    public const ESTADO_SUSPENDIDO = 'suspendido';

    /** @var array<int, array{valor: string, nombre: string}> */
    public static array $enumEstado = [
        ['valor' => self::ESTADO_ACTIVO, 'nombre' => 'Activo'],
        ['valor' => self::ESTADO_SUSPENDIDO, 'nombre' => 'Suspendido'],
    ];

    protected $table = 'item_estacionamiento';

    protected $fillable = ['empresa_id', 'nombre', 'estado'];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    protected $attributes = [
        'estado' => self::ESTADO_ACTIVO,
    ];

    public function getEstadoLabelAttribute(): string
    {
        foreach (self::$enumEstado as $row) {
            if ($row['valor'] === $this->estado) {
                return $row['nombre'];
            }
        }

        return (string) ($this->estado ?? '');
    }
}
