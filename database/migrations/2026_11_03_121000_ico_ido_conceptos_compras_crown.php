<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Crown: ICO e IDO no tenían conceptos de IVA compras asociados.
 * Se les asigna el mismo set que FDS (factura directa de servicios),
 * que es el patrón usado para gastos/servicios financieros bancarios.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const TIPOS = ['ICO', 'IDO'];

    /**
     * Códigos de concepto_ivacompra (misma lista que FDS en Crown).
     *
     * @var list<string>
     */
    private const CONCEPTOS_CODIGO = [
        '1',   // Exento/no gravado
        '2',   // Compras monotributistas
        '4',   // Gravado al 27
        '5',   // Impuesto interno
        '6',   // Gravado al 10.5
        '51',  // Gravado Servic.21
        '55',  // Gravado al 5%
        '103', // Percepcion IVA 3
        '111', // Iva al 5%
        '140', // Perc.Ing.Bruto Rio Negro
        '141', // Perc.Ing.Bruto BS.AIRES
        '142', // Perc.Ing.Bruto Neuquen
        '143', // Perc.Ing.Bruto Capital
        '221', // Iva Serv.Dir.21
        '222', // Iva Serv.Dir.10.5
        '223', // Iva Serv.Dir.27
    ];

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

        $conceptoIds = DB::table('concepto_ivacompra')
            ->whereIn('codigo', self::CONCEPTOS_CODIGO)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($conceptoIds === []) {
            return;
        }

        $ahora = now();
        foreach (self::TIPOS as $abreviatura) {
            $tipoId = (int) (DB::table('tipotransaccion_compra')
                ->whereRaw('UPPER(TRIM(abreviatura)) = ?', [$abreviatura])
                ->where('estado', 'A')
                ->value('id') ?? 0);
            if ($tipoId <= 0) {
                continue;
            }

            $ya = DB::table('tipotransaccion_compra_concepto_ivacompra')
                ->where('tipotransaccion_compra_id', $tipoId)
                ->pluck('concepto_ivacompra_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            foreach ($conceptoIds as $conceptoId) {
                if (in_array($conceptoId, $ya, true)) {
                    continue;
                }
                DB::table('tipotransaccion_compra_concepto_ivacompra')->insert([
                    'tipotransaccion_compra_id' => $tipoId,
                    'concepto_ivacompra_id' => $conceptoId,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $conceptoIds = DB::table('concepto_ivacompra')
            ->whereIn('codigo', self::CONCEPTOS_CODIGO)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        if ($conceptoIds === []) {
            return;
        }

        foreach (self::TIPOS as $abreviatura) {
            $tipoId = (int) (DB::table('tipotransaccion_compra')
                ->whereRaw('UPPER(TRIM(abreviatura)) = ?', [$abreviatura])
                ->value('id') ?? 0);
            if ($tipoId <= 0) {
                continue;
            }
            DB::table('tipotransaccion_compra_concepto_ivacompra')
                ->where('tipotransaccion_compra_id', $tipoId)
                ->whereIn('concepto_ivacompra_id', $conceptoIds)
                ->delete();
        }
    }
};
