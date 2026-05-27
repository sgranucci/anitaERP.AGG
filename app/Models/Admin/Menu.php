<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Menu extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $table = "menu";
    protected $fillable = ['nombre', 'url', 'icono'];

    public function roles()
    {
        return $this->belongsToMany(Rol::class, 'menu_rol');
    }

    public function getHijos($padres, $line, &$nivelActual)
    {
        $nivelActual++;
        $children = [];
        foreach ($padres as $line1) {
            if ($line['id'] == $line1['menu_id']) {
                $children = array_merge($children, [array_merge($line1, ['submenu' => $this->getHijos($padres, $line1, $nivelActual), 'nivel' => $nivelActual])]);
                $nivelActual--;
            }
        }
        return $children;
    }

    public function getPadres($front)
    {
        if ($front) {
            return $this->whereHas('roles', function ($query) {
                $query->where('rol_id', session()->get('rol_id'))->orderby('menu_id');
            })->orderby('menu_id')
                ->orderby('orden')
                ->get()
                ->toArray();
        } else {
            return $this->orderby('menu_id')
                ->orderby('orden')
                ->get()
                ->toArray();
        }
    }

    public static function getMenu($front = false, $nivelActual)
    {
        $menus = new Menu();
        $padres = $menus->getPadres($front);
        $menuAll = [];
        foreach ($padres as $line) {
            if ($line['menu_id'] != 0)
                break;
            $item = [array_merge($line, ['submenu' => $menus->getHijos($padres, $line, $nivelActual), 'nivel' => $nivelActual])];
            $nivelActual--;
            $menuAll = array_merge($menuAll, $item);
        }
        return $menuAll;
    }

    public function guardarOrden($menu)
    {
        $menus = json_decode($menu);
        foreach ($menus as $var => $value) {
            $this->actualizarOrdenItem($value->id, 0, $var + 1);
            if (!empty($value->children)) {
                foreach ($value->children as $key => $vchild) {
                    $this->actualizarOrdenItem($vchild->id, $value->id, $key + 1);

                    if (!empty($vchild->children)) {
                        foreach ($vchild->children as $key => $vchild1) {
                            $this->actualizarOrdenItem($vchild1->id, $vchild->id, $key + 1);

                            if (!empty($vchild1->children)) {
                                foreach ($vchild1->children as $key => $vchild2) {
                                    $this->actualizarOrdenItem($vchild2->id, $vchild1->id, $key + 1);

                                    if (!empty($vchild2->children)) {
                                        foreach ($vchild2->children as $key => $vchild3) {
                                            $this->actualizarOrdenItem($vchild3->id, $vchild2->id, $key + 1);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    // Actualiza un item via instancia Eloquent para que owen-it/laravel-auditing registre el cambio.
    // Se asignan los atributos directamente (no via update()) porque menu_id y orden no están en
    // $fillable, así que el mass-assignment los descartaría silenciosamente.
    private function actualizarOrdenItem($id, $menuId, $orden): void
    {
        $registro = self::find($id);
        if (! $registro) {
            return;
        }

        if ((int) $registro->menu_id === (int) $menuId && (int) $registro->orden === (int) $orden) {
            return;
        }

        $registro->menu_id = $menuId;
        $registro->orden = $orden;
        $registro->save();
    }
}
