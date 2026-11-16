<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Crown: FNS (Factura N/C Servicios) tenía asociado el IVA computable «IVA GRAVADO 21 %»
 * (código 74) además del correcto «Iva Serv.N/C 21» (421).
 *
 * En el alta de comprobante IVA, al cargar Gravado Servic.21 se liquidaban ambos
 * (el 74 caía al fallback por alícuota porque su fórmula Anita `con(73)*.21` no
 * parseaba). Solo FNS tenía ese vínculo; el resto de tipos N/C (FNB/FNL/…) no.
 */
return new class extends Migration
{
    private const TIPO_ABREVIATURA = 'FNS';

    /** IVA GRAVADO 21 % (computable / normal). */
    private const CONCEPTO_IVA_NORMAL_CODIGO = '74';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        if (! DB::getSchemaBuilder()->hasTable('tipotransaccion_compra')
            || ! DB::getSchemaBuilder()->hasTable('concepto_ivacompra')
            || ! DB::getSchemaBuilder()->hasTable('tipotransaccion_compra_concepto_ivacompra')) {
            return;
        }

        $tipoId = (int) (DB::table('tipotransaccion_compra')
            ->whereRaw('UPPER(TRIM(abreviatura)) = ?', [self::TIPO_ABREVIATURA])
            ->value('id') ?? 0);
        $conceptoId = (int) (DB::table('concepto_ivacompra')
            ->where('codigo', self::CONCEPTO_IVA_NORMAL_CODIGO)
            ->value('id') ?? 0);
        if ($tipoId <= 0 || $conceptoId <= 0) {
            return;
        }

        DB::table('tipotransaccion_compra_concepto_ivacompra')
            ->where('tipotransaccion_compra_id', $tipoId)
            ->where('concepto_ivacompra_id', $conceptoId)
            ->delete();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        if (! DB::getSchemaBuilder()->hasTable('tipotransaccion_compra')
            || ! DB::getSchemaBuilder()->hasTable('concepto_ivacompra')
            || ! DB::getSchemaBuilder()->hasTable('tipotransaccion_compra_concepto_ivacompra')) {
            return;
        }

        $tipoId = (int) (DB::table('tipotransaccion_compra')
            ->whereRaw('UPPER(TRIM(abreviatura)) = ?', [self::TIPO_ABREVIATURA])
            ->value('id') ?? 0);
        $conceptoId = (int) (DB::table('concepto_ivacompra')
            ->where('codigo', self::CONCEPTO_IVA_NORMAL_CODIGO)
            ->value('id') ?? 0);
        if ($tipoId <= 0 || $conceptoId <= 0) {
            return;
        }

        $ya = DB::table('tipotransaccion_compra_concepto_ivacompra')
            ->where('tipotransaccion_compra_id', $tipoId)
            ->where('concepto_ivacompra_id', $conceptoId)
            ->exists();
        if ($ya) {
            return;
        }

        $ahora = now();
        DB::table('tipotransaccion_compra_concepto_ivacompra')->insert([
            'tipotransaccion_compra_id' => $tipoId,
            'concepto_ivacompra_id' => $conceptoId,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
    }
};
