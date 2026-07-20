<?php

namespace App\Models\Sueldos;

use App\Models\Stock\Color;
use Illuminate\Database\Eloquent\Model;

class Prenda_Agrupamiento_Sueldos extends Model
{
    protected $table = 'prenda_agrupamiento_sueldos';

    public const SEXOS = [
        'M' => 'Masculino',
        'F' => 'Femenino',
    ];

    protected $fillable = [
        'agrupamiento_id',
        'sexo',
        'orden',
        'prenda_id',
        'color_id',
        'limite_anual',
    ];

    protected $casts = [
        'agrupamiento_id' => 'integer',
        'orden' => 'integer',
        'prenda_id' => 'integer',
        'color_id' => 'integer',
        'limite_anual' => 'decimal:2',
    ];

    public function agrupamiento()
    {
        return $this->belongsTo(Agrupamiento_Sueldos::class, 'agrupamiento_id');
    }

    public function prenda()
    {
        return $this->belongsTo(Prenda_Sueldos::class, 'prenda_id');
    }

    public function color()
    {
        return $this->belongsTo(Color::class, 'color_id');
    }

    public static function etiquetaSexo(?string $sexo): string
    {
        return self::SEXOS[$sexo] ?? '—';
    }

    /**
     * Mapea el sexo del empleado (Anita: '1' masculino, '2' femenino) al código
     * de texto usado en la dotación ('M'/'F'). Tolera que ya venga en 'M'/'F'.
     */
    public static function sexoDesdeEmpleado($sexoEmpleado): string
    {
        $v = strtoupper(trim((string) $sexoEmpleado));

        return match ($v) {
            '1', 'M', 'H', 'MASCULINO' => 'M',
            '2', 'F', 'MUJER', 'FEMENINO' => 'F',
            default => 'M',
        };
    }
}
