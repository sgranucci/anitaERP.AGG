<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Crown: Facturación juego (paridad p-resultado.c).
 * Flags de procesado en flash_crown + config por sala + menú/permisos.
 */
return new class extends Migration
{
    /** @var array<string, int> sala.codigo → sucursal Anita / PV juego */
    private const SALA_SUCURSAL = [
        'CIP' => 15,
        'LGR' => 16,
        'VIE' => 17,
        'GRO' => 18,
        'EFA' => 9,
        'CHO' => 35,
        'CAT' => 44,
        'VRE' => 201,
        'CSA' => 202,
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        if (Schema::hasTable('flash_crown')) {
            Schema::table('flash_crown', function (Blueprint $table) {
                if (! Schema::hasColumn('flash_crown', 'proc_mesa')) {
                    $table->char('proc_mesa', 1)->default(' ')->after('bs_win');
                }
                if (! Schema::hasColumn('flash_crown', 'proc_slot')) {
                    $table->char('proc_slot', 1)->default(' ')->after('proc_mesa');
                }
                if (! Schema::hasColumn('flash_crown', 'proc_ruleta_el')) {
                    $table->char('proc_ruleta_el', 1)->default(' ')->after('proc_slot');
                }
                if (! Schema::hasColumn('flash_crown', 'venta_mesa_id')) {
                    $table->unsignedBigInteger('venta_mesa_id')->nullable()->after('proc_ruleta_el');
                }
                if (! Schema::hasColumn('flash_crown', 'venta_slot_id')) {
                    $table->unsignedBigInteger('venta_slot_id')->nullable()->after('venta_mesa_id');
                }
                if (! Schema::hasColumn('flash_crown', 'venta_ruleta_el_id')) {
                    $table->unsignedBigInteger('venta_ruleta_el_id')->nullable()->after('venta_slot_id');
                }
                if (! Schema::hasColumn('flash_crown', 'asiento_mesa_id')) {
                    $table->unsignedBigInteger('asiento_mesa_id')->nullable()->after('venta_ruleta_el_id');
                }
                if (! Schema::hasColumn('flash_crown', 'asiento_slot_id')) {
                    $table->unsignedBigInteger('asiento_slot_id')->nullable()->after('asiento_mesa_id');
                }
                if (! Schema::hasColumn('flash_crown', 'asiento_ruleta_el_id')) {
                    $table->unsignedBigInteger('asiento_ruleta_el_id')->nullable()->after('asiento_slot_id');
                }
            });
        }

        if (! Schema::hasTable('facturacion_juego_parametro')) {
            Schema::create('facturacion_juego_parametro', function (Blueprint $table) {
                $table->id();
                $table->string('tipo_gan_mesa', 3)->default('FME');
                $table->string('tipo_per_mesa', 3)->default('CME');
                $table->string('tipo_gan_slot', 3)->default('FSL');
                $table->string('tipo_per_slot', 3)->default('CSL');
                $table->string('tipo_gan_ruleta_el', 3)->default('FRE');
                $table->string('tipo_per_ruleta_el', 3)->default('CRE');
                $table->string('letra', 1)->default('B');
                $table->string('tipoasiento_abreviatura', 10)->default('VTA');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('facturacion_juego_sala')) {
            Schema::create('facturacion_juego_sala', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sala_id')->unique();
                $table->unsignedInteger('sucursal_anita')->default(0);
                $table->unsignedBigInteger('cuentacaja_id')->nullable();
                $table->unsignedBigInteger('cuentacontable_mesa_id')->nullable();
                $table->unsignedBigInteger('cuentacontable_slot_id')->nullable();
                $table->unsignedBigInteger('cuentacontable_ruleta_el_id')->nullable();
                $table->unsignedBigInteger('centrocosto_id')->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();

                $table->foreign('sala_id')->references('id')->on('sala')->cascadeOnDelete();
            });
        }

        $ahora = now();
        if (DB::table('facturacion_juego_parametro')->count() === 0) {
            DB::table('facturacion_juego_parametro')->insert([
                'tipo_gan_mesa' => 'FME',
                'tipo_per_mesa' => 'CME',
                'tipo_gan_slot' => 'FSL',
                'tipo_per_slot' => 'CSL',
                'tipo_gan_ruleta_el' => 'FRE',
                'tipo_per_ruleta_el' => 'CRE',
                'letra' => 'B',
                'tipoasiento_abreviatura' => 'VTA',
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }

        foreach (self::SALA_SUCURSAL as $codigoSala => $sucursal) {
            $salaId = (int) (DB::table('sala')->where('codigo', $codigoSala)->value('id') ?? 0);
            if ($salaId <= 0) {
                continue;
            }
            if (DB::table('facturacion_juego_sala')->where('sala_id', $salaId)->exists()) {
                continue;
            }
            DB::table('facturacion_juego_sala')->insert([
                'sala_id' => $salaId,
                'sucursal_anita' => $sucursal,
                'activo' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }

        $this->asegurarMenuYPermisos($ahora);
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $urls = [
            'ventas/facturacion-juego',
            'ventas/facturacion-juego/configuracion',
        ];
        $menuIds = DB::table('menu')->whereIn('url', $urls)->pluck('id');
        if ($menuIds->isNotEmpty()) {
            DB::table('menu_rol')->whereIn('menu_id', $menuIds)->delete();
            DB::table('menu')->whereIn('id', $menuIds)->delete();
        }

        $slugs = [
            'usar-facturacion-juego',
            'ejecutar-facturacion-juego',
            'configurar-facturacion-juego',
        ];
        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }

        Schema::dropIfExists('facturacion_juego_sala');
        Schema::dropIfExists('facturacion_juego_parametro');

        if (Schema::hasTable('flash_crown')) {
            Schema::table('flash_crown', function (Blueprint $table) {
                foreach ([
                    'asiento_ruleta_el_id', 'asiento_slot_id', 'asiento_mesa_id',
                    'venta_ruleta_el_id', 'venta_slot_id', 'venta_mesa_id',
                    'proc_ruleta_el', 'proc_slot', 'proc_mesa',
                ] as $col) {
                    if (Schema::hasColumn('flash_crown', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function asegurarMenuYPermisos($ahora): void
    {
        $padreProceso = (int) (DB::table('menu')->where('id', 51)->value('id') ?? 0);
        $padreConfig = (int) (DB::table('menu')->where('url', '#config-modulo-ventas')->value('id') ?? 0);

        $menuProcesoId = $this->asegurarMenu(
            $padreProceso,
            'ventas/facturacion-juego',
            'Facturación juego',
            'fa-dice',
            $ahora,
        );
        $menuConfigId = $this->asegurarMenu(
            $padreConfig > 0 ? $padreConfig : $padreProceso,
            'ventas/facturacion-juego/configuracion',
            'Facturación juego',
            'fa-cogs',
            $ahora,
        );

        $permisos = [
            'usar-facturacion-juego' => ['Usar facturación juego', $menuProcesoId],
            'ejecutar-facturacion-juego' => ['Ejecutar facturación juego', $menuProcesoId],
            'configurar-facturacion-juego' => ['Configurar facturación juego', $menuConfigId > 0 ? $menuConfigId : $menuProcesoId],
        ];
        $permisoIds = [];
        foreach ($permisos as $slug => [$nombre, $menuIdPermiso]) {
            $id = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
            if ($id <= 0) {
                $id = (int) DB::table('permiso')->insertGetId([
                    'nombre' => $nombre,
                    'slug' => $slug,
                    'menu_id' => $menuIdPermiso > 0 ? $menuIdPermiso : null,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            } elseif ($menuIdPermiso > 0) {
                DB::table('permiso')->where('id', $id)->whereNull('menu_id')->update([
                    'menu_id' => $menuIdPermiso,
                    'updated_at' => $ahora,
                ]);
            }
            $permisoIds[$slug] = $id;
        }

        $rolIds = DB::table('rol')
            ->whereIn('nombre', ['administrador', 'Enc-sistemas', 'Enc-contaduría', 'Sup-contaduria'])
            ->pluck('id');

        foreach ($rolIds as $rolId) {
            foreach ($permisoIds as $permisoId) {
                if (! DB::table('permiso_rol')->where('rol_id', $rolId)->where('permiso_id', $permisoId)->exists()) {
                    DB::table('permiso_rol')->insert([
                        'rol_id' => $rolId,
                        'permiso_id' => $permisoId,
                    ]);
                }
            }
            foreach (array_filter([$menuProcesoId, $menuConfigId, $padreProceso, $padreConfig]) as $menuId) {
                if (! DB::table('menu_rol')->where('rol_id', $rolId)->where('menu_id', $menuId)->exists()) {
                    DB::table('menu_rol')->insert([
                        'rol_id' => $rolId,
                        'menu_id' => $menuId,
                    ]);
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function asegurarMenu(int $padreId, string $url, string $nombre, string $icono, $ahora): int
    {
        if ($padreId <= 0) {
            return 0;
        }
        $menuId = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
        if ($menuId > 0) {
            return $menuId;
        }
        $orden = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;

        return (int) DB::table('menu')->insertGetId([
            'menu_id' => $padreId,
            'nombre' => $nombre,
            'url' => $url,
            'orden' => $orden,
            'icono' => $icono,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
    }
};
