<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MENU_VENTAS_URL = 'ventas/tipoempresa-cliente';

    private const MENU_VENTAS_URL_LEGACY = 'ventas/tipoempresa';

    private const MENU_COMPRAS_URL = 'compras/tipoempresa';

    /** @var array<string, string> */
    private const PERMISOS_VENTAS = [
        'crear-tipo-empresa-cliente' => 'Crear tipo de empresa (clientes)',
        'listar-tipo-empresa-cliente' => 'Listar tipos de empresa (clientes)',
        'editar-tipo-empresa-cliente' => 'Editar tipo de empresa (clientes)',
        'actualizar-tipo-empresa-cliente' => 'Actualizar tipo de empresa (clientes)',
        'borrar-tipo-empresa-cliente' => 'Borrar tipo de empresa (clientes)',
    ];

    /** @var array<int, string> */
    private const PERMISOS_COMPRAS_SLUGS = [
        'crear-tipo-de-empresa',
        'listar-tipo-de-empresa',
        'editar-tipo-de-empresa',
        'actualizar-tipo-de-empresa',
        'borrar-tipo-de-empresa',
    ];

    /** @var array<int, string> */
    private const ROLES_IMPUESTOS = [
        'Enc-impuestos',
        'Op-impuestos',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('tipoempresa_cliente')) {
            Schema::create('tipoempresa_cliente', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('nombre', 255);
                $table->string('codigo', 10);
                $table->timestamps();
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_spanish_ci';
            });
        }

        if (Schema::hasTable('tipoempresa')) {
            $existentes = DB::table('tipoempresa')->get(['nombre', 'codigo', 'created_at', 'updated_at']);
            foreach ($existentes as $row) {
                if (! DB::table('tipoempresa_cliente')->where('codigo', $row->codigo)->exists()) {
                    DB::table('tipoempresa_cliente')->insert([
                        'nombre' => $row->nombre,
                        'codigo' => $row->codigo,
                        'created_at' => $row->created_at ?? now(),
                        'updated_at' => $row->updated_at ?? now(),
                    ]);
                }
            }
        }

        if (Schema::hasColumn('cliente', 'tipoempresa_id') && ! Schema::hasColumn('cliente', 'tipoempresa_cliente_id')) {
            Schema::table('cliente', function (Blueprint $table) {
                $table->unsignedBigInteger('tipoempresa_cliente_id')->nullable()->after('condicioniibb_id');
            });

            $clientes = DB::table('cliente')->whereNotNull('tipoempresa_id')->get(['id', 'tipoempresa_id']);
            foreach ($clientes as $cliente) {
                $codigo = DB::table('tipoempresa')->where('id', $cliente->tipoempresa_id)->value('codigo');
                if ($codigo === null) {
                    continue;
                }
                $nuevoId = DB::table('tipoempresa_cliente')->where('codigo', $codigo)->value('id');
                if ($nuevoId) {
                    DB::table('cliente')->where('id', $cliente->id)->update(['tipoempresa_cliente_id' => $nuevoId]);
                }
            }

            Schema::table('cliente', function (Blueprint $table) {
                $table->dropForeign('fk_cliente_tipoempresa');
                $table->dropColumn('tipoempresa_id');
            });
        } elseif (! Schema::hasColumn('cliente', 'tipoempresa_cliente_id')) {
            Schema::table('cliente', function (Blueprint $table) {
                $table->unsignedBigInteger('tipoempresa_cliente_id')->nullable()->after('condicioniibb_id');
            });
        }

        if (Schema::hasColumn('cliente', 'tipoempresa_cliente_id')) {
            $fkExists = collect(DB::select("
                SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cliente' AND CONSTRAINT_NAME = 'fk_cliente_tipoempresa_cliente'
            "))->isNotEmpty();

            if (! $fkExists) {
                Schema::table('cliente', function (Blueprint $table) {
                    $table->foreign('tipoempresa_cliente_id', 'fk_cliente_tipoempresa_cliente')
                        ->references('id')
                        ->on('tipoempresa_cliente')
                        ->onDelete('set null')
                        ->onUpdate('cascade');
                });
            }
        }

        $parentMenuId = (int) (DB::table('menu')
            ->where('nombre', 'Tablas de ventas')
            ->where('url', '#')
            ->value('id') ?? 53);

        $menuVentasId = (int) (DB::table('menu')->where('url', self::MENU_VENTAS_URL_LEGACY)->value('id') ?? 0);
        if ($menuVentasId === 0) {
            $menuVentasId = (int) (DB::table('menu')->where('url', self::MENU_VENTAS_URL)->value('id') ?? 0);
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $parentMenuId)->max('orden') ?? 0) + 1;

        if ($menuVentasId === 0) {
            $menuVentasId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $parentMenuId,
                'nombre' => 'Tipos de empresa (clientes)',
                'url' => self::MENU_VENTAS_URL,
                'orden' => $orden,
                'icono' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuVentasId)->update([
                'menu_id' => $parentMenuId,
                'nombre' => 'Tipos de empresa (clientes)',
                'url' => self::MENU_VENTAS_URL,
                'orden' => $orden,
                'updated_at' => now(),
            ]);
        }

        $menuComprasId = (int) (DB::table('menu')->where('url', self::MENU_COMPRAS_URL)->value('id') ?? 0);

        $permisoVentasIds = [];
        foreach (self::PERMISOS_VENTAS as $slug => $nombre) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
            if ($permisoId === 0) {
                $permisoId = (int) DB::table('permiso')->insertGetId([
                    'nombre' => $nombre,
                    'slug' => $slug,
                    'menu_id' => $menuVentasId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('permiso')->where('id', $permisoId)->update([
                    'nombre' => $nombre,
                    'menu_id' => $menuVentasId,
                    'updated_at' => now(),
                ]);
            }
            $permisoVentasIds[] = $permisoId;
        }

        if ($menuComprasId > 0) {
            DB::table('permiso')
                ->whereIn('slug', self::PERMISOS_COMPRAS_SLUGS)
                ->update([
                    'menu_id' => $menuComprasId,
                    'updated_at' => now(),
                ]);
        }

        $permisosComprasIds = DB::table('permiso')->whereIn('slug', self::PERMISOS_COMPRAS_SLUGS)->pluck('id')->all();
        $rolImpuestosIds = DB::table('rol')->whereIn('nombre', self::ROLES_IMPUESTOS)->pluck('id')->all();

        foreach ($rolImpuestosIds as $rolId) {
            $rid = (int) $rolId;
            foreach ($permisoVentasIds as $permisoId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rid)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rid]);
                }
            }
            if (! DB::table('menu_rol')->where('menu_id', $menuVentasId)->where('rol_id', $rid)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuVentasId, 'rol_id' => $rid]);
            }
            foreach ($permisosComprasIds as $permisoComprasId) {
                DB::table('permiso_rol')->where('permiso_id', $permisoComprasId)->where('rol_id', $rid)->delete();
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (Schema::hasColumn('cliente', 'tipoempresa_cliente_id')) {
            Schema::table('cliente', function (Blueprint $table) {
                $table->dropForeign('fk_cliente_tipoempresa_cliente');
                $table->dropColumn('tipoempresa_cliente_id');
            });
        }

        foreach (array_keys(self::PERMISOS_VENTAS) as $slug) {
            $permisoId = DB::table('permiso')->where('slug', $slug)->value('id');
            if ($permisoId) {
                DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
                DB::table('permiso')->where('id', $permisoId)->delete();
            }
        }

        $menuId = DB::table('menu')->where('url', self::MENU_VENTAS_URL)->value('id');
        if ($menuId) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        Schema::dropIfExists('tipoempresa_cliente');

        SuitecrmPermiso::flushCachePermisos();
    }
};
