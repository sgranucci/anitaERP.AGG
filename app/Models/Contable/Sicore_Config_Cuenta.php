<?php

declare(strict_types=1);

namespace App\Models\Contable;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sicore_Config_Cuenta extends Model
{
    protected $table = 'sicore_config_cuenta';

    protected $fillable = [
        'sicore_config_id',
        'empresa_id',
        'cuentacontable_id',
    ];

    public function sicoreConfig(): BelongsTo
    {
        return $this->belongsTo(Sicore_Config::class, 'sicore_config_id');
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
