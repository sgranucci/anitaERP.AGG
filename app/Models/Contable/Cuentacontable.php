<?php

namespace App\Models\Contable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Configuracion\Empresa;
use App\Models\Caja\Conceptogasto;
use App\Traits\Contable\AjustamonedaextranjeraTrait;
use Auth;

class Cuentacontable extends Model
{
    use AjustamonedaextranjeraTrait;

    protected $fillable = ['empresa_id', 'rubrocontable_id', 'nivel', 
                            'nombre', 'codigo', 'tipocuenta', 'monetaria', 'manejaccosto', 
                            'usuarioultcambio_id', 'ajustamonedaextrajera', 'conceptogasto_id',
                            'cuentacontable_difcambio_id'];
    protected $table = 'cuentacontable';

    public function cuentacontable_centrocostos()
	{
    	return $this->hasMany(Cuentacontable_Centrocosto::class, 'cuentacontable_id')
                    ->with('centrocostos');
	}

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function rubrocontables()
    {
        return $this->belongsTo(Rubrocontable::class, 'rubrocontable_id');
    }

    public function conceptogastos()
    {
        return $this->belongsTo(Conceptogasto::class, 'conceptogasto_id');
    }

}
