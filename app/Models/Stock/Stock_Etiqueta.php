<?php

namespace App\Models\Stock;

use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;

class Stock_Etiqueta extends Model
{
    protected $table = 'stock_etiqueta';

    protected $fillable = [
        'empresa_id', 'articulo_id', 'deposito_id', 'unidadmedida_id',
        'separa_unidadmedida_id', 'cant_unid_separa',
        'estado', 'origen_tipo', 'origen_id', 'origen_linea_id', 'articulo_movimiento_id',
        'etiqueta_origen_id',
        'lote_proveedor', 'fecha_vto', 'fecha_emision', 'hora_emision',
        'cant_pieza', 'peso_bruto', 'peso_neto', 'nro_establecimiento', 'descripcion_snapshot',
        'anita_proveedor', 'anita_tipo', 'anita_letra', 'anita_sucursal', 'anita_nro',
        'anita_orden', 'anita_nro_interno', 'anita_nro_apertura',
        'usuario_id',
    ];

    protected $casts = [
        'fecha_vto' => 'date',
        'fecha_emision' => 'date',
        'cant_pieza' => 'decimal:4',
        'peso_bruto' => 'decimal:4',
        'peso_neto' => 'decimal:4',
    ];

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function articulos()
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }

    public function depositos()
    {
        return $this->belongsTo(Depmae::class, 'deposito_id');
    }

    public function unidadesmedida()
    {
        return $this->belongsTo(Unidadmedida::class, 'unidadmedida_id');
    }

    public function separaUnidadmedida()
    {
        return $this->belongsTo(UnidadmedidaSurmar::class, 'separa_unidadmedida_id');
    }

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
