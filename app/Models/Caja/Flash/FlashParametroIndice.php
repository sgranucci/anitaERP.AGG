<?php

namespace App\Models\Caja\Flash;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class FlashParametroIndice extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'flash_parametro_indice';

    protected $fillable = [
        'flash_parametro_id',
        'empresa_id',
        'fecha',
        'customer',
        'season_index',
        'sindex_bingo',
        'sindex_slot',
        'sindex_rul',
        'sindex_poker',
        'sindex_estac',
        'vehiculos',
    ];

    protected $casts = [
        'fecha' => 'date',
        'customer' => 'integer',
        'vehiculos' => 'integer',
        'season_index' => 'float',
        'sindex_bingo' => 'float',
        'sindex_slot' => 'float',
        'sindex_rul' => 'float',
        'sindex_poker' => 'float',
        'sindex_estac' => 'float',
    ];

    public function parametro()
    {
        return $this->belongsTo(FlashParametro::class, 'flash_parametro_id');
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }
}
