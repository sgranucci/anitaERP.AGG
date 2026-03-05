<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Configuracion\Empresa;
use App\Models\Caja\Caja;
use App\Models\Compras\Proveedor;
use App\Models\Contable\Asiento;
use App\Models\Seguridad\Usuario;
use Auth;

class Caja_Asignacion extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    
    protected $fillable = ['empresa_id', 'caja_id', 'usuario_id', 'fecha']; 
    protected $table = 'caja_asignacion';

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function cajas()
    {
        return $this->belongsTo(Caja::class, 'caja_id');
    }

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

}
