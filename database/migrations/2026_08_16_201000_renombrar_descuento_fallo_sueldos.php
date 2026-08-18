<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->prepararIndicesNombresCompletos();
        $this->quitarRestriccionesNombresAnteriores();

        Schema::rename('cierrefallo_sueldos', 'cierre_descuento_fallo_sueldos');
        Schema::rename('dtofallo_sueldos', 'descuento_fallo_sueldos');

        Schema::table('cierre_descuento_fallo_sueldos', function (Blueprint $table) {
            $table->renameColumn('nro_cierre', 'numero_cierre');
        });
        Schema::table('descuento_fallo_sueldos', function (Blueprint $table) {
            $table->renameColumn('cierrefallo_id', 'cierre_descuento_fallo_id');
            $table->renameColumn('tipo_oper', 'tipo_operacion');
        });
        Schema::table('novedad_sueldos', function (Blueprint $table) {
            $table->renameColumn('dtofallo_id', 'descuento_fallo_id');
        });

        $this->crearRestriccionesNombresCompletos();
        $this->actualizarNombresAplicacion(
            'dtofallo',
            'descuento_fallo',
            'sueldos/dtofallo',
            'sueldos/descuento-fallo',
            [
                'listar-dtofallo-sueldos' => 'listar-descuento-fallo-sueldos',
                'crear-dtofallo-sueldos' => 'crear-descuento-fallo-sueldos',
                'borrar-dtofallo-sueldos' => 'anular-descuento-fallo-sueldos',
            ]
        );
    }

    public function down(): void
    {
        $this->prepararIndicesNombresAnteriores();
        $this->quitarRestriccionesNombresCompletos();

        Schema::table('novedad_sueldos', function (Blueprint $table) {
            $table->renameColumn('descuento_fallo_id', 'dtofallo_id');
        });
        Schema::table('descuento_fallo_sueldos', function (Blueprint $table) {
            $table->renameColumn('cierre_descuento_fallo_id', 'cierrefallo_id');
            $table->renameColumn('tipo_operacion', 'tipo_oper');
        });
        Schema::table('cierre_descuento_fallo_sueldos', function (Blueprint $table) {
            $table->renameColumn('numero_cierre', 'nro_cierre');
        });

        Schema::rename('descuento_fallo_sueldos', 'dtofallo_sueldos');
        Schema::rename('cierre_descuento_fallo_sueldos', 'cierrefallo_sueldos');

        $this->crearRestriccionesNombresAnteriores();
        $this->actualizarNombresAplicacion(
            'descuento_fallo',
            'dtofallo',
            'sueldos/descuento-fallo',
            'sueldos/dtofallo',
            [
                'listar-descuento-fallo-sueldos' => 'listar-dtofallo-sueldos',
                'crear-descuento-fallo-sueldos' => 'crear-dtofallo-sueldos',
                'anular-descuento-fallo-sueldos' => 'borrar-dtofallo-sueldos',
            ]
        );
    }

    private function prepararIndicesNombresCompletos(): void
    {
        $this->crearIndiceSiFalta(
            'cierrefallo_sueldos',
            'cierre_descuento_fallo_numero_uq',
            fn (Blueprint $table) => $table->unique('nro_cierre', 'cierre_descuento_fallo_numero_uq')
        );
        $this->crearIndiceSiFalta(
            'cierrefallo_sueldos',
            'cierre_descuento_fallo_empresa_periodo_idx',
            fn (Blueprint $table) => $table->index(
                ['empresa_id', 'periodo_descuento'],
                'cierre_descuento_fallo_empresa_periodo_idx'
            )
        );
        $this->crearIndiceSiFalta(
            'dtofallo_sueldos',
            'descuento_fallo_empleado_fecha_idx',
            fn (Blueprint $table) => $table->index(
                ['empleado_sueldos_id', 'fecha'],
                'descuento_fallo_empleado_fecha_idx'
            )
        );
        $this->crearIndiceSiFalta(
            'dtofallo_sueldos',
            'descuento_fallo_empresa_periodo_tipo_idx',
            fn (Blueprint $table) => $table->index(
                ['empresa_id', 'periodo', 'tipo_oper'],
                'descuento_fallo_empresa_periodo_tipo_idx'
            )
        );
        $this->crearIndiceSiFalta(
            'dtofallo_sueldos',
            'descuento_fallo_cierre_idx',
            fn (Blueprint $table) => $table->index('cierrefallo_id', 'descuento_fallo_cierre_idx')
        );
    }

    private function quitarRestriccionesNombresAnteriores(): void
    {
        $this->eliminarClaveForaneaSiExiste('novedad_sueldos', 'dtofallo_id');
        $this->eliminarIndiceSiExiste('novedad_sueldos', 'novedad_dtofallo_uq');
        $this->eliminarClaveForaneaSiExiste('dtofallo_sueldos', 'cierrefallo_id');
        $this->eliminarIndiceSiExiste('dtofallo_sueldos', 'dtofallo_emp_fecha_idx');
        $this->eliminarIndiceSiExiste('dtofallo_sueldos', 'dtofallo_emp_per_tipo_idx');
        $this->eliminarIndiceSiExiste('dtofallo_sueldos', 'dtofallo_cierre_idx');
        $this->eliminarIndiceSiExiste('cierrefallo_sueldos', 'cierrefallo_sueldos_nro_cierre_unique');
        $this->eliminarIndiceSiExiste('cierrefallo_sueldos', 'cierrefallo_emp_per_idx');
    }

    private function crearRestriccionesNombresCompletos(): void
    {
        if (! $this->tieneClaveForanea('descuento_fallo_sueldos', 'cierre_descuento_fallo_id')) {
            Schema::table('descuento_fallo_sueldos', function (Blueprint $table) {
                $table->foreign('cierre_descuento_fallo_id')
                    ->references('id')
                    ->on('cierre_descuento_fallo_sueldos')
                    ->nullOnDelete();
            });
        }

        $this->crearIndiceSiFalta(
            'novedad_sueldos',
            'novedad_descuento_fallo_uq',
            fn (Blueprint $table) => $table->unique(
                'descuento_fallo_id',
                'novedad_descuento_fallo_uq'
            )
        );
        if (! $this->tieneClaveForanea('novedad_sueldos', 'descuento_fallo_id')) {
            Schema::table('novedad_sueldos', function (Blueprint $table) {
                $table->foreign('descuento_fallo_id')
                    ->references('id')
                    ->on('descuento_fallo_sueldos')
                    ->nullOnDelete();
            });
        }
    }

    private function prepararIndicesNombresAnteriores(): void
    {
        $this->crearIndiceSiFalta(
            'cierre_descuento_fallo_sueldos',
            'cierrefallo_sueldos_nro_cierre_unique',
            fn (Blueprint $table) => $table->unique(
                'numero_cierre',
                'cierrefallo_sueldos_nro_cierre_unique'
            )
        );
        $this->crearIndiceSiFalta(
            'cierre_descuento_fallo_sueldos',
            'cierrefallo_emp_per_idx',
            fn (Blueprint $table) => $table->index(
                ['empresa_id', 'periodo_descuento'],
                'cierrefallo_emp_per_idx'
            )
        );
        $this->crearIndiceSiFalta(
            'descuento_fallo_sueldos',
            'dtofallo_emp_fecha_idx',
            fn (Blueprint $table) => $table->index(
                ['empleado_sueldos_id', 'fecha'],
                'dtofallo_emp_fecha_idx'
            )
        );
        $this->crearIndiceSiFalta(
            'descuento_fallo_sueldos',
            'dtofallo_emp_per_tipo_idx',
            fn (Blueprint $table) => $table->index(
                ['empresa_id', 'periodo', 'tipo_operacion'],
                'dtofallo_emp_per_tipo_idx'
            )
        );
        $this->crearIndiceSiFalta(
            'descuento_fallo_sueldos',
            'dtofallo_cierre_idx',
            fn (Blueprint $table) => $table->index(
                'cierre_descuento_fallo_id',
                'dtofallo_cierre_idx'
            )
        );
    }

    private function quitarRestriccionesNombresCompletos(): void
    {
        $this->eliminarClaveForaneaSiExiste('novedad_sueldos', 'descuento_fallo_id');
        $this->eliminarIndiceSiExiste('novedad_sueldos', 'novedad_descuento_fallo_uq');
        $this->eliminarClaveForaneaSiExiste(
            'descuento_fallo_sueldos',
            'cierre_descuento_fallo_id'
        );
        $this->eliminarIndiceSiExiste(
            'descuento_fallo_sueldos',
            'descuento_fallo_empleado_fecha_idx'
        );
        $this->eliminarIndiceSiExiste(
            'descuento_fallo_sueldos',
            'descuento_fallo_empresa_periodo_tipo_idx'
        );
        $this->eliminarIndiceSiExiste(
            'descuento_fallo_sueldos',
            'descuento_fallo_cierre_idx'
        );
        $this->eliminarIndiceSiExiste(
            'cierre_descuento_fallo_sueldos',
            'cierre_descuento_fallo_numero_uq'
        );
        $this->eliminarIndiceSiExiste(
            'cierre_descuento_fallo_sueldos',
            'cierre_descuento_fallo_empresa_periodo_idx'
        );
    }

    private function crearRestriccionesNombresAnteriores(): void
    {
        if (! $this->tieneClaveForanea('dtofallo_sueldos', 'cierrefallo_id')) {
            Schema::table('dtofallo_sueldos', function (Blueprint $table) {
                $table->foreign('cierrefallo_id')
                    ->references('id')
                    ->on('cierrefallo_sueldos')
                    ->nullOnDelete();
            });
        }

        $this->crearIndiceSiFalta(
            'novedad_sueldos',
            'novedad_dtofallo_uq',
            fn (Blueprint $table) => $table->unique('dtofallo_id', 'novedad_dtofallo_uq')
        );
        if (! $this->tieneClaveForanea('novedad_sueldos', 'dtofallo_id')) {
            Schema::table('novedad_sueldos', function (Blueprint $table) {
                $table->foreign('dtofallo_id')
                    ->references('id')
                    ->on('dtofallo_sueldos')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * @param  array<string, string>  $permisos
     */
    private function actualizarNombresAplicacion(
        string $origenAnterior,
        string $origenNuevo,
        string $urlAnterior,
        string $urlNueva,
        array $permisos
    ): void {
        DB::table('novedad_sueldos')
            ->where('origen', $origenAnterior)
            ->update(['origen' => $origenNuevo]);

        DB::table('menu')
            ->where('url', $urlAnterior)
            ->update(['url' => $urlNueva]);

        foreach ($permisos as $slugAnterior => $slugNuevo) {
            DB::table('permiso')
                ->where('slug', $slugAnterior)
                ->update(['slug' => $slugNuevo]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function crearIndiceSiFalta(
        string $tabla,
        string $indice,
        callable $crear
    ): void {
        if (Schema::hasIndex($tabla, $indice)) {
            return;
        }

        Schema::table($tabla, function (Blueprint $table) use ($crear) {
            $crear($table);
        });
    }

    private function eliminarIndiceSiExiste(string $tabla, string $indice): void
    {
        \App\Support\Database\MigrationDialectSupport::dropIndiceOUnique($tabla, $indice);
    }

    private function eliminarClaveForaneaSiExiste(string $tabla, string $columna): void
    {
        if (! $this->tieneClaveForanea($tabla, $columna)) {
            return;
        }

        Schema::table($tabla, function (Blueprint $table) use ($columna) {
            $table->dropForeign([$columna]);
        });
    }

    private function tieneClaveForanea(string $tabla, string $columna): bool
    {
        foreach (Schema::getForeignKeys($tabla) as $claveForanea) {
            if (($claveForanea['columns'] ?? []) === [$columna]) {
                return true;
            }
        }

        return false;
    }
};
