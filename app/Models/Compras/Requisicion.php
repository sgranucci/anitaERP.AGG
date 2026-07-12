<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Moneda;
use App\Models\Configuracion\Oficinacompra;
use App\Models\Contable\Centrocosto;
use App\Models\Seguridad\Usuario;
use App\Models\Ventas\Formapago;
use App\Traits\Compras\RequisicionTrait;

class Requisicion extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use RequisicionTrait;

    protected $fillable = [
        'fecha', 'fechaentrega', 'empresa_id', 'numerorequisicion', 'centrocosto_id', 'centrocostodestino_arbol_id', 'comentario', 'detalle', 
        'tratamiento', 'motivotratamiento', 'contrataciondirecta', 'proveedor_id', 'formapago_id',
        'estado', 'creousuario_id', 'oficinacompra_id',
        'anita_sync_estado', 'anita_sync_error', 'anita_sync_at',
    ];

    protected $table = 'requisicion';

    public function requisicion_estados()
    {
        return $this->hasMany(Requisicion_Estado::class, 'requisicion_id');
    }

    public function requisicion_articulos()
    {
        return $this->hasMany(Requisicion_Articulo::class, 'requisicion_id');
    }

    public function requisicion_archivos()
    {
        return $this->hasMany(Requisicion_Archivo::class, 'requisicion_id');
    }

    public function requisicion_presupuestos()
    {
        return $this->hasMany(Requisicion_Presupuesto::class, 'requisicion_id');
    }

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function centrocostos()
    {
        return $this->belongsTo(Centrocosto::class, 'centrocosto_id');
    }

    public function proveedores()
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function oficinacompras()
    {
        return $this->belongsTo(Oficinacompra::class, 'oficinacompra_id');
    }

    public function formapagos()
    {
        return $this->belongsTo(Formapago::class, 'formapago_id');
    }

    public function ordencompras()
    {
        return $this->hasMany(Ordencompra::class, 'requisicion_id');
    }

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'creousuario_id');
    }
}
