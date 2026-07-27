<?php

declare(strict_types=1);

namespace App\Models\Contable;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Iibb_Presentacion_Config_Cuenta extends Model
{
    protected $table = 'iibb_presentacion_config_cuenta';

    protected $fillable = [
        'iibb_presentacion_config_id',
        'empresa_id',
        'cuentacontable_id',
    ];

    public function config(): BelongsTo
    {
        return $this->belongsTo(Iibb_Presentacion_Config::class, 'iibb_presentacion_config_id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function cuentacontable(): BelongsTo
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontable_id');
    }
}
