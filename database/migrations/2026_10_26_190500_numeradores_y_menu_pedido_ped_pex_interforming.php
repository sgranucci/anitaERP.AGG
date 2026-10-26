<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * INTERFORMING: numeradores ventas.PED / ventas.PEX y dos entradas de menú
 * (Pedidos de venta / Pedidos de exportación).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esInterforming()) {
            return;
        }

        $this->sembrarNumeradores();
        $this->menus();
        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esInterforming()) {
            return;
        }

        if (Schema::hasTable('sistema_numerador')) {
            DB::table('sistema_numerador')
                ->whereIn('codigo', ['ventas.PED', 'ventas.PEX'])
                ->delete();
        }

        $exportId = (int) (DB::table('menu')->where('url', 'ventas/pedidos-exportacion')->value('id') ?? 0);
        if ($exportId > 0) {
            DB::table('menu_rol')->where('menu_id', $exportId)->delete();
            DB::table('menu')->where('id', $exportId)->delete();
        }

        DB::table('menu')->where('url', 'ventas/pedidos-venta')->update([
            'nombre' => 'Pedidos',
            'url' => 'ventas/pedido',
            'updated_at' => now(),
        ]);

        SuitecrmPermiso::flushCachePermisos();
    }

    private function sembrarNumeradores(): void
    {
        if (! Schema::hasTable('sistema_numerador') || ! Schema::hasTable('pedido') || ! Schema::hasTable('empresa')) {
            return;
        }

        $empresaId = (int) (DB::table('empresa')->orderBy('id')->value('id') ?? 0);
        if ($empresaId <= 0) {
            return;
        }

        $ahora = now();
        foreach ([
            'PED' => 'Pedidos de venta (PED)',
            'PEX' => 'Pedidos de exportación (PEX)',
        ] as $tipo => $nombre) {
            $codigo = 'ventas.'.$tipo;
            if (DB::table('sistema_numerador')->where('codigo', $codigo)->where('empresa_id', $empresaId)->exists()) {
                continue;
            }
            $ultimo = 0;
            if (Schema::hasColumn('pedido', 'tipo_comprobante') && Schema::hasColumn('pedido', 'numero_comprobante')) {
                $ultimo = (int) (DB::table('pedido')->where('tipo_comprobante', $tipo)->max('numero_comprobante') ?? 0);
            }
            DB::table('sistema_numerador')->insert([
                'codigo' => $codigo,
                'nombre' => $nombre,
                'empresa_id' => $empresaId,
                'modulo' => 'ventas',
                'ultimo_numero' => $ultimo,
                'numera_en_erp' => true,
                'activo' => true,
                'observacion' => 'Secuencia ERP. El alta elige PED o PEX según la letra de IVA del cliente.',
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }

    private function menus(): void
    {
        $pedido = DB::table('menu')->where('url', 'ventas/pedido')->first();
        if (! $pedido) {
            $pedido = DB::table('menu')->where('url', 'ventas/pedidos-venta')->first();
        }
        if (! $pedido) {
            return;
        }

        $ahora = now();
        DB::table('menu')->where('id', $pedido->id)->update([
            'nombre' => 'Pedidos de venta',
            'url' => 'ventas/pedidos-venta',
            'updated_at' => $ahora,
        ]);

        $exportId = (int) (DB::table('menu')->where('url', 'ventas/pedidos-exportacion')->value('id') ?? 0);
        if ($exportId <= 0) {
            $exportId = (int) DB::table('menu')->insertGetId([
                'nombre' => 'Pedidos de exportación',
                'url' => 'ventas/pedidos-exportacion',
                'menu_id' => $pedido->menu_id,
                'orden' => ((int) $pedido->orden) + 1,
                'icono' => $pedido->icono ?: 'fa-circle',
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }

        $roles = DB::table('menu_rol')->where('menu_id', $pedido->id)->pluck('rol_id');
        foreach ($roles as $rolId) {
            $existe = DB::table('menu_rol')
                ->where('menu_id', $exportId)
                ->where('rol_id', $rolId)
                ->exists();
            if (! $existe) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $exportId,
                    'rol_id' => $rolId,
                ]);
            }
        }
    }
};
