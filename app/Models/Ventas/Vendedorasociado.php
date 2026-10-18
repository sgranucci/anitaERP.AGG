<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Models\Configuracion\Empresa;
use Illuminate\Support\Str;
use App\Traits\Ventas\VendedorTrait;
use App\ApiAnita;
use OwenIt\Auditing\Contracts\Auditable;

class Vendedorasociado extends Model implements Auditable
{
    use VendedorTrait;
    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['vendedor_id', 'vendedorasociado_id'];
    protected $table = 'vendedorasociado';

    public function vendedores()
    {
        return $this->belongsTo(Vendedor::class, 'vendedor_id');
    }
    
    public function vendedorasociados()
    {
        return $this->belongsTo(Vendedor::class, 'vendedorasociado_id');
    }

}
