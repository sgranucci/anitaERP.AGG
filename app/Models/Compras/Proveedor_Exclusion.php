<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Traits\Compras\Proveedor_ExclusionTrait;

class Proveedor_Exclusion extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use Proveedor_ExclusionTrait;
    
    protected $fillable = ['proveedor_id', 'comentario', 'tiporetencion', 'desdefecha', 'hastafecha', 
                            'porcentajeexclusion'];
    protected $table = 'proveedor_exclusion';

	public function proveedores()
	{
    	return $this->belongsTo(Proveedor::class, 'proveedor_id', 'id');
	}

}
