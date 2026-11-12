<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Crown: el tipo REC (Recibo) no tenía conceptos de IVA compras asociados,
 * así que al cargar un comprobante REC la solapa de conceptos quedaba vacía.
 * Se le asigna «Compras monotributistas» (código 2), el concepto de precio
 * en factura C / monotributo.
 */
return new class extends Migration
{
    private const TIPO_ABREVIATURA = 'REC';

    /** Código de concepto_ivacompra: Compras monotributistas. */
    private const CONCEPTO_CODIGO = '2';

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
            ->where('estado', 'A')
            ->value('id') ?? 0);
        if ($tipoId <= 0) {
            return;
        }

        $conceptoId = (int) (DB::table('concepto_ivacompra')
            ->where('codigo', self::CONCEPTO_CODIGO)
            ->value('id') ?? 0);
        if ($conceptoId <= 0) {
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

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $tipoId = (int) (DB::table('tipotransaccion_compra')
            ->whereRaw('UPPER(TRIM(abreviatura)) = ?', [self::TIPO_ABREVIATURA])
            ->value('id') ?? 0);
        $conceptoId = (int) (DB::table('concepto_ivacompra')
            ->where('codigo', self::CONCEPTO_CODIGO)
            ->value('id') ?? 0);
        if ($tipoId <= 0 || $conceptoId <= 0) {
            return;
        }

        DB::table('tipotransaccion_compra_concepto_ivacompra')
            ->where('tipotransaccion_compra_id', $tipoId)
            ->where('concepto_ivacompra_id', $conceptoId)
            ->delete();
    }
};
