<?php

use App\Support\Compras\ProveedorRetencionIibbAnitaSupport;
use App\Support\Compras\Retencion\RetencionIibbCrownMatrizSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Crown: matriz IIBB sin condición «No inscripto».
 * - % fuera de jurisdicción en retencionIIBB.porcentajeretencion_fuera (5).
 * - Condición 1 = Convenio; 3 queda inactiva; proveedores 3 → 1.
 * - Agente retención RN en todas las empresas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        if (Schema::hasTable('retencionIIBB')
            && ! Schema::hasColumn('retencionIIBB', 'porcentajeretencion_fuera')) {
            Schema::table('retencionIIBB', function (Blueprint $table) {
                $table->decimal('porcentajeretencion_fuera', 8, 4)
                    ->nullable()
                    ->after('cuentacontable_id');
            });
        }

        $rnId = RetencionIibbCrownMatrizSupport::PROVINCIA_LOCAL_ID;
        $ahora = now();

        if (Schema::hasTable('retencionIIBB')) {
            DB::table('retencionIIBB')
                ->where('provincia_id', $rnId)
                ->update([
                    'porcentajeretencion_fuera' => 5,
                    'updated_at' => $ahora,
                ]);
        }

        if (Schema::hasTable('condicionIIBB')) {
            DB::table('condicionIIBB')
                ->where('id', ProveedorRetencionIibbAnitaSupport::CONDICION_CONVENIO)
                ->update([
                    'nombre' => 'Convenio',
                    'updated_at' => $ahora,
                ]);

            DB::table('condicionIIBB')
                ->where('id', ProveedorRetencionIibbAnitaSupport::CONDICION_NO_INSCRIPTO)
                ->update([
                    'estado' => 'B',
                    'formacalculo' => 'N',
                    'updated_at' => $ahora,
                ]);
        }

        if (Schema::hasTable('proveedor')) {
            $q = DB::table('proveedor')
                ->where('condicionIIBB_id', ProveedorRetencionIibbAnitaSupport::CONDICION_NO_INSCRIPTO);
            if (Schema::hasColumn('proveedor', 'deleted_at')) {
                $q->whereNull('deleted_at');
            }
            $q->update([
                'condicionIIBB_id' => ProveedorRetencionIibbAnitaSupport::CONDICION_CONVENIO,
                'updated_at' => $ahora,
            ]);
        }

        if (Schema::hasTable('retencionIIBB_condicion')) {
            DB::table('retencionIIBB_condicion')
                ->where('condicionIIBB_id', ProveedorRetencionIibbAnitaSupport::CONDICION_NO_INSCRIPTO)
                ->delete();
        }

        if (Schema::hasTable('empresa_jurisdiccion_iibb')) {
            DB::table('empresa_jurisdiccion_iibb')
                ->where('provincia_id', $rnId)
                ->update([
                    'es_agente_retencion' => 1,
                    'updated_at' => $ahora,
                ]);
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $ahora = now();

        if (Schema::hasTable('condicionIIBB')) {
            DB::table('condicionIIBB')
                ->where('id', ProveedorRetencionIibbAnitaSupport::CONDICION_CONVENIO)
                ->update([
                    'nombre' => 'Inscripto',
                    'updated_at' => $ahora,
                ]);

            DB::table('condicionIIBB')
                ->where('id', ProveedorRetencionIibbAnitaSupport::CONDICION_NO_INSCRIPTO)
                ->update([
                    'estado' => 'A',
                    'formacalculo' => 'R',
                    'nombre' => 'No inscripto',
                    'updated_at' => $ahora,
                ]);
        }

        if (Schema::hasTable('retencionIIBB')
            && Schema::hasColumn('retencionIIBB', 'porcentajeretencion_fuera')) {
            Schema::table('retencionIIBB', function (Blueprint $table) {
                $table->dropColumn('porcentajeretencion_fuera');
            });
        }
    }
};
