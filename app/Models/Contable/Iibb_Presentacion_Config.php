<?php

declare(strict_types=1);

namespace App\Models\Contable;

use App\Models\Configuracion\Provincia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Iibb_Presentacion_Config extends Model
{
    protected $table = 'iibb_presentacion_config';

    protected $fillable = [
        'provincia_id',
        'tipo',
        'nombre',
        'descripcion',
        'codigo_actividad_arba',
        'frecuencia',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    /** @var array<string, string> */
    public static array $enumTipo = [
        'retenciones' => 'Retenciones ARBA',
        'percepciones' => 'Percepciones ARBA',
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
