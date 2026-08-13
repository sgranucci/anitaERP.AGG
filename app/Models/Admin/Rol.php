<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use App\Models\Contable\Centrocosto;
use App\Models\Seguridad\Usuario;
use OwenIt\Auditing\Contracts\Auditable;

class Rol extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $table = "rol";
    protected $fillable = ['nombre', 'centrocosto_id'];

    public function roles()
    {
        return $this->belongsToMany(Rol::class);
    }

    public function usuarios()
    {
        return $this->belongsToMany(Usuario::class, 'usuario_rol');
    }

    public function centrocostos()
    {
        return $this->belongsTo(Centrocosto::class, 'centrocosto_id');
    }

}
