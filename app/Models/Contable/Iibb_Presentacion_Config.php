<?php

declare(strict_types=1);

namespace App\Models\Contable;

use App\Models\Configuracion\Provincia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class Iibb_Presentacion_Config extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $table = 'iibb_presentacion_config';

    /** Valores sugeridos al crear la configuración (la pantalla los muestra y el operador los cambia). */
    public const TOLERANCIA_CONCILIACION_DEFAULT = 100;

    public const PALABRAS_BANCO_DEFAULT = 'BCO,BANCO';

    protected $fillable = [
        'provincia_id',
        'tipo',
        'nombre',
        'descripcion',
        'codigo_actividad_arba',
        'frecuencia',
        'tolerancia_conciliacion',
        'palabras_banco',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'tolerancia_conciliacion' => 'float',
    ];

    /**
     * Palabras que identifican un banco en el nombre del proveedor (separadas por coma o punto y coma).
     *
     * @return list<string>
     */
    public function palabrasBanco(): array
    {
        $partes = preg_split('/[,;]+/', (string) ($this->palabras_banco ?? '')) ?: [];

        return array_values(array_filter(array_map(static fn (string $p) => trim($p), $partes), static fn (string $p) => $p !== ''));
    }

    /** @var array<string, string> */
    public static array $enumTipo = [
        'retenciones' => 'Retenciones',
        'percepciones' => 'Percepciones',
    ];

    /** @var array<string, string> */
    public static array $enumFrecuencia = [
        'quincenal' => 'Quincenal',
        'mensual' => 'Mensual',
    ];

    public function provincia(): BelongsTo
    {
        return $this->belongsTo(Provincia::class, 'provincia_id');
    }

    public function cuentas(): HasMany
    {
        return $this->hasMany(Iibb_Presentacion_Config_Cuenta::class, 'iibb_presentacion_config_id');
    }
}
