<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MENU_URL = 'configuracion/regimen-percepcion';

    /** @var list<string> */
    private const ROLES = [
        'administrador',
        'Enc-admin',
        'Enc-contaduría',
        'Enc-impuestos',
        'Op-impuestos',
        'Ger-administracion',
    ];

    /** @var list<array{nombre: string, slug: string}> */
    private const PERMISOS = [
        ['nombre' => 'Listar regímenes de percepción', 'slug' => 'listar-regimen-percepcion'],
        ['nombre' => 'Crear regímenes de percepción', 'slug' => 'crear-regimen-percepcion'],
        ['nombre' => 'Editar regímenes de percepción', 'slug' => 'editar-regimen-percepcion'],
        ['nombre' => 'Actualizar regímenes de percepción', 'slug' => 'actualizar-regimen-percepcion'],
        ['nombre' => 'Borrar regímenes de percepción', 'slug' => 'borrar-regimen-percepcion'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('regimen_percepcion')) {
            Schema::create('regimen_percepcion', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 20);
                $table->string('nombre', 80);
                $table->boolean('habilitado')->default(false);
                $table->decimal('tasa', 8, 4)->default(0);
                $table->decimal('minimo_base', 15, 2)->default(0);
                $table->decimal('minimo_importe', 15, 2)->default(0);
                $table->date('vigencia_desde')->nullable();
                $table->date('vigencia_hasta')->nullable();
                $table->unsignedBigInteger('impuesto_id')->nullable();
                $table->timestamps();
                $table->unique('codigo');
                $table->foreign('impuesto_id', 'fk_regimen_percepcion_impuesto')
                    ->references('id')->on('impuesto')
                    ->onDelete('set null')
                    ->onUpdate('cascade');
            });
        }

        $this->sembrarRegimenes();
        $this->asegurarMenuYPermisos();
        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        foreach (self::PERMISOS as $perm) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $perm['slug'])->value('id') ?? 0);
            if ($permisoId > 0) {
                DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
                DB::table('permiso')->where('id', $permisoId)->delete();
            }
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        Schema::dropIfExists('regimen_percepcion');
        SuitecrmPermiso::flushCachePermisos();
    }

    private function sembrarRegimenes(): void
    {
        $ahora = now();
        $impuestoPncId = Schema::hasTable('impuesto')
            ? DB::table('impuesto')->where('codigo', 'PNC')->value('id')
            : null;

        $pncLegacy = null;
        if (Schema::hasTable('configuracion_percepcion_no_categorizado')) {
            $pncLegacy = DB::table('configuracion_percepcion_no_categorizado')->orderBy('id')->first();
        }

        $this->asegurarFila('PIVA', [
            'nombre' => 'Percepción IVA RI (RG 5329)',
            'habilitado' => strtolower(trim((string) config('anita.agente_percepcion_iva', 'no'))) === 'si',
            'tasa' => (float) config('anita.tasa_percepcion_iva', 0),
            'minimo_base' => (float) config('anita.minimo_base_percepcion_iva', 0),
            'minimo_importe' => (float) config('anita.minimo_importe_percepcion_iva', 0),
            'vigencia_desde' => '2000-01-01',
            'vigencia_hasta' => null,
            'impuesto_id' => null,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);

        $this->asegurarFila('PNC', [
            'nombre' => 'Percepción IVA no categorizado (RG 2126)',
            'habilitado' => $pncLegacy !== null
                ? (bool) $pncLegacy->habilitado
                : strtolower(trim((string) config('anita.agente_percepcion_no_categorizado', 'no'))) === 'si',
            'tasa' => $pncLegacy !== null && (float) $pncLegacy->tasa > 0.0001
                ? (float) $pncLegacy->tasa
                : (float) config('anita.tasa_percepcion_no_categorizado', 10.5),
            'minimo_base' => 0,
            'minimo_importe' => $pncLegacy !== null
                ? (float) $pncLegacy->minimo
                : (float) config('anita.minimo_percepcion_no_categorizado', 0),
            'vigencia_desde' => '2000-01-01',
            'vigencia_hasta' => null,
            'impuesto_id' => $impuestoPncId !== null ? (int) $impuestoPncId : null,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function asegurarFila(string $codigo, array $datos): void
    {
        $existe = DB::table('regimen_percepcion')->where('codigo', $codigo)->exists();
        if ($existe) {
            return;
        }

        DB::table('regimen_percepcion')->insert(array_merge(['codigo' => $codigo], $datos));
    }

    private function asegurarMenuYPermisos(): void
    {
        $padreId = $this->resolverMenuPadreId();
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $ordenImpuesto = (int) (DB::table('menu')->where('url', 'configuracion/impuesto')->value('orden') ?? 0);
        $orden = $menuId > 0
            ? (int) (DB::table('menu')->where('id', $menuId)->value('orden') ?? 1)
            : ($ordenImpuesto > 0 ? $ordenImpuesto + 1 : (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1);

        if ($menuId === 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $padreId,
                'nombre' => 'Regímenes de percepción',
                'url' => self::MENU_URL,
                'orden' => $orden,
                'icono' => 'fa-percent',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $padreId,
                'nombre' => 'Regímenes de percepción',
                'orden' => $orden,
                'icono' => 'fa-percent',
                'updated_at' => now(),
            ]);
        }

        $rolIds = $this->resolverRolIds(self::ROLES);
        foreach ($rolIds as $rolId) {
            DB::table('menu_rol')->updateOrInsert(
                ['menu_id' => $menuId, 'rol_id' => $rolId],
                []
            );
        }

        foreach (self::PERMISOS as $perm) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $perm['slug'])->value('id') ?? 0);
            if ($permisoId === 0) {
                $permisoId = (int) DB::table('permiso')->insertGetId([
                    'nombre' => $perm['nombre'],
                    'slug' => $perm['slug'],
                    'menu_id' => $menuId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('permiso')->where('id', $permisoId)->update([
                    'menu_id' => $menuId,
                    'nombre' => $perm['nombre'],
                    'updated_at' => now(),
                ]);
            }
            foreach ($rolIds as $rolId) {
                DB::table('permiso_rol')->updateOrInsert(
                    ['permiso_id' => $permisoId, 'rol_id' => $rolId],
                    []
                );
            }
        }
    }

    private function resolverMenuPadreId(): int
    {
        $id = (int) (DB::table('menu')->where('url', 'configuracion/impuesto')->value('menu_id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        $id = (int) (DB::table('menu')->where('url', 'configuracion/empresa')->value('menu_id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        foreach (['Configuración', 'Módulo Configuración', 'Configuracion'] as $nombre) {
            $id = (int) (DB::table('menu')->where('nombre', $nombre)->where('menu_id', 0)->value('id') ?? 0);
            if ($id > 0) {
                return $id;
            }
        }

        return 33;
    }

    /** @param list<string> $nombres @return list<int> */
    private function resolverRolIds(array $nombres): array
    {
        $ids = [];
        foreach ($nombres as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
};
