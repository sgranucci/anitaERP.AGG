<?php

namespace App\Models\Admin;

use App\Support\Admin\MenuFerliSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
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

    /**
     * Arma los hijos de un ítem. El nivel se calcula por profundidad (sin mutar un
     * contador compartido): el bug anterior dejaba hermanos/nietos con tipografía incorrecta.
     *
     * @param  array<int, array<string, mixed>>  $padres
     * @param  array<string, mixed>  $line
     * @return array<int, array<string, mixed>>
     */
    public function getHijos($padres, $line, $nivelPadre)
    {
        $nivelHijo = (int) $nivelPadre + 1;
        $children = [];
        foreach ($padres as $line1) {
            if ((int) $line['id'] === (int) $line1['menu_id']) {
                $children[] = array_merge($line1, [
                    'submenu' => $this->getHijos($padres, $line1, $nivelHijo),
                    'nivel' => $nivelHijo,
                ]);
            }
        }

        return $children;
    }

    public function getPadres($front)
    {
        if ($front) {
            $menus = $this->whereHas('roles', function ($query) {
                $query->where('rol_id', session()->get('rol_id'))->orderby('menu_id');
            })->orderby('menu_id')
                ->orderby('orden')
                ->orderby('id')
                ->get()
                ->toArray();
        } else {
            $menus = $this->orderby('menu_id')
                ->orderby('orden')
                ->orderby('id')
                ->get()
                ->toArray();
        }

        if ($front) {
            $menus = self::etiquetasFront($menus);
        }

        return MenuFerliSupport::filtrar($menus);
    }

    /**
     * El registro sigue llamándose «Módulo Sueldos y Jornales» (las migraciones lo buscan así).
     * En AGG el menú lateral muestra Capital Humano.
     *
     * @param  list<array<string, mixed>>  $menus
     * @return list<array<string, mixed>>
     */
    private static function etiquetasFront(array $menus): array
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return $menus;
        }

        foreach ($menus as &$menu) {
            if ((int) ($menu['menu_id'] ?? 0) === 0 && ($menu['nombre'] ?? '') === 'Módulo Sueldos y Jornales') {
                $menu['nombre'] = 'Capital Humano';
            }
        }
        unset($menu);

        return $menus;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function getMenu($front = false, $nivelActual = 0)
    {
        $menus = new Menu();
        $padres = $menus->getPadres($front);
        $menuAll = [];
        $nivelRaiz = (int) $nivelActual;
        foreach ($padres as $line) {
            if ((int) $line['menu_id'] !== 0) {
                break;
            }
            $menuAll[] = array_merge($line, [
                'submenu' => $menus->getHijos($padres, $line, $nivelRaiz),
                'nivel' => $nivelRaiz,
            ]);
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
