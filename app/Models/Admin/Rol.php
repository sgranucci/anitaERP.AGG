<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use App\Models\Contable\Centrocosto;
use OwenIt\Auditing\Contracts\Auditable;
use App\Models\Admin\Permiso_Rol;

class Rol extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $table = "rol";
    protected $fillable = ['nombre', 'centrocosto_id'];

    public function roles()
    {
        return $this->belongsToMany(Rol::class);
    }

    public function centrocostos()
    {
        return $this->belongsTo(Centrocosto::class, 'centrocosto_id');
    }

}
