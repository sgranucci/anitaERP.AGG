<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Log de ajustes WIGOS en rendición de máquinas + permiso de override.
 * La FK a cabecera se agregará cuando exista rendicion_maquina.
 */
return new class extends Migration
{
    private const PERMISO_AJUSTAR = [
        'nombre' => 'Ajustar datos WIGOS rendición de máquinas',
        'slug' => 'ajustar-wigos-rendicion-maquina',
    ];

    private const PERMISO_VER_LOG = [
        'nombre' => 'Consultar log ajustes WIGOS rendición de máquinas',
        'slug' => 'listar-ajustes-wigos-rendicion-maquina',
    ];

    private const MENU_REF_ROLES_URL = 'caja/usocuentacaja';

    /** @var list<string> */
    private const ROLES_TESORERIA = [
        'administrador',
        'Op-tesoreria',
        'op-Tesoreria Operativa',
        'Enc-tesorería',
        'Enc-tesoreria',
        'enc-Tesoreria Operativa',
        'Ger-Tesoreria',
        'Sup-tesoreria',
        'Sup-Tesoreria',
        'Sup-tesorería',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('rendicion_maquina_ajuste_wigos')) {
            Schema::create('rendicion_maquina_ajuste_wigos', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('rendicion_maquina_id')->nullable();
                $table->unsignedBigInteger('empresa_id');
                $table->date('fecha');
                $table->string('turno', 1);
                $table->unsignedInteger('nro_oper')->nullable();
                $table->string('campo', 80);
                $table->string('etiqueta', 120)->nullable();
                $table->decimal('valor_wigos', 18, 2);
                $table->decimal('valor_ajustado', 18, 2);
                $table->decimal('delta', 18, 2);
                $table->string('motivo', 500)->nullable();
                $table->unsignedBigInteger('usuario_id');
                $table->timestamps();

                $table->foreign('empresa_id', 'fk_rendmaq_ajw_empresa')
                    ->references('id')->on('empresa')->restrictOnDelete();
                $table->foreign('usuario_id', 'fk_rendmaq_ajw_usuario')
                    ->references('id')->on('usuario')->restrictOnDelete();

                $table->index(['empresa_id', 'fecha', 'turno'], 'idx_rendmaq_ajw_emp_fecha_turno');
                $table->index(['rendicion_maquina_id'], 'idx_rendmaq_ajw_rendicion');
                $table->index(['campo'], 'idx_rendmaq_ajw_campo');
            });
        }

        $this->upsertPermisos();
        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        foreach ([self::PERMISO_AJUSTAR['slug'], self::PERMISO_VER_LOG['slug']] as $slug) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
            if ($permisoId > 0) {
                DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
                DB::table('permiso')->where('id', $permisoId)->delete();
            }
        }

        Schema::dropIfExists('rendicion_maquina_ajuste_wigos');
    }

    private function upsertPermisos(): void
    {
        $menuPadreId = (int) (DB::table('menu')
            ->where('nombre', 'Rendición de máquinas')
            ->where('url', '#')
            ->value('id') ?? 0);

        $menuAperturaId = (int) (DB::table('menu')
            ->where('url', 'caja/apertura-gasto')
            ->value('id') ?? 0);

        $refMenuId = (int) (DB::table('menu')->where('url', self::MENU_REF_ROLES_URL)->value('id') ?? 0);
        $menuId = $menuAperturaId > 0 ? $menuAperturaId : $menuPadreId;

        foreach ([self::PERMISO_AJUSTAR, self::PERMISO_VER_LOG] as $meta) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $meta['slug'])->value('id') ?? 0);
            if ($permisoId === 0) {
                $permisoId = (int) DB::table('permiso')->insertGetId([
                    'nombre' => $meta['nombre'],
                    'slug' => $meta['slug'],
                    'menu_id' => $menuId > 0 ? $menuId : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('permiso')->where('id', $permisoId)->update([
                    'nombre' => $meta['nombre'],
                    'menu_id' => $menuId > 0 ? $menuId : null,
                    'updated_at' => now(),
                ]);
            }

            $this->asignarRoles($permisoId, $refMenuId, $menuPadreId);
        }
    }

    private function asignarRoles(int $permisoId, int $refMenuId, int $menuPadreId): void
    {
        $rolIds = [];
        if ($refMenuId > 0) {
            $rolIds = DB::table('permiso as p')
                ->join('permiso_rol as pr', 'pr.permiso_id', '=', 'p.id')
                ->where('p.menu_id', $refMenuId)
                ->distinct()
                ->pluck('pr.rol_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $rolesTesoreria = DB::table('rol')
            ->whereIn('nombre', self::ROLES_TESORERIA)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $rolIds = array_values(array_unique(array_merge($rolIds, $rolesTesoreria)));
        foreach ($rolIds as $rolId) {
            $exists = DB::table('permiso_rol')
                ->where('permiso_id', $permisoId)
                ->where('rol_id', $rolId)
                ->exists();
            if (! $exists) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }

        if ($menuPadreId > 0) {
            // noop: menú padre ya tiene roles desde migración apertura gasto
        }
    }
};
