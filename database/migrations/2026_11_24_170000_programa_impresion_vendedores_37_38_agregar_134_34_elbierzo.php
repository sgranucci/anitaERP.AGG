<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\ComprobanteImpresionReglaClave;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El Bierzo: suma vendedores 134 y 34 al programa VEND_37_38_TRI.
 */
return new class extends Migration
{
    private const CODIGO_PROGRAMA = 'VEND_37_38_TRI';

    /** @var list<string> */
    private const CODIGOS_VENDEDOR = ['134', '34'];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }
        if (! Schema::hasTable('comprobante_impresion_programa')
            || ! Schema::hasTable('comprobante_impresion_regla')
            || ! Schema::hasTable('vendedor')) {
            return;
        }

        $programaId = (int) (DB::table('comprobante_impresion_programa')
            ->where('codigo', self::CODIGO_PROGRAMA)
            ->value('id') ?? 0);
        if ($programaId <= 0) {
            return;
        }

        $ahora = now();
        $prioridad = (int) (ComprobanteImpresionReglaClave::PRECEDENCIA[ComprobanteImpresionReglaClave::VENDEDOR] ?? 50);

        foreach (self::CODIGOS_VENDEDOR as $codigo) {
            $vendedorId = (int) (DB::table('vendedor')
                ->where('codigo', $codigo)
                ->value('id') ?? 0);
            if ($vendedorId <= 0) {
                continue;
            }

            $existe = DB::table('comprobante_impresion_regla')
                ->where('programa_id', $programaId)
                ->where('clave', ComprobanteImpresionReglaClave::VENDEDOR)
                ->where('valor_id', $vendedorId)
                ->exists();
            if ($existe) {
                continue;
            }

            DB::table('comprobante_impresion_regla')->insert([
                'programa_id' => $programaId,
                'clave' => ComprobanteImpresionReglaClave::VENDEDOR,
                'valor_id' => $vendedorId,
                'prioridad' => $prioridad,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }
        if (! Schema::hasTable('comprobante_impresion_programa')
            || ! Schema::hasTable('comprobante_impresion_regla')
            || ! Schema::hasTable('vendedor')) {
            return;
        }

        $programaId = (int) (DB::table('comprobante_impresion_programa')
            ->where('codigo', self::CODIGO_PROGRAMA)
            ->value('id') ?? 0);
        if ($programaId <= 0) {
            return;
        }

        $vendedorIds = DB::table('vendedor')
            ->whereIn('codigo', self::CODIGOS_VENDEDOR)
            ->pluck('id');
        if ($vendedorIds->isEmpty()) {
            return;
        }

        DB::table('comprobante_impresion_regla')
            ->where('programa_id', $programaId)
            ->where('clave', ComprobanteImpresionReglaClave::VENDEDOR)
            ->whereIn('valor_id', $vendedorIds)
            ->delete();
    }
};
