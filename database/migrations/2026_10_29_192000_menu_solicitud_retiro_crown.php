<?php

use App\Support\Cache\PermisoCacheSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Crown: carga de solicitudes de retiro (p-cargasolret) bajo Cuentas a pagar.
 *
 * Menú y permiso para administrador, Enc-contaduría (Celina Chiappero)
 * y Enc-cuentasapagar (José Cofre). Ese rol también lo tiene Oreste Poblete,
 * que ve el mismo menú.
 */
return new class extends Migration
{
    private const MENU_URL = 'compras/solicitud-retiro';

    private const MENU_NOMBRE = 'Solicitudes de retiro';

    private const PERMISO = 'generar-solicitud-retiro-crown';

    private const PADRE_NOMBRE = 'Cuentas a pagar';

    /** @var list<string> */
    private const ROLES = [
        'administrador',
        'Enc-contaduría',
        'Enc-cuentasapagar',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        if (! Schema::hasTable('solicitud_retiro_crown_preferencia')) {
            Schema::create('solicitud_retiro_crown_preferencia', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('ultima_secuencia')->default(0);
                $table->string('cbu_origen', 22)->default('');
                $table->timestamps();
            });
        }

        if (! DB::table('solicitud_retiro_crown_preferencia')->exists()) {
            DB::table('solicitud_retiro_crown_preferencia')->insert([
                'ultima_secuencia' => 0,
                'cbu_origen' => '',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $padreId = $this->idModuloCuentasAPagar();
        if ($padreId <= 0) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId <= 0) {
            $orden = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;
            $menuId = (int) DB::table('menu')->insertGetId([
                'nombre' => self::MENU_NOMBRE,
                'url' => self::MENU_URL,
                'menu_id' => $padreId,
                'orden' => $orden,
                'icono' => 'fa-exchange',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'nombre' => self::MENU_NOMBRE,
                'menu_id' => $padreId,
                'icono' => 'fa-exchange',
                'updated_at' => now(),
            ]);
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO)->value('id') ?? 0);
        if ($permisoId <= 0) {
            $permisoId = (int) DB::table('permiso')->insertGetId([
                'nombre' => 'Generar solicitudes de retiro Interbanking y Francés',
                'slug' => self::PERMISO,
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('permiso')->where('id', $permisoId)->update([
                'nombre' => 'Generar solicitudes de retiro Interbanking y Francés',
                'menu_id' => $menuId,
                'updated_at' => now(),
            ]);
        }

        $rolIds = DB::table('rol')->whereIn('nombre', self::ROLES)->pluck('id');
        foreach ($rolIds as $rolId) {
            $rolId = (int) $rolId;
            if ($rolId <= 0) {
                continue;
            }
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
            }
            if (! DB::table('menu_rol')->where('menu_id', $padreId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $padreId, 'rol_id' => $rolId]);
            }
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
            }
            PermisoCacheSupport::forgetRol($rolId);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        Schema::dropIfExists('solicitud_retiro_crown_preferencia');

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO)->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function idModuloCuentasAPagar(): int
    {
        return (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where('url', '#')
            ->where('nombre', self::PADRE_NOMBRE)
            ->orderBy('id')
            ->value('id') ?? 0);
    }
};
