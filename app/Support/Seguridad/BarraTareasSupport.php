<?php

namespace App\Support\Seguridad;

use App\Models\Admin\Menu;
use App\Models\Seguridad\UsuarioMenuAnclado;
use App\Support\Caja\Estacionamiento\EstacionamientoModuloSupport;
use Illuminate\Support\Facades\DB;

class BarraTareasSupport
{
    public const MAX_ANCLADOS = 20;

    /** @var array<int, string>|null */
    private ?array $mapaIconosMenu = null;

    /**
     * @return array<int, array{menu_id: int, nombre: string, url: string, icono: string, icono_clases: string, activo: bool}>
     */
    public function ancladosResueltos(?int $usuarioId = null): array
    {
        $usuarioId = $usuarioId ?? (int) auth()->id();
        if ($usuarioId <= 0) {
            return [];
        }

        $permitidos = $this->idsMenuPermitidosRol();
        if ($permitidos === []) {
            return [];
        }

        $filas = UsuarioMenuAnclado::query()
            ->where('usuario_id', $usuarioId)
            ->whereIn('menu_id', $permitidos)
            ->orderBy('orden')
            ->orderBy('id')
            ->with('menu')
            ->get();

        $resultado = [];
        foreach ($filas as $fila) {
            $menu = $fila->menu;
            if ($menu === null || trim((string) $menu->url) === '') {
                continue;
            }

            $url = trim((string) $menu->url);
            $icono = $this->resolverIconoMenuId((int) $menu->id);
            $resultado[] = [
                'menu_id' => (int) $menu->id,
                'nombre' => (string) $menu->nombre,
                'url' => $url,
                'icono' => $icono,
                'icono_clases' => clasesIconoMenu($icono),
                'activo' => getMenuActivo($url) === 'active',
            ];
        }

        return $resultado;
    }

    /**
     * @return array<int>
     */
    public function idsAnclados(?int $usuarioId = null): array
    {
        return array_column($this->ancladosResueltos($usuarioId), 'menu_id');
    }

    /**
     * @return array<int, array{id: int, nombre: string, nombre_ruta: string, url: string, icono: string, icono_clases: string, anclado: bool}>
     */
    public function menusDisponibles(): array
    {
        $anclados = $this->idsAnclados();
        $menus = $this->arbolMenuFront();
        $hojas = $this->aplanarMenusHoja($menus);

        return array_values(array_map(function (array $item) use ($anclados) {
            $item['anclado'] = in_array($item['id'], $anclados, true);

            return $item;
        }, $hojas));
    }

    public function anclar(int $menuId, ?int $usuarioId = null): array
    {
        $usuarioId = $usuarioId ?? (int) auth()->id();
        $this->validarMenuAnclable($menuId);

        $cantidad = UsuarioMenuAnclado::query()
            ->where('usuario_id', $usuarioId)
            ->count();

        if ($cantidad >= self::MAX_ANCLADOS) {
            throw new \InvalidArgumentException('Alcanzó el máximo de '.self::MAX_ANCLADOS.' programas anclados.');
        }

        $existe = UsuarioMenuAnclado::query()
            ->where('usuario_id', $usuarioId)
            ->where('menu_id', $menuId)
            ->exists();

        if ($existe) {
            return $this->ancladosResueltos($usuarioId);
        }

        $orden = (int) UsuarioMenuAnclado::query()
            ->where('usuario_id', $usuarioId)
            ->max('orden');

        UsuarioMenuAnclado::query()->create([
            'usuario_id' => $usuarioId,
            'menu_id' => $menuId,
            'orden' => $orden + 1,
        ]);

        return $this->ancladosResueltos($usuarioId);
    }

    public function desanclar(int $menuId, ?int $usuarioId = null): array
    {
        $usuarioId = $usuarioId ?? (int) auth()->id();

        UsuarioMenuAnclado::query()
            ->where('usuario_id', $usuarioId)
            ->where('menu_id', $menuId)
            ->delete();

        return $this->ancladosResueltos($usuarioId);
    }

    /**
     * @param  array<int>  $menuIds
     */
    public function reordenar(array $menuIds, ?int $usuarioId = null): array
    {
        $usuarioId = $usuarioId ?? (int) auth()->id();
        $permitidos = $this->idsMenuPermitidosRol();
        $menuIds = array_values(array_unique(array_map('intval', $menuIds)));
        $menuIds = array_values(array_filter($menuIds, fn (int $id) => in_array($id, $permitidos, true)));

        DB::transaction(function () use ($usuarioId, $menuIds) {
            foreach ($menuIds as $orden => $menuId) {
                UsuarioMenuAnclado::query()
                    ->where('usuario_id', $usuarioId)
                    ->where('menu_id', $menuId)
                    ->update(['orden' => $orden + 1]);
            }
        });

        return $this->ancladosResueltos($usuarioId);
    }

