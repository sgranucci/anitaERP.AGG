<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Maestros de tesorería Crown: tipos de valor, valores, depósitos y cotización por jornada.
 * No corre en AGG ni en el resto de las instalaciones.
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private const PERMISOS = [
        'listar-tesoreria-crown-valor' => 'Listar valores de tesorería Crown',
        'crear-tesoreria-crown-valor' => 'Crear valores de tesorería Crown',
        'editar-tesoreria-crown-valor' => 'Editar valores de tesorería Crown',
        'actualizar-tesoreria-crown-valor' => 'Actualizar valores de tesorería Crown',
        'eliminar-tesoreria-crown-valor' => 'Eliminar valores de tesorería Crown',
        'listar-tesoreria-crown-deposito' => 'Listar depósitos de tesorería Crown',
        'crear-tesoreria-crown-deposito' => 'Crear depósitos de tesorería Crown',
        'editar-tesoreria-crown-deposito' => 'Editar depósitos de tesorería Crown',
        'actualizar-tesoreria-crown-deposito' => 'Actualizar depósitos de tesorería Crown',
        'eliminar-tesoreria-crown-deposito' => 'Eliminar depósitos de tesorería Crown',
        'listar-tesoreria-crown-cotizacion' => 'Listar cotizaciones de tesorería Crown',
        'crear-tesoreria-crown-cotizacion' => 'Crear cotizaciones de tesorería Crown',
        'editar-tesoreria-crown-cotizacion' => 'Editar cotizaciones de tesorería Crown',
        'actualizar-tesoreria-crown-cotizacion' => 'Actualizar cotizaciones de tesorería Crown',
        'eliminar-tesoreria-crown-cotizacion' => 'Eliminar cotizaciones de tesorería Crown',
    ];

    /** @var list<string> */
    private const ROLES = [
        'administrador',
        'Enc-sistemas',
        'Enc-tesorería',
        'enc-Tesoreria Operativa',
        'Ger-Tesoreria',
        'Op-tesoreria',
        'op-Tesoreria Operativa',
        'opflash-tesoreria',
        'Sup-tesoreria',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $this->crearTablas();
        $this->sembrarCatalogo();
        $this->crearMenu();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $urls = [
            '#tesoreria-crown',
            'caja/tesoreria-crown/valor',
            'caja/tesoreria-crown/deposito',
            'caja/tesoreria-crown/cotizacion',
        ];
        $menuIds = DB::table('menu')->whereIn('url', $urls)->pluck('id');
        if ($menuIds->isNotEmpty()) {
            DB::table('menu_rol')->whereIn('menu_id', $menuIds)->delete();
            DB::table('menu')->whereIn('id', $menuIds)->delete();
        }

        $permisoIds = DB::table('permiso')->whereIn('slug', array_keys(self::PERMISOS))->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }

        Schema::dropIfExists('tesoreria_crown_cotizacion');
        Schema::dropIfExists('tesoreria_crown_deposito');
        Schema::dropIfExists('tesoreria_crown_valor');
        Schema::dropIfExists('tesoreria_crown_tipo_deposito');
        Schema::dropIfExists('tesoreria_crown_tipo_valor');

        if (class_exists(\App\Support\SuitecrmPermiso::class)) {
            \App\Support\SuitecrmPermiso::flushCachePermisos();
        }
    }

    private function crearTablas(): void
    {
        if (! Schema::hasTable('tesoreria_crown_tipo_valor')) {
            Schema::create('tesoreria_crown_tipo_valor', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 10);
                $table->string('nombre', 80);
                $table->boolean('es_moneda_referencia')->default(false);
                $table->boolean('controla_stock')->default(true);
                $table->boolean('cotizacion_fija')->default(false);
                $table->unsignedSmallInteger('orden')->default(0);
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->unique('codigo', 'tc_tipo_valor_codigo_uq');
            });
        }

        if (! Schema::hasTable('tesoreria_crown_valor')) {
            Schema::create('tesoreria_crown_valor', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tipo_valor_id')->constrained('tesoreria_crown_tipo_valor');
                $table->string('serie', 40);
                $table->string('descripcion', 120);
                $table->decimal('denominacion', 18, 2)->default(0);
                $table->unsignedSmallInteger('orden')->default(0);
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->unique(['tipo_valor_id', 'serie'], 'tc_valor_tipo_serie_uq');
            });
        }

        if (! Schema::hasTable('tesoreria_crown_tipo_deposito')) {
            Schema::create('tesoreria_crown_tipo_deposito', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 20);
                $table->string('nombre', 80);
                $table->unsignedSmallInteger('orden')->default(0);
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->unique('codigo', 'tc_tipo_deposito_codigo_uq');
            });
        }

        if (! Schema::hasTable('tesoreria_crown_deposito')) {
            Schema::create('tesoreria_crown_deposito', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresa');
                $table->foreignId('sala_id')->constrained('sala');
                $table->foreignId('tipo_deposito_id')->constrained('tesoreria_crown_tipo_deposito');
                $table->string('codigo', 20);
                $table->string('nombre', 120);
                $table->unsignedSmallInteger('orden')->default(0);
                $table->foreignId('caja_id')->nullable()->constrained('caja')->nullOnDelete();
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->unique(['sala_id', 'codigo'], 'tc_deposito_sala_codigo_uq');
            });
        }

        if (! Schema::hasTable('tesoreria_crown_cotizacion')) {
            Schema::create('tesoreria_crown_cotizacion', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresa');
                $table->foreignId('sala_id')->constrained('sala');
                $table->date('jornada');
                $table->foreignId('tipo_valor_id')->constrained('tesoreria_crown_tipo_valor');
                $table->decimal('compra', 18, 6);
                $table->decimal('venta', 18, 6);
                $table->timestamps();
                $table->unique(['sala_id', 'jornada', 'tipo_valor_id'], 'tc_cotiz_sala_jornada_tipo_uq');
                $table->index('jornada', 'tc_cotiz_jornada_idx');
            });
        }
    }

    private function sembrarCatalogo(): void
    {
        $ahora = now();

        $tiposValor = [
            ['codigo' => 'FCOL', 'nombre' => 'Fichas de color', 'es_moneda_referencia' => false, 'controla_stock' => false, 'cotizacion_fija' => true, 'orden' => 10],
            ['codigo' => 'FVAL', 'nombre' => 'Fichas de valor', 'es_moneda_referencia' => false, 'controla_stock' => true, 'cotizacion_fija' => true, 'orden' => 20],
            ['codigo' => 'ARS', 'nombre' => 'Pesos', 'es_moneda_referencia' => true, 'controla_stock' => true, 'cotizacion_fija' => true, 'orden' => 30],
            ['codigo' => 'USD', 'nombre' => 'Dólares', 'es_moneda_referencia' => false, 'controla_stock' => true, 'cotizacion_fija' => false, 'orden' => 40],
            ['codigo' => 'EUR', 'nombre' => 'Euros', 'es_moneda_referencia' => false, 'controla_stock' => true, 'cotizacion_fija' => false, 'orden' => 50],
        ];
        foreach ($tiposValor as $fila) {
            $existe = DB::table('tesoreria_crown_tipo_valor')->where('codigo', $fila['codigo'])->exists();
            if ($existe) {
                continue;
            }
            DB::table('tesoreria_crown_tipo_valor')->insert($fila + [
                'activo' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }

        $tiposDeposito = [
            ['codigo' => 'BOVEDA', 'nombre' => 'Bóveda', 'orden' => 10],
            ['codigo' => 'CAJA', 'nombre' => 'Caja', 'orden' => 20],
            ['codigo' => 'MESA', 'nombre' => 'Mesa', 'orden' => 30],
            ['codigo' => 'PREDEPOSITO', 'nombre' => 'Pre-depósito', 'orden' => 40],
            ['codigo' => 'CAJA_CHICA', 'nombre' => 'Caja chica', 'orden' => 50],
        ];
        foreach ($tiposDeposito as $fila) {
            $existe = DB::table('tesoreria_crown_tipo_deposito')->where('codigo', $fila['codigo'])->exists();
            if ($existe) {
                continue;
            }
            DB::table('tesoreria_crown_tipo_deposito')->insert($fila + [
                'activo' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }

    private function crearMenu(): void
    {
        $ahora = now();
        $cajaId = (int) (DB::table('menu')->where('id', 104)->value('id')
            ?: DB::table('menu')->where('nombre', 'Módulo de Caja')->value('id')
            ?: 0);
        if ($cajaId <= 0) {
            throw new RuntimeException('No está el menú Módulo de Caja para colgar Tesorería Crown.');
        }

        $permisoIds = [];
        foreach (self::PERMISOS as $slug => $nombre) {
            $id = DB::table('permiso')->where('slug', $slug)->value('id');
            if (! $id) {
                $id = DB::table('permiso')->insertGetId([
                    'nombre' => $nombre,
                    'slug' => $slug,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }
            $permisoIds[$slug] = (int) $id;
        }

        $padreId = DB::table('menu')->where('url', '#tesoreria-crown')->value('id');
        if (! $padreId) {
            $orden = (int) (DB::table('menu')->where('menu_id', $cajaId)->max('orden') ?? 0) + 1;
            $padreId = DB::table('menu')->insertGetId([
                'menu_id' => $cajaId,
                'nombre' => 'Tesorería',
                'url' => '#tesoreria-crown',
                'orden' => $orden,
                'icono' => 'fa-university',
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }

        $hojas = [
            ['nombre' => 'Valores', 'url' => 'caja/tesoreria-crown/valor', 'icono' => 'fa-money', 'orden' => 1],
            ['nombre' => 'Depósitos', 'url' => 'caja/tesoreria-crown/deposito', 'icono' => 'fa-archive', 'orden' => 2],
            ['nombre' => 'Cotizaciones', 'url' => 'caja/tesoreria-crown/cotizacion', 'icono' => 'fa-exchange', 'orden' => 3],
        ];
        $menuIds = [(int) $padreId, $cajaId];
        foreach ($hojas as $hoja) {
            $id = DB::table('menu')->where('url', $hoja['url'])->value('id');
            if (! $id) {
                $id = DB::table('menu')->insertGetId([
                    'menu_id' => $padreId,
                    'nombre' => $hoja['nombre'],
                    'url' => $hoja['url'],
                    'orden' => $hoja['orden'],
                    'icono' => $hoja['icono'],
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }
            $menuIds[] = (int) $id;
        }

        $rolIds = DB::table('rol')->whereIn('nombre', self::ROLES)->pluck('id');
        foreach ($rolIds as $rolId) {
            foreach ($permisoIds as $permisoId) {
                $existe = DB::table('permiso_rol')
                    ->where('rol_id', $rolId)
                    ->where('permiso_id', $permisoId)
                    ->exists();
                if (! $existe) {
                    DB::table('permiso_rol')->insert([
                        'rol_id' => $rolId,
                        'permiso_id' => $permisoId,
                    ]);
                }
            }
            foreach ($menuIds as $menuId) {
                $existe = DB::table('menu_rol')
                    ->where('rol_id', $rolId)
                    ->where('menu_id', $menuId)
                    ->exists();
                if (! $existe) {
                    DB::table('menu_rol')->insert([
                        'rol_id' => $rolId,
                        'menu_id' => $menuId,
                    ]);
                }
            }
        }

        if (class_exists(\App\Support\SuitecrmPermiso::class)) {
            \App\Support\SuitecrmPermiso::flushCachePermisos();
        }
    }
};
