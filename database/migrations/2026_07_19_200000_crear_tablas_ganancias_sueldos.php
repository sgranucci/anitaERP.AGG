<?php

use App\Support\Sueldos\Ganancias\GananciasTablas2026Seed;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Nucleo Impuesto a las Ganancias 4ta categoria.
 * - Escalas Art. 94 y deducciones Art. 30 con vigencia mensual (datos AFIP).
 * - Plan de lineas 100% por formula (reemplaza concgan + ifs por descripcion).
 * - Movimientos por empleado/periodo (SIRADIG / carga manual = concgmov).
 * - Resultado snapshot de la planilla anual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ganancia_escala_tramo_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('mes'); // mes de pago 1..12
            $table->decimal('desde', 18, 2)->default(0);
            $table->decimal('hasta', 18, 2)->nullable(); // null = en adelante
            $table->decimal('fijo', 18, 2)->default(0);
            $table->decimal('alicuota', 8, 4)->default(0); // %
            $table->decimal('excedente', 18, 2)->default(0);
            $table->unsignedTinyInteger('nro_tramo')->default(1);
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
            $table->index(['anio', 'mes', 'desde'], 'gan_escala_anio_mes_desde_ix');
        });

        Schema::create('ganancia_deduccion_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('codigo', 30)->unique();
            $table->string('descripcion', 120);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });

        Schema::create('ganancia_deduccion_valor_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('codigo', 30);
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('mes');
            $table->decimal('valor_acumulado', 18, 2)->default(0);
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
            $table->unique(['codigo', 'anio', 'mes'], 'gan_deduc_valor_uq');
        });

        Schema::create('ganancia_linea_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('codigo', 40)->unique();
            $table->string('descripcion', 80);
            $table->unsignedInteger('orden')->default(0);
            $table->string('origen', 30)->default('formula'); // entrada|formula|deduccion_art30
            $table->text('formula')->nullable();
            $table->string('deduccion_codigo', 30)->nullable();
            $table->string('concepto_afip', 10)->nullable();
            $table->unsignedBigInteger('concepto_id')->nullable(); // concepto liquidacion (writeback)
            $table->boolean('activo')->default(true);
            $table->boolean('va_planilla')->default(true);
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
            $table->index(['activo', 'orden'], 'gan_linea_activo_orden_ix');
        });

        Schema::create('ganancia_movimiento_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('empleado_id');
            $table->unsignedInteger('periodo'); // YYYYMM
            $table->string('linea_codigo', 40);
            $table->decimal('valor', 18, 2)->default(0);
            $table->decimal('cantidad', 18, 4)->default(0);
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
            $table->unique(['empleado_id', 'periodo', 'linea_codigo'], 'gan_mov_emp_per_lin_uq');
            $table->index(['empresa_id', 'periodo'], 'gan_mov_emp_periodo_ix');
        });

        Schema::create('ganancia_resultado_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->unsignedBigInteger('empleado_id');
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('mes');
            $table->string('linea_codigo', 40);
            $table->decimal('valor', 18, 2)->default(0);
            $table->decimal('cantidad', 18, 4)->default(0);
            $table->unsignedBigInteger('liquidacion_id')->nullable();
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
            $table->unique(['empleado_id', 'anio', 'mes', 'linea_codigo'], 'gan_res_emp_anio_mes_lin_uq');
            $table->index(['empleado_id', 'anio'], 'gan_res_emp_anio_ix');
        });

        $this->sembrarDeduccionesCatalogo();
        $this->sembrarArt94();
        $this->sembrarArt30();
        $this->sembrarPlanLineas();
        $this->instalarMenu();
    }

    private function sembrarDeduccionesCatalogo(): void
    {
        $ahora = now();
        $cats = [
            ['codigo' => 'GNI', 'descripcion' => 'Ganancias no imponibles (art. 30 a)'],
            ['codigo' => 'TOPE_FAMILIAR', 'descripcion' => 'Tope entradas netas familiar a cargo'],
            ['codigo' => 'CONYUGE', 'descripcion' => 'Cónyuge (art. 30 b)'],
            ['codigo' => 'HIJO', 'descripcion' => 'Hijo (art. 30 b)'],
            ['codigo' => 'HIJO_INCAP', 'descripcion' => 'Hijo incapacitado (art. 30 b)'],
            ['codigo' => 'DE_ESP1', 'descripcion' => 'Deducción especial art. 30 c ap.1'],
            ['codigo' => 'DE_ESP1_NP', 'descripcion' => 'Deducción especial nuevos profesionales'],
            ['codigo' => 'DE_ESP2', 'descripcion' => 'Deducción especial art. 30 c ap.2'],
        ];
        foreach ($cats as $c) {
            DB::table('ganancia_deduccion_sueldos')->insert(array_merge($c, [
                'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora,
            ]));
        }
    }

    private function sembrarArt94(): void
    {
        $ahora = now();
        $filas = [];
        foreach (GananciasTablas2026Seed::tramosArt94() as $mes => $tramos) {
            $n = 0;
            foreach ($tramos as $t) {
                $n++;
                $filas[] = [
                    'anio' => 2026,
                    'mes' => $mes,
                    'desde' => $t[0],
                    'hasta' => $t[1],
                    'fijo' => $t[2],
                    'alicuota' => $t[3],
                    'excedente' => $t[4],
                    'nro_tramo' => $n,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];
            }
        }
        foreach (array_chunk($filas, 100) as $lote) {
            DB::table('ganancia_escala_tramo_sueldos')->insert($lote);
        }
    }

    private function sembrarArt30(): void
    {
        $ahora = now();
        $filas = [];
        foreach (GananciasTablas2026Seed::deduccionesArt30() as $codigo => $meses) {
            foreach ($meses as $mes => $valor) {
                $filas[] = [
                    'codigo' => $codigo,
                    'anio' => 2026,
                    'mes' => $mes,
                    'valor_acumulado' => $valor,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];
            }
        }
        foreach (array_chunk($filas, 100) as $lote) {
            DB::table('ganancia_deduccion_valor_sueldos')->insert($lote);
        }
    }

    private function sembrarPlanLineas(): void
    {
        $ahora = now();
        foreach (GananciasTablas2026Seed::planLineas() as $l) {
            DB::table('ganancia_linea_sueldos')->insert([
                'codigo' => $l['codigo'],
                'descripcion' => $l['descripcion'],
                'orden' => $l['orden'],
                'origen' => $l['origen'],
                'formula' => $l['formula'],
                'deduccion_codigo' => $l['deduccion_codigo'],
                'concepto_afip' => null,
                'concepto_id' => null,
                'activo' => true,
                'va_planilla' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }

    private function instalarMenu(): void
    {
        $moduloId = (int) (DB::table('menu')->where('nombre', 'Módulo Sueldos y Jornales')->where('menu_id', 0)->value('id') ?? 0);
        if ($moduloId === 0) {
            return;
        }
        $submenuId = (int) (DB::table('menu')->where('nombre', 'Liquidación')->where('menu_id', $moduloId)->value('id') ?? 0);
        if ($submenuId === 0) {
            $orden = (int) (DB::table('menu')->where('menu_id', $moduloId)->max('orden') ?? 0) + 1;
            $submenuId = (int) DB::table('menu')->insertGetId([
                'nombre' => 'Liquidación', 'url' => '#', 'menu_id' => $moduloId,
                'orden' => $orden, 'icono' => 'fa-calculator', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $url = 'sueldos/ganancias';
        $orden = (int) (DB::table('menu')->where('menu_id', $submenuId)->max('orden') ?? 0) + 1;
        $menuId = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
        $payload = ['nombre' => 'Consulta Ganancias', 'url' => $url, 'menu_id' => $submenuId, 'orden' => $orden, 'icono' => null, 'updated_at' => now()];
        if ($menuId > 0) {
            DB::table('menu')->where('id', $menuId)->update($payload);
        } else {
            $menuId = (int) DB::table('menu')->insertGetId(array_merge($payload, ['created_at' => now()]));
        }

        $permisos = [
            ['nombre' => 'Listar ganancias sueldos', 'slug' => 'listar-ganancias-sueldos'],
            ['nombre' => 'Calcular ganancias sueldos', 'slug' => 'calcular-ganancias-sueldos'],
        ];
        $rolIds = DB::table('rol')->where('nombre', 'administrador')
            ->orWhere('nombre', 'like', '%apital%umano%')
            ->pluck('id')->map(fn ($id) => (int) $id)->unique()->values()->all();

        foreach ($rolIds as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
            }
        }
        foreach ($permisos as $p) {
            $pid = (int) (DB::table('permiso')->where('slug', $p['slug'])->value('id') ?? 0);
            $pp = ['nombre' => $p['nombre'], 'slug' => $p['slug'], 'menu_id' => $menuId, 'updated_at' => now()];
            if ($pid > 0) {
                DB::table('permiso')->where('id', $pid)->update($pp);
            } else {
                $pid = (int) DB::table('permiso')->insertGetId(array_merge($pp, ['created_at' => now()]));
            }
            foreach ($rolIds as $rolId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $pid)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $pid, 'rol_id' => $rolId]);
                }
            }
        }
        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        Schema::dropIfExists('ganancia_resultado_sueldos');
        Schema::dropIfExists('ganancia_movimiento_sueldos');
        Schema::dropIfExists('ganancia_linea_sueldos');
        Schema::dropIfExists('ganancia_deduccion_valor_sueldos');
        Schema::dropIfExists('ganancia_deduccion_sueldos');
        Schema::dropIfExists('ganancia_escala_tramo_sueldos');
    }
};
