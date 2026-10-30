<?php

use App\Models\Ventas\FacturacionLocalParametro;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Database\EloquentAuditDeleteSupport;
use App\Support\SuitecrmPermiso;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalParametroSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Log de envíos del TXT de ventas de Caballito al shopping, más el ítem de menú (solo Ferli).
 * Sin SoftDeletes: el log se conserva.
 */
return new class extends Migration
{
    private const MENU_URL = 'ventas/facturacion-local/shopping-caballito';

    private const MENU_NOMBRE = 'Shopping Caballito';

    private const GRUPO_URL = '#configuracion-por-modulo';

    private const MODULO_URL = '#config-modulo-ventas';

    private const PADRE_OPERATIVO = '#facturacion-local';

    /** @var list<string> */
    private const ROLES_CONFIG = [
        'administrador',
        'Enc-sistemas',
        'Admin-ventas',
        'Enc-admin',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('facturacion_local_shopping_envio')) {
            Schema::create('facturacion_local_shopping_envio', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('local_venta_id')->nullable();
                $table->date('fecha_venta');
                $table->string('nombre_archivo', 40)->default('');
                $table->string('ruta', 255)->nullable();
                $table->string('destinatarios', 500)->default('');
                $table->string('estado', 20)->default('error');
                $table->unsignedInteger('comprobantes')->default(0);
                $table->unsignedInteger('lineas')->default(0);
                $table->text('mensaje')->nullable();
                $table->timestamp('enviado_at')->nullable();
                $table->timestamps();

                $table->index(['fecha_venta', 'estado'], 'idx_fl_shopping_fecha_estado');
                $table->foreign('local_venta_id', 'fk_fl_shopping_local')
                    ->references('id')->on('local_venta')->onDelete('restrict');
            });
        }

        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $this->sembrarParametros();
        $this->crearMenu();
    }

    public function down(): void
    {
        if (EntornoEmpresaSupport::esFerli()) {
            $menuIds = DB::table('menu')->where('url', self::MENU_URL)->pluck('id');
            if ($menuIds->isNotEmpty()) {
                DB::table('menu_rol')->whereIn('menu_id', $menuIds)->delete();
                DB::table('menu')->whereIn('id', $menuIds)->delete();
            }
            if (Schema::hasTable('facturacion_local_parametro')) {
                EloquentAuditDeleteSupport::each(
                    FacturacionLocalParametro::query()->where('clave', 'like', 'shopping_caballito_%')
                );
            }
            SuitecrmPermiso::flushCachePermisos();
        }

        Schema::dropIfExists('facturacion_local_shopping_envio');
    }

    private function sembrarParametros(): void
    {
        if (! Schema::hasTable('facturacion_local_parametro')) {
            return;
        }

        $codigoLocal = '';
        if (Schema::hasTable('local_venta')) {
            $codigoLocal = (string) (DB::table('local_venta')->where('nombre', 'Caballito')->value('codigo') ?? '');
        }

        $filas = [
            ['clave' => 'shopping_caballito_habilitado', 'valor' => '1', 'etiqueta' => 'Shopping Caballito: enviar automático', 'ayuda' => '1 = todos los días a la hora indicada. 0 = no corre solo.', 'orden' => 100],
            ['clave' => 'shopping_caballito_hora', 'valor' => '21:00', 'etiqueta' => 'Shopping Caballito: hora de envío', 'ayuda' => 'Hora HH:MM. Por ahora 21:00.', 'orden' => 110],
            ['clave' => 'shopping_caballito_email', 'valor' => '', 'etiqueta' => 'Shopping Caballito: correo', 'ayuda' => 'Correos separados por coma.', 'orden' => 120],
            ['clave' => 'shopping_caballito_local_codigo', 'valor' => $codigoLocal, 'etiqueta' => 'Shopping Caballito: código del local', 'ayuda' => 'Código del local en Locales de venta.', 'orden' => 130],
            ['clave' => 'shopping_caballito_nro_local', 'valor' => '', 'etiqueta' => 'Shopping Caballito: nro de local', 'ayuda' => '4 dígitos que asigna el shopping.', 'orden' => 140],
            ['clave' => 'shopping_caballito_nro_contrato', 'valor' => '', 'etiqueta' => 'Shopping Caballito: nro de contrato', 'ayuda' => 'Hasta 10 dígitos.', 'orden' => 150],
            ['clave' => 'shopping_caballito_nro_caja', 'valor' => '01', 'etiqueta' => 'Shopping Caballito: nro de caja', 'ayuda' => '2 dígitos. Una caja: 01.', 'orden' => 160],
        ];

        $ahora = now();
        foreach ($filas as $fila) {
            $existe = DB::table('facturacion_local_parametro')->where('clave', $fila['clave'])->exists();
            if ($existe) {
                continue;
            }
            DB::table('facturacion_local_parametro')->insert(array_merge($fila, [
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]));
        }

        FacturacionLocalParametroSupport::forget();
    }

    private function crearMenu(): void
    {
        $configRootId = $this->resolverMenuConfiguracionId();
        $grupoId = (int) (DB::table('menu')->where('url', self::GRUPO_URL)->value('id') ?? 0);
        $moduloVentasId = (int) (DB::table('menu')->where('url', self::MODULO_URL)->value('id') ?? 0);
        if ($configRootId === 0 || $grupoId === 0 || $moduloVentasId === 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $moduloVentasId)->max('orden') ?? 0) + 1;
        $menuId = $this->upsertMenu(self::MENU_URL, self::MENU_NOMBRE, $moduloVentasId, $orden, 'fa-file-text-o');
        $rolIds = $this->resolverRolIds(self::ROLES_CONFIG);
        $this->asignarRolesMenu($menuId, $rolIds);
        $this->asignarRolesMenu($moduloVentasId, $rolIds);
        $this->asignarRolesMenu($grupoId, $rolIds);
        $this->asignarRolesMenu($configRootId, $rolIds);

        $padreOpId = (int) (DB::table('menu')->where('url', self::PADRE_OPERATIVO)->orderBy('id')->value('id') ?? 0);
        $adminId = (int) (DB::table('rol')->where('nombre', 'administrador')->value('id') ?? 0);
        if ($padreOpId > 0 && $adminId > 0) {
            $atajoId = (int) (DB::table('menu')
                ->where('menu_id', $padreOpId)
                ->where('url', self::MENU_URL)
                ->where('id', '!=', $menuId)
                ->value('id') ?? 0);
            if ($atajoId === 0) {
                $ordenAtajo = (int) (DB::table('menu')->where('menu_id', $padreOpId)->max('orden') ?? 0) + 1;
                $atajoId = (int) DB::table('menu')->insertGetId([
                    'menu_id' => $padreOpId,
                    'nombre' => self::MENU_NOMBRE,
                    'url' => self::MENU_URL,
                    'orden' => $ordenAtajo,
                    'icono' => 'fa-file-text-o',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            DB::table('menu_rol')->where('menu_id', $atajoId)->delete();
            $this->asignarRolesMenu($atajoId, [$adminId]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function resolverMenuConfiguracionId(): int
    {
        $id = (int) (DB::table('menu')->where('menu_id', 0)->where('nombre', 'Configuración')->orderBy('id')->value('id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        return (int) (DB::table('menu')->where('menu_id', 0)->where('nombre', 'Configuracion')->orderBy('id')->value('id') ?? 0);
    }

    private function upsertMenu(string $url, string $nombre, int $padre, int $orden, string $icono): int
    {
        $id = (int) (DB::table('menu')->where('url', $url)->where('menu_id', $padre)->value('id') ?? 0);
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
     * @param  list<string>  $nombres
     * @return list<int>
     */
    private function resolverRolIds(array $nombres): array
    {
        $ids = [];
        foreach ($nombres as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  list<int>  $rolIds
     */
    private function asignarRolesMenu(int $menuId, array $rolIds): void
    {
        if ($menuId <= 0) {
            return;
        }
        foreach ($rolIds as $rolId) {
            if ($rolId <= 0) {
                continue;
            }
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $menuId,
                    'rol_id' => $rolId,
                ]);
            }
        }
    }
};
