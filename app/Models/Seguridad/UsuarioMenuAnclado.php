<?php

namespace App\Models\Seguridad;

use App\Models\Admin\Menu;
use Illuminate\Database\Eloquent\Model;

class UsuarioMenuAnclado extends Model
{
    protected $table = 'usuario_menu_anclado';

    public $timestamps = false;

    protected $fillable = ['usuario_id', 'menu_id', 'orden'];

    public function menu()
    {
        return $this->belongsTo(Menu::class, 'menu_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
