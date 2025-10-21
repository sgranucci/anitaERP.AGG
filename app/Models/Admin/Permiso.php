<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Models\Admin\Permiso_Rol;

class Permiso extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $table = "permiso";
    protected $fillable = ['nombre', 'slug'];

    public function roles()
    {
        return $this->belongsToMany(Rol::class, 'permiso_rol');
    }
}
