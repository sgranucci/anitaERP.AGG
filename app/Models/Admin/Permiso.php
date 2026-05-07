<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Permiso extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'permiso';

    protected $fillable = ['nombre', 'slug', 'menu_id'];

    public function roles()
    {
        return $this->belongsToMany(Rol::class, 'permiso_rol');
    }
}
