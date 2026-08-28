<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MENU_URL = 'configuracion/general';

    /** @var list<string> */
    private const ROLES = [
        'administrador',
        'Enc-admin',
        'Enc-contaduría',
        'Enc-impuestos',
        'Ger-administracion',
    ];

    /** @var list<array{nombre: string, slug: string}> */
    private const PERMISOS = [
        ['nombre' => 'Editar configuración general del sistema', 'slug' => 'editar-configuracion-general'],
        ['nombre' => 'Actualizar configuración general del sistema', 'slug' => 'actualizar-configuracion-general'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('parametro_sistema')) {
            Schema::create('parametro_sistema', function (Blueprint $table) {
                $table->id();
                $table->string('clave', 80);
                $table->string('grupo', 80);
                $table->string('etiqueta', 160);
                $table->string('ayuda', 500)->nullable();
                $table->string('tipo', 20);
                $table->string('valor', 255);
                $table->unsignedSmallInteger('orden')->default(0);
                $table->timestamps();
                $table->unique('clave');
            });
        }

        $this->asegurarParametro(
            'limite_fce',
            'Facturación ARCA',
            'Tope FCE MiPyME',
            'Si el cliente es receptor de Factura de Crédito (FCE) y el total del comprobante alcanza este importe, se emite FCE (códigos AFIP 201 / 206).',
            'decimal',
            (string) (float) config('facturacion.LIMITE_FCE', 0),
            10
        );
        $this->asegurarParametro(
            'tope_consumidor_final',
            'Facturación ARCA',
            'Tope consumidor final',
            'Umbral RG 5700/2025: a partir de este monto hay que identificar al comprador (DNI/CUIT) en Factura B, POS y Libro IVA Digital.',
            'decimal',
            (string) (float) config('arca_wsfe.receptor.consumidor_final_umbral_monto', 10_000_000),
            20
        );

        $padreId = $this->resolverMenuPadreId();
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $orden = $menuId > 0
            ? (int) (DB::table('menu')->where('id', $menuId)->value('orden') ?? 1)
            : (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;

        if ($menuId === 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $padreId,
                'nombre' => 'Configuración general',
                'url' => self::MENU_URL,
                'orden' => $orden,
                'icono' => 'fa-cogs',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $padreId,
                'nombre' => 'Configuración general',
                'orden' => $orden,
                'icono' => 'fa-cogs',
                'updated_at' => now(),
            ]);
        }

        $rolIds = $this->resolverRolIds(self::ROLES);
        foreach ($rolIds as $rolId) {
            DB::table('menu_rol')->updateOrInsert(
                ['menu_id' => $menuId, 'rol_id' => $rolId],
                []
            );
        }

        foreach (self::PERMISOS as $perm) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $perm['slug'])->value('id') ?? 0);
            if ($permisoId === 0) {
                $permisoId = (int) DB::table('permiso')->insertGetId([
                    'nombre' => $perm['nombre'],
                    'slug' => $perm['slug'],
                    'menu_id' => $menuId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('permiso')->where('id', $permisoId)->update([
                    'menu_id' => $menuId,
                    'nombre' => $perm['nombre'],
                    'updated_at' => now(),
                ]);
            }
            foreach ($rolIds as $rolId) {
                DB::table('permiso_rol')->updateOrInsert(
                    ['permiso_id' => $permisoId, 'rol_id' => $rolId],
                    []
                );
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        foreach (self::PERMISOS as $perm) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $perm['slug'])->value('id') ?? 0);
            if ($permisoId > 0) {
                DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
                DB::table('permiso')->where('id', $permisoId)->delete();
            }
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        Schema::dropIfExists('parametro_sistema');

        SuitecrmPermiso::flushCachePermisos();
    }

    private function asegurarParametro(
        string $clave,
        string $grupo,
        string $etiqueta,
        string $ayuda,
        string $tipo,
        string $valor,
        int $orden
    ): void {
        $existe = DB::table('parametro_sistema')->where('clave', $clave)->exists();
        if ($existe) {
            return;
        }

        DB::table('parametro_sistema')->insert([
            'clave' => $clave,
            'grupo' => $grupo,
            'etiqueta' => $etiqueta,
            'ayuda' => $ayuda,
            'tipo' => $tipo,
            'valor' => $valor,
            'orden' => $orden,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function resolverMenuPadreId(): int
    {
        $id = (int) (DB::table('menu')->where('url', 'configuracion/empresa')->value('menu_id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        foreach (['Configuración', 'Módulo Configuración', 'Configuracion'] as $nombre) {
            $id = (int) (DB::table('menu')->where('nombre', $nombre)->where('menu_id', 0)->value('id') ?? 0);
            if ($id > 0) {
                return $id;
            }
        }

        return 33;
    }

    /** @param list<string> $nombres @return list<int> */
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
};
