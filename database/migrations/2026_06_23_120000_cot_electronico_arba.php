<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MENU_URL = 'ventas/cot-electronico';

    private const PERMISO_SLUG = 'procesar-cot-electronico';

    /** @var list<string> */
    private const ROLES = ['administrador', 'Enc-contaduría', 'Enc-impuestos'];

    public function up(): void
    {
        if (! Schema::hasColumn('transporte', 'cuit_chofer')) {
            Schema::table('transporte', function (Blueprint $table) {
                $table->string('cuit_chofer', 20)->nullable()->after('patenteacoplado');
            });
        }

        if (! Schema::hasTable('cot_remito_envio')) {
            Schema::create('cot_remito_envio', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('tipo', 3)->default('REM');
                $table->string('letra', 1)->default('R');
                $table->unsignedInteger('sucursal')->default(1);
                $table->unsignedBigInteger('numero_remito');
                $table->date('fecha_remito');
                $table->unsignedBigInteger('venta_id')->nullable();
                $table->unsignedBigInteger('transporte_id')->nullable();
                $table->unsignedBigInteger('cliente_id')->nullable();
                $table->string('procesado', 2)->nullable();
                $table->string('nro_unico', 40)->nullable();
                $table->string('cot', 30)->nullable();
                $table->string('numero_comprobante_arba', 20)->nullable();
                $table->string('nombre_archivo', 80)->nullable();
                $table->text('error')->nullable();
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->timestamps();

                $table->unique(['tipo', 'letra', 'sucursal', 'numero_remito', 'fecha_remito'], 'cot_remito_envio_uk');
                $table->index('fecha_remito');
            });
        }

        $permisoId = $this->upsertPermiso();
        $this->asignarPermisoRoles($permisoId);

        $padreId = $this->resolverMenuVentasId();
        if ($padreId === 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;
        $menuId = $this->upsertMenu(self::MENU_URL, 'COT electrónico ARBA', $padreId, $orden, 'fa-truck');

        foreach ($this->resolverRolIds() as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function upsertPermiso(): int
    {
        $id = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($id > 0) {
            DB::table('permiso')->where('id', $id)->update([
                'nombre' => 'Procesar COT electrónico ARBA',
                'updated_at' => now(),
            ]);

            return $id;
        }

        return (int) DB::table('permiso')->insertGetId([
            'nombre' => 'Procesar COT electrónico ARBA',
            'slug' => self::PERMISO_SLUG,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function asignarPermisoRoles(int $permisoId): void
    {
        foreach ($this->resolverRolIds() as $rolId) {
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }
    }

    /** @return list<int> */
    private function resolverRolIds(): array
    {
        $ids = [];
        foreach (self::ROLES as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function resolverMenuVentasId(): int
    {
        $id = (int) (DB::table('menu')
            ->where('nombre', 'Módulo Ventas')
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($id > 0) {
            return $id;
        }

        return (int) (DB::table('menu')->where('id', 51)->value('id') ?? 0);
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

    public function down(): void
    {
        Schema::dropIfExists('cot_remito_envio');

        if (Schema::hasColumn('transporte', 'cuit_chofer')) {
            Schema::table('transporte', function (Blueprint $table) {
                $table->dropColumn('cuit_chofer');
            });
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
