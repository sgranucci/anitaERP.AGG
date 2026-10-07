<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Trabajos, adjuntos, umbral de aprobación y asignación del módulo al rol Enc-logistica (AGG).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->ampliarSolicitud();
        $this->crearArchivos();
        $this->crearParametro();

        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $this->asignarRolEncLogistica();
        DB::table('logistica_trabajo_tipo')->where('codigo', 'slots')->where('icono', 'fa-arrows')->update([
            'icono' => 'fa-arrows-h',
            'updated_at' => now(),
        ]);
        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitud_logistica_archivo');
        Schema::dropIfExists('logistica_parametro');

        if (Schema::hasTable('solicitud_logistica')) {
            Schema::table('solicitud_logistica', function (Blueprint $table) {
                foreach ([
                    'fk_sollog_trabajo',
                    'fk_sollog_uorigen',
                    'fk_sollog_udestino',
                    'fk_sollog_empresa',
                    'fk_sollog_oc',
                    'fk_sollog_dep',
                    'fk_sollog_depdest',
                ] as $fk) {
                    try {
                        $table->dropForeign($fk);
                    } catch (\Throwable) {
                    }
                }
            });
            Schema::table('solicitud_logistica', function (Blueprint $table) {
                $columnas = [
                    'trabajo_tipo_id', 'ubicacion_origen_id', 'ubicacion_destino_id', 'empresa_id',
                    'ordencompra_id', 'cantidad', 'fecha_tentativa', 'motivo', 'detalle', 'accesorios',
                    'accesorios_detalle', 'tipo_butaca', 'uid_bien', 'direccion_retiro',
                    'modo_cumplimiento', 'deposito_id', 'deposito_destino_id', 'total_estimado',
                    'responsable_snapshot',
                ];
                foreach ($columnas as $columna) {
                    if (Schema::hasColumn('solicitud_logistica', $columna)) {
                        $table->dropColumn($columna);
                    }
                }
            });
        }

        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $rolId = (int) (DB::table('rol')->where('nombre', 'Enc-logistica')->value('id') ?? 0);
        $menuIds = DB::table('menu')->whereIn('url', [
            '#logistica',
            'logistica/solicitud',
            '#config-modulo-logistica',
            'logistica/configuracion',
        ])->pluck('id');
        if ($rolId > 0 && $menuIds->isNotEmpty()) {
            DB::table('menu_rol')->where('rol_id', $rolId)->whereIn('menu_id', $menuIds)->delete();
        }

        $slugs = [
            'listar-logistica-solicitud',
            'crear-logistica-solicitud',
            'listar-configuracion-logistica',
            'editar-configuracion-logistica',
            'actualizar-configuracion-logistica',
        ];
        if ($rolId > 0) {
            $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id');
            if ($permisoIds->isNotEmpty()) {
                DB::table('permiso_rol')->where('rol_id', $rolId)->whereIn('permiso_id', $permisoIds)->delete();
            }
        }

        $gestionarId = (int) (DB::table('permiso')->where('slug', 'gestionar-logistica-solicitud')->value('id') ?? 0);
        if ($gestionarId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $gestionarId)->delete();
            DB::table('permiso')->where('id', $gestionarId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function ampliarSolicitud(): void
    {
        if (! Schema::hasTable('solicitud_logistica')) {
            return;
        }

        Schema::table('solicitud_logistica', function (Blueprint $table) {
            if (! Schema::hasColumn('solicitud_logistica', 'trabajo_tipo_id')) {
                $table->unsignedBigInteger('trabajo_tipo_id')->nullable();
                $table->foreign('trabajo_tipo_id', 'fk_sollog_trabajo')->references('id')->on('logistica_trabajo_tipo');
            }
            if (! Schema::hasColumn('solicitud_logistica', 'ubicacion_origen_id')) {
                $table->unsignedBigInteger('ubicacion_origen_id')->nullable();
                $table->foreign('ubicacion_origen_id', 'fk_sollog_uorigen')->references('id')->on('logistica_ubicacion');
            }
            if (! Schema::hasColumn('solicitud_logistica', 'ubicacion_destino_id')) {
                $table->unsignedBigInteger('ubicacion_destino_id')->nullable();
                $table->foreign('ubicacion_destino_id', 'fk_sollog_udestino')->references('id')->on('logistica_ubicacion');
            }
            if (! Schema::hasColumn('solicitud_logistica', 'empresa_id')) {
                $table->unsignedBigInteger('empresa_id')->nullable();
                $table->foreign('empresa_id', 'fk_sollog_empresa')->references('id')->on('empresa');
            }
            if (! Schema::hasColumn('solicitud_logistica', 'ordencompra_id')) {
                $table->unsignedBigInteger('ordencompra_id')->nullable();
                $table->foreign('ordencompra_id', 'fk_sollog_oc')->references('id')->on('ordencompra');
            }
            if (! Schema::hasColumn('solicitud_logistica', 'cantidad')) {
                $table->decimal('cantidad', 14, 4)->nullable();
            }
            if (! Schema::hasColumn('solicitud_logistica', 'fecha_tentativa')) {
                $table->date('fecha_tentativa')->nullable();
            }
            if (! Schema::hasColumn('solicitud_logistica', 'motivo')) {
                $table->string('motivo', 40)->nullable();
            }
            if (! Schema::hasColumn('solicitud_logistica', 'detalle')) {
                $table->text('detalle')->nullable();
            }
            if (! Schema::hasColumn('solicitud_logistica', 'accesorios')) {
                $table->boolean('accesorios')->default(false);
            }
            if (! Schema::hasColumn('solicitud_logistica', 'accesorios_detalle')) {
                $table->string('accesorios_detalle', 255)->nullable();
            }
            if (! Schema::hasColumn('solicitud_logistica', 'tipo_butaca')) {
                $table->string('tipo_butaca', 30)->nullable();
            }
            if (! Schema::hasColumn('solicitud_logistica', 'uid_bien')) {
                $table->string('uid_bien', 40)->nullable();
            }
            if (! Schema::hasColumn('solicitud_logistica', 'direccion_retiro')) {
                $table->string('direccion_retiro', 180)->nullable();
            }
            if (! Schema::hasColumn('solicitud_logistica', 'modo_cumplimiento')) {
                $table->string('modo_cumplimiento', 30)->nullable();
            }
            if (! Schema::hasColumn('solicitud_logistica', 'deposito_id')) {
                $table->unsignedBigInteger('deposito_id')->nullable();
                $table->foreign('deposito_id', 'fk_sollog_dep')->references('id')->on('depmae');
            }
            if (! Schema::hasColumn('solicitud_logistica', 'deposito_destino_id')) {
                $table->unsignedBigInteger('deposito_destino_id')->nullable();
                $table->foreign('deposito_destino_id', 'fk_sollog_depdest')->references('id')->on('depmae');
            }
            if (! Schema::hasColumn('solicitud_logistica', 'total_estimado')) {
                $table->decimal('total_estimado', 22, 4)->default(0);
            }
            if (! Schema::hasColumn('solicitud_logistica', 'responsable_snapshot')) {
                $table->string('responsable_snapshot', 80)->nullable();
            }
        });
    }

    private function crearArchivos(): void
    {
        if (Schema::hasTable('solicitud_logistica_archivo') || ! Schema::hasTable('solicitud_logistica')) {
            return;
        }

        Schema::create('solicitud_logistica_archivo', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('solicitud_logistica_id');
            $table->string('nombre', 180);
            $table->string('ruta', 255);
            $table->timestamps();
            $table->foreign('solicitud_logistica_id', 'fk_sollogarch_solicitud')
                ->references('id')->on('solicitud_logistica');
        });
    }

    private function crearParametro(): void
    {
        if (! Schema::hasTable('logistica_parametro')) {
            Schema::create('logistica_parametro', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->decimal('monto_aprobacion', 22, 4)->default(0);
                $table->timestamps();
            });
        }

        if (DB::table('logistica_parametro')->count() === 0) {
            DB::table('logistica_parametro')->insert([
                'monto_aprobacion' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function asignarRolEncLogistica(): void
    {
        $rolId = (int) (DB::table('rol')->where('nombre', 'Enc-logistica')->value('id') ?? 0);
        $adminId = (int) (DB::table('rol')->where('nombre', 'administrador')->value('id') ?? 0);
        if ($rolId === 0) {
            return;
        }

        $menuIds = DB::table('menu')->whereIn('url', [
            '#logistica',
            'logistica/solicitud',
            '#config-modulo-logistica',
            'logistica/configuracion',
        ])->pluck('id');
        foreach ($menuIds as $menuId) {
            $existe = DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists();
            if (! $existe) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $menuId,
                    'rol_id' => $rolId,
                ]);
            }
        }

        $slugs = [
            'listar-logistica-solicitud',
            'crear-logistica-solicitud',
            'listar-configuracion-logistica',
            'editar-configuracion-logistica',
            'actualizar-configuracion-logistica',
        ];
        foreach (DB::table('permiso')->whereIn('slug', $slugs)->pluck('id') as $permisoId) {
            $existe = DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists();
            if (! $existe) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }

        $menuSolicitud = (int) (DB::table('menu')->where('url', 'logistica/solicitud')->orderBy('id')->value('id') ?? 0);
        $gestionarId = (int) (DB::table('permiso')->where('slug', 'gestionar-logistica-solicitud')->value('id') ?? 0);
        if ($gestionarId === 0 && $menuSolicitud > 0) {
            $gestionarId = (int) DB::table('permiso')->insertGetId([
                'nombre' => 'Gestionar solicitudes logística',
                'slug' => 'gestionar-logistica-solicitud',
                'menu_id' => $menuSolicitud,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        foreach (array_filter([$rolId, $adminId]) as $asignar) {
            if ($gestionarId === 0) {
                continue;
            }
            $existe = DB::table('permiso_rol')->where('permiso_id', $gestionarId)->where('rol_id', $asignar)->exists();
            if (! $existe) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $gestionarId,
                    'rol_id' => $asignar,
                ]);
            }
        }
    }
};
