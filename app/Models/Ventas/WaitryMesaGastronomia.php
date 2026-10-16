<?php

namespace App\Models\Ventas;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class WaitryMesaGastronomia extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'waitry_mesa_gastronomia';

    protected $fillable = [
        'empresa_id',
        'servicio',
        'nombre',
        'table_id',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'table_id' => 'integer',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function etiqueta(): string
    {
        return trim($this->servicio.' — '.$this->nombre).' ('.$this->table_id.')';
    }
}
