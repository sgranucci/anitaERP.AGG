<?php

namespace App\Models\Sueldos;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Empleado_Base_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'empleado_base_sueldos';

    protected $fillable = [
        'empleado_id',
        'nombrebase_id',
        'valor',
        'fecha_vigencia',
        'valor_anterior',
        'usuario_id',
    ];

    protected $casts = [
        'empleado_id' => 'integer',
        'nombrebase_id' => 'integer',
        'valor' => 'decimal:4',
        'valor_anterior' => 'decimal:4',
        'fecha_vigencia' => 'date',
    ];

    public function empleado()
    {
        return $this->belongsTo(Empleado_Sueldos::class, 'empleado_id');
    }

    public function nombrebase()
    {
        return $this->belongsTo(Nombrebase_Sueldos::class, 'nombrebase_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
