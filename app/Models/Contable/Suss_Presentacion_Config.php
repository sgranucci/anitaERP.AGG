<?php

declare(strict_types=1);

namespace App\Models\Contable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class Suss_Presentacion_Config extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $table = 'suss_presentacion_config';

    protected $fillable = [
        'nombre',
        'descripcion',
        'codigo_impuesto',
        'codigo_regimen',
        'frecuencia',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'codigo_impuesto' => 'integer',
        'codigo_regimen' => 'integer',
    ];

    /** @var array<string, string> */
    public static array $enumFrecuencia = [
        'quincenal' => 'Quincenal',
        'mensual' => 'Mensual',
    ];

    public function cuentas(): HasMany
    {
        return $this->hasMany(Suss_Presentacion_Config_Cuenta::class, 'suss_presentacion_config_id');
    }
}
