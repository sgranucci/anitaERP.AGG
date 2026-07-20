<?php

namespace App\Models\Sueldos;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Categoria_Base_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'categoria_base_sueldos';

    protected $fillable = [
        'categoria_id',
        'nombrebase_id',
        'valor',
        'fecha_vigencia',
        'valor_anterior',
        'usuario_id',
    ];

    protected $casts = [
        'categoria_id' => 'integer',
        'nombrebase_id' => 'integer',
        'valor' => 'decimal:4',
        'valor_anterior' => 'decimal:4',
        'usuario_id' => 'integer',
        'fecha_vigencia' => 'date',
    ];

    public function categoria()
    {
        return $this->belongsTo(Categoria_Sueldos::class, 'categoria_id');
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
