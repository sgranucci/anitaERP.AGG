<?php

declare(strict_types=1);

namespace App\Models\Contable;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class Canon_Municipal_Config extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'canon_municipal_config';

    protected $fillable = [
        'empresa_id',
        'municipio',
        'legajo',
        'periodicidad',
        'plantilla',
        'alicuota',
        'firmante_nombre',
        'firmante_cargo',
        'pie_razon_social',
        'direccion_extra',
        'telefono',
        'activo',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'alicuota' => 'float',
        'activo' => 'boolean',
    ];

    /** @var array<string, string> */
    public static array $enumPeriodicidad = [
        'semanal' => 'Semanal (lun–dom)',
        'quincenal' => 'Quincenal (1–15 / 16–fin)',
    ];

    /** @var array<string, string> */
    public static array $enumPlantilla = [
        'biyemas' => 'Biyemas (Avellaneda)',
        'kandiko' => 'Kandiko (Avellaneda)',
        'rebisco' => 'Rebisco (Florencio Varela)',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }
}
