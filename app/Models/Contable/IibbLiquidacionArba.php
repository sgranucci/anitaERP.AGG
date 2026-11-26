<?php

declare(strict_types=1);

namespace App\Models\Contable;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Liquidación ARBA generada (TXT neteado + PDF) por empresa, quincena y configuración.
 */
class IibbLiquidacionArba extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'iibb_liquidacion_arba';

    protected $fillable = [
        'empresa_id',
        'iibb_presentacion_config_id',
        'periodo',
        'quincena',
        'nombre_txt',
        'nombre_pdf',
        'ruta_pdf',
        'total_erp',
        'total_neteos',
        'total_txt',
        'total_pendientes',
        'total_dj',
        'vep_esperado',
        'conciliacion_ok',
        'usuario_id',
    ];

    protected $casts = [
        'total_erp' => 'float',
        'total_neteos' => 'float',
        'total_txt' => 'float',
        'total_pendientes' => 'float',
        'total_dj' => 'float',
        'vep_esperado' => 'float',
        'conciliacion_ok' => 'boolean',
        'quincena' => 'integer',
    ];
}
