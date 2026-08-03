<?php

use App\Support\Caja\Remesa\RemesaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MENU_URL = 'caja/remesa';

    private const MENU_REF_ROLES_URL = 'caja/usocuentacaja';

    /** @var list<array{nombre: string, slug: string}> */
    private const PERMISOS = [
        ['nombre' => 'Listar remesas', 'slug' => 'listar-remesa'],
        ['nombre' => 'Ingresar remesas', 'slug' => 'crear-remesa'],
        ['nombre' => 'Editar remesas', 'slug' => 'editar-remesa'],
        ['nombre' => 'Actualizar remesas', 'slug' => 'actualizar-remesa'],
        ['nombre' => 'Anular remesas', 'slug' => 'anular-remesa'],
        ['nombre' => 'Configurar cuentas remesas', 'slug' => 'configurar-remesa'],
    ];

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

    private const ROLES_CONFIG = [
        'administrador',
        'Enc-tesorería',
        'Enc-tesoreria',
        'enc-Tesoreria Operativa',
    ];

    public function up(): void
    {
        $this->crearTablas();
        $this->seedUsosYCuentas();
        $this->seedMenuPermisos();
        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        foreach (array_column(self::PERMISOS, 'slug') as $slug) {
            $pid = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
            if ($pid > 0) {
                DB::table('permiso_rol')->where('permiso_id', $pid)->delete();
                DB::table('permiso')->where('id', $pid)->delete();
            }
        }
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        Schema::dropIfExists('remesa_linea');
        Schema::dropIfExists('remesa');
    }

    private function crearTablas(): void
    {
        if (! Schema::hasTable('remesa')) {
            Schema::create('remesa', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('empresa_id');
                $table->unsignedInteger('numero')->nullable();
                $table->date('fecha');
                $table->char('tipo', 1);
                $table->string('estado', 20)->default(RemesaSupport::ESTADO_CONFIRMADA);
                $table->string('remito', 45)->nullable();
                $table->string('bolsa', 45)->nullable();
                $table->string('precinto', 45)->nullable();
                $table->decimal('importe_destino', 18, 2)->default(0);
                $table->decimal('importe_origen', 18, 2)->default(0);
                $table->unsignedBigInteger('asiento_id')->nullable();
                $table->unsignedInteger('usuario_id')->nullable();
                $table->text('observacion')->nullable();
                $table->unsignedInteger('nro_oper_anita')->nullable();
                $table->timestamps();

                $table->unique(['empresa_id', 'numero'], 'uq_remesa_empresa_numero');
                $table->index(['empresa_id', 'fecha'], 'idx_remesa_empresa_fecha');
                $table->index(['tipo', 'estado'], 'idx_remesa_tipo_estado');
            });
        }

        if (! Schema::hasTable('remesa_linea')) {
            Schema::create('remesa_linea', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('remesa_id');
                $table->string('lado', 10);
                $table->unsignedInteger('cuentacaja_id');
                $table->decimal('monto', 18, 2)->default(0);
                $table->unsignedSmallInteger('orden')->default(0);
                $table->timestamps();

                $table->foreign('remesa_id')->references('id')->on('remesa')->onDelete('cascade');
                $table->index(['remesa_id', 'lado'], 'idx_remesa_linea_lado');
            });
        }
    }

    private function seedUsosYCuentas(): void
    {
        $usoDestinoId = $this->upsertUso(RemesaSupport::USO_DESTINO);
        $usoOrigenId = $this->upsertUso(RemesaSupport::USO_ORIGEN);

        $this->asignarUsoACodigos($usoDestinoId, RemesaSupport::CODIGOS_DESTINO);
        $this->asignarUsoACodigos($usoOrigenId, RemesaSupport::CODIGOS_ORIGEN);
    }

    private function upsertUso(string $nombre): int
    {
        $id = (int) (DB::table('usocuentacaja')->where('nombre', $nombre)->value('id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        return (int) DB::table('usocuentacaja')->insertGetId([
            'nombre' => $nombre,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  list<string>  $codigos
     */
    private function asignarUsoACodigos(int $usoId, array $codigos): void
    {
        if ($usoId <= 0 || $codigos === []) {
            return;
        }

        $codigosErp = RemesaSupport::codigosErpDesdeAnita($codigos);
        if ($codigosErp === []) {
            return;
        }

        $ids = DB::table('cuentacaja')
            ->whereIn('codigo', $codigosErp)
            ->pluck('id')
            ->all();

        foreach ($ids as $cuentacajaId) {
            $exists = DB::table('cuentacaja_usocuentacaja')
                ->where('cuentacaja_id', $cuentacajaId)
                ->where('usocuentacaja_id', $usoId)
                ->exists();
            if (! $exists) {
                DB::table('cuentacaja_usocuentacaja')->insert([
                    'cuentacaja_id' => $cuentacajaId,
                    'usocuentacaja_id' => $usoId,
                ]);
            }
        }
    }

    private function seedMenuPermisos(): void
    {
        $cajaId = $this->resolverMenuCajaId();
        $orden = (int) (DB::table('menu')->where('menu_id', $cajaId)->max('orden') ?? 0) + 1;
        $menuId = $this->upsertMenu(self::MENU_URL, 'Remesas', $cajaId, $orden, 'fa-university');

        $refMenuId = (int) (DB::table('menu')->where('url', self::MENU_REF_ROLES_URL)->value('id') ?? 0);
        $this->upsertPermisos($menuId, $refMenuId);
        $this->asignarRoles($menuId);
    }

    private function resolverMenuCajaId(): int
    {
        $id = (int) (DB::table('menu')
            ->where('nombre', 'Módulo de Caja')
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($id > 0) {
            return $id;
        }

        return (int) (DB::table('menu')
            ->where('nombre', 'like', '%Caja%')
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 104);
    }

    private function upsertMenu(string $url, string $nombre, int $padre, int $orden, string $icono): int
    {
        $id = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
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
            'menu_id' => $padre,
            'nombre' => $nombre,
            'orden' => $orden,
            'icono' => $icono,
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function upsertPermisos(int $menuId, int $refMenuId): void
    {
        $rolIdsRef = $refMenuId > 0
            ? DB::table('menu_rol')->where('menu_id', $refMenuId)->pluck('rol_id')->unique()->all()
            : [];

        foreach (self::PERMISOS as $row) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $row['slug'])->value('id') ?? 0);
            if ($permisoId === 0) {
                $permisoId = (int) DB::table('permiso')->insertGetId([
                    'nombre' => $row['nombre'],
                    'slug' => $row['slug'],
                    'menu_id' => $menuId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('permiso')->where('id', $permisoId)->update([
                    'menu_id' => $menuId,
                    'nombre' => $row['nombre'],
                    'updated_at' => now(),
                ]);
            }

            foreach ($rolIdsRef as $rolId) {
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
        }
    }

    private function asignarRoles(int $menuId): void
    {
        $rolIds = DB::table('rol')
            ->whereIn('nombre', self::ROLES_TESORERIA)
            ->pluck('id')
            ->all();

        foreach ($rolIds as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
            }
        }

        // Configurar: solo admin + encargado tesorería
        $configPid = (int) (DB::table('permiso')->where('slug', 'configurar-remesa')->value('id') ?? 0);
        if ($configPid > 0) {
            $configRolIds = DB::table('rol')->whereIn('nombre', self::ROLES_CONFIG)->pluck('id')->all();
            DB::table('permiso_rol')->where('permiso_id', $configPid)->delete();
            foreach ($configRolIds as $rolId) {
                DB::table('permiso_rol')->insert(['permiso_id' => $configPid, 'rol_id' => $rolId]);
            }
        }

        // Asegurar permisos CRUD en roles tesorería
        $crudSlugs = array_column(array_filter(self::PERMISOS, fn ($p) => $p['slug'] !== 'configurar-remesa'), 'slug');
        $crudIds = DB::table('permiso')->whereIn('slug', $crudSlugs)->pluck('id')->all();
        foreach ($rolIds as $rolId) {
            foreach ($crudIds as $pid) {
                if (! DB::table('permiso_rol')->where('permiso_id', $pid)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $pid, 'rol_id' => $rolId]);
                }
            }
        }
    }
};
