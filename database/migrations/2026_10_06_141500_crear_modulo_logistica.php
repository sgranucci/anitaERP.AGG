<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de logística (ficha por artículo + configuración) y solicitud de insumos.
 * Menú y datos iniciales solo en AGG (salas Biyemas / Kandiko / Rebisco).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->crearTablas();

        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $this->sembrarMaestros();
        $this->crearMenu();
        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitud_logistica_item');
        Schema::dropIfExists('solicitud_logistica');
        Schema::dropIfExists('logistica_habilitacion');
        Schema::dropIfExists('articulo_centrocosto_pedido');
        Schema::dropIfExists('articulo_catalogo_logistica');
        Schema::dropIfExists('logistica_ubicacion');
        Schema::dropIfExists('logistica_trabajo_tipo');
        Schema::dropIfExists('logistica_tipo_solicitud');
        Schema::dropIfExists('logistica_catalogo_categoria');

        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $slugs = [
            'listar-logistica-solicitud',
            'crear-logistica-solicitud',
            'listar-configuracion-logistica',
            'editar-configuracion-logistica',
            'actualizar-configuracion-logistica',
        ];
        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }

        $urls = ['logistica/solicitud', 'logistica/configuracion', '#logistica', '#config-modulo-logistica'];
        $menuIds = DB::table('menu')->whereIn('url', $urls)->pluck('id');
        if ($menuIds->isNotEmpty()) {
            DB::table('menu_rol')->whereIn('menu_id', $menuIds)->delete();
            DB::table('menu')->whereIn('id', $menuIds)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function crearTablas(): void
    {
        if (! Schema::hasTable('logistica_catalogo_categoria')) {
            Schema::create('logistica_catalogo_categoria', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('codigo', 30);
                $table->string('nombre', 80);
                $table->string('icono', 40)->default('fa-cube');
                $table->unsignedInteger('orden')->default(0);
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->unique('codigo', 'uq_logistica_catalogo_categoria_codigo');
            });
        }

        if (! Schema::hasTable('logistica_tipo_solicitud')) {
            Schema::create('logistica_tipo_solicitud', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('codigo', 30);
                $table->string('nombre', 80);
                $table->string('icono', 40)->default('fa-cube');
                $table->unsignedInteger('orden')->default(0);
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->unique('codigo', 'uq_logistica_tipo_solicitud_codigo');
            });
        }

        if (! Schema::hasTable('logistica_trabajo_tipo')) {
            Schema::create('logistica_trabajo_tipo', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('codigo', 30);
                $table->string('nombre', 80);
                $table->string('icono', 40)->default('fa-wrench');
                $table->string('responsable', 80);
                $table->string('email', 120)->nullable();
                $table->string('prioridad_piso', 20)->default('Media');
                $table->unsignedInteger('orden')->default(0);
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->unique('codigo', 'uq_logistica_trabajo_tipo_codigo');
            });
        }

        if (! Schema::hasTable('logistica_ubicacion')) {
            Schema::create('logistica_ubicacion', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('nombre', 80);
                $table->string('icono', 40)->default('fa-map-marker');
                $table->unsignedInteger('orden')->default(0);
                $table->unsignedBigInteger('empresa_id')->nullable();
                $table->unsignedBigInteger('deposito_id')->nullable();
                $table->unsignedBigInteger('sala_id')->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->foreign('empresa_id', 'fk_logistica_ubicacion_empresa')->references('id')->on('empresa');
                $table->foreign('deposito_id', 'fk_logistica_ubicacion_deposito')->references('id')->on('depmae');
                $table->foreign('sala_id', 'fk_logistica_ubicacion_sala')->references('id')->on('sala');
            });
        }

        if (! Schema::hasTable('articulo_catalogo_logistica')) {
            Schema::create('articulo_catalogo_logistica', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('articulo_id');
                $table->unsignedBigInteger('catalogo_categoria_id')->nullable();
                $table->boolean('publicable')->default(false);
                $table->boolean('favorito')->default(false);
                $table->timestamps();
                $table->unique('articulo_id', 'uq_articulo_catalogo_logistica');
                $table->foreign('articulo_id', 'fk_acl_articulo')->references('id')->on('articulo');
                $table->foreign('catalogo_categoria_id', 'fk_acl_categoria')
                    ->references('id')->on('logistica_catalogo_categoria');
            });
        }

        if (! Schema::hasTable('articulo_centrocosto_pedido')) {
            Schema::create('articulo_centrocosto_pedido', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('articulo_id');
                $table->unsignedBigInteger('centrocosto_id');
                $table->timestamps();
                $table->unique(['articulo_id', 'centrocosto_id'], 'uq_articulo_cc_pedido');
                $table->foreign('articulo_id', 'fk_accp_articulo')->references('id')->on('articulo');
                $table->foreign('centrocosto_id', 'fk_accp_centrocosto')->references('id')->on('centrocosto');
            });
        }

        if (! Schema::hasTable('logistica_habilitacion')) {
            Schema::create('logistica_habilitacion', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('nivel', 20);
                $table->string('alcance', 20);
                $table->unsignedBigInteger('rol_id')->nullable();
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->unsignedBigInteger('tipo_solicitud_id')->nullable();
                $table->unsignedBigInteger('catalogo_categoria_id')->nullable();
                $table->unsignedBigInteger('articulo_id')->nullable();
                $table->boolean('habilitado')->default(false);
                $table->timestamps();
                $table->index(['nivel', 'alcance'], 'ix_logistica_hab_nivel');
                $table->foreign('rol_id', 'fk_loghab_rol')->references('id')->on('rol');
                $table->foreign('usuario_id', 'fk_loghab_usuario')->references('id')->on('usuario');
                $table->foreign('tipo_solicitud_id', 'fk_loghab_tipo')->references('id')->on('logistica_tipo_solicitud');
                $table->foreign('catalogo_categoria_id', 'fk_loghab_categoria')->references('id')->on('logistica_catalogo_categoria');
                $table->foreign('articulo_id', 'fk_loghab_articulo')->references('id')->on('articulo');
            });
        }

        if (! Schema::hasTable('solicitud_logistica')) {
            Schema::create('solicitud_logistica', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('numero');
                $table->date('fecha');
                $table->unsignedBigInteger('usuario_id');
                $table->unsignedBigInteger('centrocosto_id');
                $table->unsignedBigInteger('tipo_solicitud_id');
                $table->string('prioridad', 20)->default('Normal');
                $table->string('estado', 30)->default('enviada');
                $table->string('observacion', 255)->nullable();
                $table->timestamps();
                $table->index(['fecha', 'numero'], 'ix_solicitud_logistica_fecha');
                $table->foreign('usuario_id', 'fk_sollog_usuario')->references('id')->on('usuario');
                $table->foreign('centrocosto_id', 'fk_sollog_cc')->references('id')->on('centrocosto');
                $table->foreign('tipo_solicitud_id', 'fk_sollog_tipo')->references('id')->on('logistica_tipo_solicitud');
            });
        }

        if (! Schema::hasTable('solicitud_logistica_item')) {
            Schema::create('solicitud_logistica_item', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('solicitud_logistica_id');
                $table->unsignedBigInteger('articulo_id');
                $table->decimal('cantidad', 14, 4);
                $table->decimal('precio_estimado', 22, 4)->default(0);
                $table->timestamps();
                $table->foreign('solicitud_logistica_id', 'fk_sollogitem_solicitud')
                    ->references('id')->on('solicitud_logistica');
                $table->foreign('articulo_id', 'fk_sollogitem_articulo')->references('id')->on('articulo');
            });
        }
    }

    private function sembrarMaestros(): void
    {
        if (DB::table('logistica_catalogo_categoria')->count() === 0) {
            $ahora = now();
            $filas = [
                ['LIB', 'Artículos de librería', 'fa-paperclip', 1],
                ['LIM', 'Artículos de limpieza', 'fa-tint', 2],
                ['EPP', 'EPP', 'fa-shield', 3],
                ['MANT', 'Insumos de mantenimiento', 'fa-wrench', 4],
                ['REP', 'Repuestos', 'fa-cogs', 5],
                ['OTR', 'Otro', 'fa-ellipsis-h', 6],
            ];
            foreach ($filas as [$codigo, $nombre, $icono, $orden]) {
                DB::table('logistica_catalogo_categoria')->insert([
                    'codigo' => $codigo,
                    'nombre' => $nombre,
                    'icono' => $icono,
                    'orden' => $orden,
                    'activo' => true,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }
        }

        if (DB::table('logistica_tipo_solicitud')->count() === 0) {
            $ahora = now();
            DB::table('logistica_tipo_solicitud')->insert([
                [
                    'codigo' => 'insumos',
                    'nombre' => 'Solicitar insumos',
                    'icono' => 'fa-cube',
                    'orden' => 1,
                    'activo' => true,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ],
                [
                    'codigo' => 'trabajos',
                    'nombre' => 'Solicitar trabajos',
                    'icono' => 'fa-wrench',
                    'orden' => 2,
                    'activo' => true,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ],
            ]);
        }

        if (DB::table('logistica_trabajo_tipo')->count() === 0) {
            $ahora = now();
            $filas = [
                ['slots', 'Traslado de slots', 'fa-arrows', 'Técnica de slots', 'Alta', 1],
                ['retiro', 'Retiro de materiales', 'fa-truck', 'Logística', 'Media', 2],
                ['elementos', 'Traslado de elementos', 'fa-exchange', 'Logística', 'Media', 3],
                ['butacas', 'Recambio de butacas', 'fa-th-large', 'Mantenimiento general', 'Baja', 4],
            ];
            foreach ($filas as [$codigo, $nombre, $icono, $responsable, $piso, $orden]) {
                DB::table('logistica_trabajo_tipo')->insert([
                    'codigo' => $codigo,
                    'nombre' => $nombre,
                    'icono' => $icono,
                    'responsable' => $responsable,
                    'email' => null,
                    'prioridad_piso' => $piso,
                    'orden' => $orden,
                    'activo' => true,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }
        }

        if (DB::table('logistica_ubicacion')->count() === 0) {
            $ahora = now();
            $nombres = ['Biyemas', 'Kandiko', 'Rebisco', 'Depósito de logística'];
            foreach ($nombres as $i => $nombre) {
                DB::table('logistica_ubicacion')->insert([
                    'nombre' => $nombre,
                    'icono' => $i === 3 ? 'fa-building' : 'fa-map-marker',
                    'orden' => $i + 1,
                    'activo' => true,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }
        }
    }

    private function crearMenu(): void
    {
        $adminIds = $this->rolIds(['administrador']);
        if ($adminIds === []) {
            return;
        }

        $ordenRaiz = (int) (DB::table('menu')->where('menu_id', 0)->max('orden') ?? 0) + 1;
        $raizId = $this->upsertMenu(0, 'Logística', '#logistica', $ordenRaiz, 'fa-truck');
        $solicitudMenuId = $this->upsertMenu($raizId, 'Solicitudes', 'logistica/solicitud', 1, 'fa-list');
        $this->asignarRolesMenu($raizId, $adminIds);
        $this->asignarRolesMenu($solicitudMenuId, $adminIds);

        $this->upsertPermiso('Listar solicitudes logística', 'listar-logistica-solicitud', $solicitudMenuId, $adminIds);
        $this->upsertPermiso('Crear solicitudes logística', 'crear-logistica-solicitud', $solicitudMenuId, $adminIds);

        $grupoId = (int) (DB::table('menu')->where('url', '#configuracion-por-modulo')->orderBy('id')->value('id') ?? 0);
        if ($grupoId === 0) {
            return;
        }
        $moduloId = $this->upsertMenu(
            $grupoId,
            'Logística',
            '#config-modulo-logistica',
            (int) (DB::table('menu')->where('menu_id', $grupoId)->max('orden') ?? 0) + 1,
            'fa-truck'
        );
        $configMenuId = $this->upsertMenu($moduloId, 'Catálogo de logística', 'logistica/configuracion', 1, 'fa-cog');
        $this->asignarRolesMenu($moduloId, $adminIds);
        $this->asignarRolesMenu($configMenuId, $adminIds);
        $this->asignarRolesMenu($grupoId, $adminIds);

        $configRootId = (int) (DB::table('menu')->where('url', 'configuracion/empresa')->value('menu_id') ?? 0);
        if ($configRootId > 0) {
            $this->asignarRolesMenu($configRootId, $adminIds);
        }

        $this->upsertPermiso('Listar config. logística', 'listar-configuracion-logistica', $configMenuId, $adminIds);
        $this->upsertPermiso('Editar config. logística', 'editar-configuracion-logistica', $configMenuId, $adminIds);
        $this->upsertPermiso('Actualizar config. logística', 'actualizar-configuracion-logistica', $configMenuId, $adminIds);

        $atajoId = (int) (DB::table('menu')
            ->where('menu_id', $raizId)
            ->where('url', 'logistica/configuracion')
            ->value('id') ?? 0);
        if ($atajoId === 0) {
            $atajoId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $raizId,
                'nombre' => 'Catálogo de logística',
                'url' => 'logistica/configuracion',
                'orden' => 2,
                'icono' => 'fa-cog',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        DB::table('menu_rol')->where('menu_id', $atajoId)->delete();
        $this->asignarRolesMenu($atajoId, $adminIds);
    }

    /**
     * @param  list<string>  $nombres
     * @return list<int>
     */
    private function rolIds(array $nombres): array
    {
        $ids = [];
        foreach ($nombres as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    private function upsertMenu(int $padre, string $nombre, string $url, int $orden, string $icono): int
    {
        $id = (int) (DB::table('menu')->where('url', $url)->where('menu_id', $padre)->value('id') ?? 0);
        if ($id === 0 && $padre === 0) {
            $id = (int) (DB::table('menu')->where('url', $url)->where('menu_id', 0)->value('id') ?? 0);
        }
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
            'nombre' => $nombre,
            'orden' => $orden,
            'icono' => $icono,
            'updated_at' => now(),
        ]);

        return $id;
    }

    /**
     * @param  list<int>  $rolIds
     */
    private function asignarRolesMenu(int $menuId, array $rolIds): void
    {
        foreach ($rolIds as $rolId) {
            $existe = DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists();
            if (! $existe) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $menuId,
                    'rol_id' => $rolId,
                ]);
            }
        }
    }

    /**
     * @param  list<int>  $rolIds
     */
    private function upsertPermiso(string $nombre, string $slug, int $menuId, array $rolIds): void
    {
        $id = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        $payload = [
            'nombre' => mb_substr($nombre, 0, 50),
            'menu_id' => $menuId,
            'updated_at' => now(),
        ];
        if ($id === 0) {
            $id = (int) DB::table('permiso')->insertGetId(array_merge($payload, [
                'slug' => $slug,
                'created_at' => now(),
            ]));
        } else {
            DB::table('permiso')->where('id', $id)->update($payload);
        }
        foreach ($rolIds as $rolId) {
            $existe = DB::table('permiso_rol')->where('permiso_id', $id)->where('rol_id', $rolId)->exists();
            if (! $existe) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $id,
                    'rol_id' => $rolId,
                ]);
            }
        }
    }
};
