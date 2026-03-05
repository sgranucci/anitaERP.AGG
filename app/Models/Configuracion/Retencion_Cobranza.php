<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;
use App\Traits\Configuracion\Retencion_CobranzaTrait;
use Illuminate\Support\Str;

class Retencion_Cobranza extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use Retencion_CobranzaTrait;

    protected $fillable = ['nombre', 'tiporetencion', 'provincia_id'];
    protected $table = 'retencion_cobranza';

    public function provincias()
    {
        return $this->belongsTo(Provincia::class, 'provincia_id');
    }

    public function retencion_cobranza_cuentacontables()
	{
    	return $this->hasMany(Retencion_Cobranza_Cuentacontable::class, 'retencion_cobranza_id');
	}    
}
