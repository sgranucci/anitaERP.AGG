<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MENU_URL = 'ventas/cot-electronico-historico';

    private const PERMISO_SLUG = 'listar-cot-electronico-historico';

    /** @var list<string> */
    private const ROLES = ['administrador', 'Enc-contaduría', 'Enc-impuestos'];

    public function up(): void
    {
        if (! Schema::hasTable('cot_sesion_envio')) {
            Schema::create('cot_sesion_envio', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->date('fecha_facturas');
                $table->timestamp('fecha_envio')->useCurrent();
                $table->string('ambiente', 10)->default('test');
                $table->string('nombre_archivo', 80)->nullable();
                $table->string('numero_comprobante_arba', 20)->nullable();
                $table->string('cuit_empresa', 15)->nullable();
                $table->string('codigo_integridad', 40)->nullable();
                $table->boolean('ok')->default(false);
                $table->text('error_general')->nullable();
                $table->unsignedInteger('cantidad_remitos')->default(0);
                $table->unsignedInteger('cantidad_ok')->default(0);
                $table->unsignedInteger('cantidad_error')->default(0);
                $table->json('repartos_json')->nullable();
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->timestamps();

                $table->index('fecha_envio');
                $table->index('fecha_facturas');
            });
        }

        if (Schema::hasTable('cot_remito_envio')) {
            Schema::table('cot_remito_envio', function (Blueprint $table) {
                if (! Schema::hasColumn('cot_remito_envio', 'cot_sesion_envio_id')) {
                    $table->unsignedBigInteger('cot_sesion_envio_id')->nullable()->after('id');
                    $table->index('cot_sesion_envio_id');
                }
                if (! Schema::hasColumn('cot_remito_envio', 'cliente_nombre')) {
                    $table->string('cliente_nombre', 200)->nullable()->after('cliente_id');
                }
            });

            if ($this->indexExists('cot_remito_envio', 'cot_remito_envio_uk')) {
                Schema::table('cot_remito_envio', function (Blueprint $table) {
                    $table->dropUnique('cot_remito_envio_uk');
                });
            }
        }

        $permisoId = $this->upsertPermiso();
        $this->asignarPermisoRoles($permisoId);

        $padreId = $this->resolverMenuVentasId();
        if ($padreId === 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;
        $menuId = $this->upsertMenu(self::MENU_URL, 'Histórico COT ARBA', $padreId, $orden, 'fa-history');

        foreach ($this->resolverRolIds() as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);

        return $indexes !== [];
    }

    private function upsertPermiso(): int
    {
        $id = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($id > 0) {
            DB::table('permiso')->where('id', $id)->update([
                'nombre' => 'Listar histórico COT electrónico ARBA',
                'updated_at' => now(),
            ]);

            return $id;
        }

        return (int) DB::table('permiso')->insertGetId([
            'nombre' => 'Listar histórico COT electrónico ARBA',
            'slug' => self::PERMISO_SLUG,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function asignarPermisoRoles(int $permisoId): void
    {
        foreach ($this->resolverRolIds() as $rolId) {
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }
    }

    /** @return list<int> */
    private function resolverRolIds(): array
    {
        $ids = [];
        foreach (self::ROLES as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function resolverMenuVentasId(): int
    {
        $id = (int) (DB::table('menu')
            ->where('nombre', 'Módulo Ventas')
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($id > 0) {
            return $id;
        }

        return (int) (DB::table('menu')->where('id', 51)->value('id') ?? 0);
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

    public function down(): void
    {
        if (Schema::hasTable('cot_remito_envio') && Schema::hasColumn('cot_remito_envio', 'cot_sesion_envio_id')) {
            Schema::table('cot_remito_envio', function (Blueprint $table) {
                $table->dropIndex(['cot_sesion_envio_id']);
                $table->dropColumn(['cot_sesion_envio_id', 'cliente_nombre']);
            });

            Schema::table('cot_remito_envio', function (Blueprint $table) {
                $table->unique(['tipo', 'letra', 'sucursal', 'numero_remito', 'fecha_remito'], 'cot_remito_envio_uk');
            });
        }

        Schema::dropIfExists('cot_sesion_envio');

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
