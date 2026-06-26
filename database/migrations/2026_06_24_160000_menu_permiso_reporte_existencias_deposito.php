<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
  private const MENU_PADRE_NOMBRE = 'Informes de stock';

  private const MENU_URL = 'stock/informes-de-stock/existencias-por-deposito';

  private const PERMISO_SLUG = 'listar-reporte-existencias-deposito';

  /** @var list<string> */
  private const ROLES_OBJETIVO = [
    'Enc-gastronomía',
    'Sup-Gastronomia',
    'Ger-Gastronomia',
    'Enc-logistica',
    'op-Logistica',
  ];

  public function up(): void
  {
    $stockMenuId = (int) (DB::table('menu')->where('url', '#')->where('nombre', 'Módulo de Stock')->value('id') ?? 10);

    $informesPadreId = (int) (DB::table('menu')
      ->where('menu_id', $stockMenuId)
      ->where('nombre', self::MENU_PADRE_NOMBRE)
      ->where('url', '#')
      ->value('id') ?? 0);

    if ($informesPadreId <= 0) {
      $ordenPadre = (int) (DB::table('menu')->where('menu_id', $stockMenuId)->max('orden') ?? 0) + 1;
      $informesPadreId = (int) DB::table('menu')->insertGetId([
        'menu_id' => $stockMenuId,
        'nombre' => self::MENU_PADRE_NOMBRE,
        'url' => '#',
        'orden' => $ordenPadre,
        'icono' => 'fa-chart-bar',
        'created_at' => now(),
        'updated_at' => now(),
      ]);
    }

    $orden = (int) (DB::table('menu')->where('menu_id', $informesPadreId)->max('orden') ?? 0) + 1;
    $menuId = $this->upsertMenu(self::MENU_URL, 'Existencias por depósito', $informesPadreId, $orden, 'fa-cubes');
    $permisoId = $this->upsertPermiso('Listar reporte existencias por depósito', self::PERMISO_SLUG, $menuId);

    $rolIds = $this->resolverRolesObjetivo();
    foreach ($rolIds as $rolId) {
      DB::table('menu_rol')->updateOrInsert(['menu_id' => $menuId, 'rol_id' => $rolId], []);
      DB::table('permiso_rol')->updateOrInsert(['permiso_id' => $permisoId, 'rol_id' => $rolId], []);
    }

    SuitecrmPermiso::flushCachePermisos();
  }

  public function down(): void
  {
    $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
    if ($permisoId > 0) {
      DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
      DB::table('permiso')->where('id', $permisoId)->delete();
    }

    $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
    if ($menuId > 0) {
      DB::table('menu_rol')->where('menu_id', $menuId)->delete();
      DB::table('menu')->where('id', $menuId)->delete();
    }

    SuitecrmPermiso::flushCachePermisos();
  }

  /** @return list<int> */
  private function resolverRolesObjetivo(): array
  {
    $rolIds = [];

    foreach (self::ROLES_OBJETIVO as $nombre) {
      $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
      if ($id > 0) {
        $rolIds[] = $id;
      }
    }

    $encGastro = (int) (DB::table('rol')->where('nombre', 'like', 'Enc-gastronom%')->orderBy('id')->value('id') ?? 0);
    if ($encGastro > 0) {
      $rolIds[] = $encGastro;
    }

    return array_values(array_unique($rolIds));
  }

  private function upsertPermiso(string $nombre, string $slug, int $menuId): int
  {
    $id = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
    $payload = ['nombre' => $nombre, 'menu_id' => $menuId, 'updated_at' => now()];

    if ($id > 0) {
      DB::table('permiso')->where('id', $id)->update($payload);

      return $id;
    }

    return (int) DB::table('permiso')->insertGetId(array_merge($payload, [
      'slug' => $slug,
      'created_at' => now(),
    ]));
  }

  private function upsertMenu(string $url, string $nombre, int $padre, int $orden, string $icono): int
  {
    $id = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);

    if ($id === 0) {
      return (int) DB::table('menu')->insertGetId([
        'menu_id' => $padre,
        'nombre' => $nombre,
        'url' => $url,
        'orden' => $orden,
        'icono' => $icono,
        'created_at' => now(),
        'updated_at' => now(),
      ]);
    }

    DB::table('menu')->where('id', $id)->update([
      'menu_id' => $padre,
      'nombre' => $nombre,
      'orden' => $orden,
      'icono' => $icono,
      'updated_at' => now(),
    ]);

    return $id;
  }
};