    public function estaAnclado(int $menuId, ?int $usuarioId = null): bool
    {
        $usuarioId = $usuarioId ?? (int) auth()->id();

        return in_array($menuId, $this->idsAnclados($usuarioId), true);
    }

    private function validarMenuAnclable(int $menuId): void
    {
        $permitidos = $this->idsMenuPermitidosRol();
        if (! in_array($menuId, $permitidos, true)) {
            throw new \InvalidArgumentException('No tiene permiso para anclar este programa.');
        }

        $menu = Menu::query()->find($menuId);
        if ($menu === null || trim((string) $menu->url) === '') {
            throw new \InvalidArgumentException('Solo se pueden anclar programas con enlace directo.');
        }
    }

    /**
     * @return array<int>
     */
    private function idsMenuPermitidosRol(): array
    {
        $rolId = (int) session()->get('rol_id');
        if ($rolId <= 0) {
            return [];
        }

        return Menu::query()
            ->whereHas('roles', function ($query) use ($rolId) {
                $query->where('rol_id', $rolId);
            })
            ->whereNotNull('url')
            ->where('url', '!=', '')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function arbolMenuFront(): array
    {
        $nivelActual = 0;
        $menus = Menu::getMenu(true, $nivelActual);

        return EstacionamientoModuloSupport::filtrarMenuAside($menus);
    }

    /**
     * @param  array<int, array<string, mixed>>  $menus
     * @return array<int, array{id: int, nombre: string, nombre_ruta: string, url: string, icono: string, icono_clases: string}>
     */
    private function aplanarMenusHoja(array $menus, string $prefijo = '', ?string $iconoAncestro = null): array
    {
        $resultado = [];

        foreach ($menus as $item) {
            $nombreVisible = (string) ($item['nombre'] ?? '');
            $nombreRuta = $prefijo !== '' ? $prefijo.' › '.$nombreVisible : $nombreVisible;
            $iconoPropio = $this->iconoMenuItem($item['icono'] ?? null);
            $iconoEfectivo = $iconoPropio ?? $iconoAncestro;

            if (! empty($item['submenu'])) {
                $iconoParaHijos = $iconoPropio ?? $iconoAncestro;
                $resultado = array_merge(
                    $resultado,
                    $this->aplanarMenusHoja($item['submenu'], $nombreRuta, $iconoParaHijos)
                );

                continue;
            }

            $url = trim((string) ($item['url'] ?? ''));
            if ($url === '') {
                continue;
            }

            $icono = $iconoEfectivo ?? 'fa-circle';
            $resultado[] = [
                'id' => (int) $item['id'],
                'nombre' => $nombreVisible,
                'nombre_ruta' => $nombreRuta,
                'url' => $url,
                'icono' => $icono,
                'icono_clases' => clasesIconoMenu($icono),
            ];
        }

        return $resultado;
    }

    private function resolverIconoMenuId(int $menuId): string
    {
        $mapa = $this->mapaIconosMenu();

        return $mapa[$menuId] ?? 'fa-circle';
    }

    /**
     * @return array<int, string>
     */
    private function mapaIconosMenu(): array
    {
        if ($this->mapaIconosMenu !== null) {
            return $this->mapaIconosMenu;
        }

        $menus = Menu::query()
            ->select(['id', 'menu_id', 'icono'])
            ->get()
            ->keyBy('id');

        $mapa = [];
        foreach ($menus as $id => $menu) {
            $mapa[(int) $id] = $this->resolverIconoDesdeCadena((int) $id, $menus);
        }

        return $this->mapaIconosMenu = $mapa;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\Admin\Menu>  $menus
     */
    private function resolverIconoDesdeCadena(int $menuId, $menus): string
    {
        $visitados = [];
        $actual = $menus->get($menuId);

        while ($actual !== null) {
            $idActual = (int) $actual->id;
            if (in_array($idActual, $visitados, true)) {
                break;
            }
            $visitados[] = $idActual;

            $icono = $this->iconoMenuItem($actual->icono);
            if ($icono !== null) {
                return $icono;
            }

            $padreId = (int) $actual->menu_id;
            if ($padreId === 0) {
                break;
            }

            $actual = $menus->get($padreId);
        }

        return 'fa-circle';
    }

    private function iconoMenuItem(mixed $icono): ?string
    {
        $icono = trim((string) $icono);
        if ($icono === '') {
            return null;
        }

        if (preg_match('/^(fa|fas|far|fab|fal|fad)\s+/', $icono)) {
            return $icono;
        }

        if (str_contains($icono, 'fa-')) {
            return $icono;
        }

        return 'fa-'.ltrim($icono, '-');
    }

    private function normalizarIcono(?string $icono): string
    {
        return $this->iconoMenuItem($icono) ?? 'fa-circle';
    }
}
