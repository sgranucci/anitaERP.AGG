<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use OwenIt\Auditing\Contracts\Auditable;

class Menu_Rol extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $table = "menu_rol";
    public $incrementing = true;
}
