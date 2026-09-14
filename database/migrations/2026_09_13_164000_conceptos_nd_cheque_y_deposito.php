<?php

use App\Models\Ventas\Concepto_Venta;
use App\Models\Ventas\Tipotransaccion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Conceptos de venta para ND por cheque rechazado (NDR) + columnas depósito CHT.
 */
return new class extends Migration
{
    private const COD_CHEQUE = 'NDR-CHEQUE';

    private const COD_GASTOS = 'NDR-GASTOS';

    public function up(): void
    {
        Schema::table('cheque', function (Blueprint $table) {
            if (! Schema::hasColumn('cheque', 'fecha_deposito')) {
                $table->date('fecha_deposito')->nullable()->after('motivo_rechazo');
            }
            if (! Schema::hasColumn('cheque', 'cuentacaja_deposito_id')) {
                $table->unsignedBigInteger('cuentacaja_deposito_id')->nullable()->after('fecha_deposito');
                $table->index('cuentacaja_deposito_id', 'cheque_cuentacaja_deposito_idx');
            }
            if (! Schema::hasColumn('cheque', 'nro_boleta_deposito')) {
                $table->string('nro_boleta_deposito', 40)->nullable()->after('cuentacaja_deposito_id');
            }
        });

        $impuestoId = (int) (DB::table('impuesto')->where('id', 3)->value('id')
            ?? DB::table('impuesto')->orderBy('id')->value('id')
            ?? 3);
        $unidadId = (int) (DB::table('unidadmedida')->where('abreviatura', 'UNI')->value('id')
            ?? DB::table('unidadmedida')->orderBy('id')->value('id')
            ?? 3);

        $conceptoChequeId = $this->upsertConcepto(
            self::COD_CHEQUE,
            'Cheque rechazado',
            'Nota de débito por cheque de terceros rechazado',
            $impuestoId,
            $unidadId
        );
        $conceptoGastosId = $this->upsertConcepto(
            self::COD_GASTOS,
            'Gastos bancarios cheque rechazado',
            'Gastos / comisiones bancarias por rechazo de cheque',
            $impuestoId,
            $unidadId
        );

        $ndr = Tipotransaccion::query()->where('abreviatura', 'NDR')->first();
        if ($ndr && (int) ($ndr->concepto_venta_id ?? 0) <= 0) {
            $ndr->concepto_venta_id = $conceptoChequeId;
            $ndr->save();
        }

        // Defaults de config vía env se documentan; IDs quedan en tabla.
        // No escribe .env aquí.
    }

    public function down(): void
    {
        Schema::table('cheque', function (Blueprint $table) {
            if (Schema::hasColumn('cheque', 'nro_boleta_deposito')) {
                $table->dropColumn('nro_boleta_deposito');
            }
            if (Schema::hasColumn('cheque', 'cuentacaja_deposito_id')) {
                $table->dropIndex('cheque_cuentacaja_deposito_idx');
                $table->dropColumn('cuentacaja_deposito_id');
            }
            if (Schema::hasColumn('cheque', 'fecha_deposito')) {
                $table->dropColumn('fecha_deposito');
            }
        });

        $ids = Concepto_Venta::query()
            ->whereIn('codigo', [self::COD_CHEQUE, self::COD_GASTOS])
            ->pluck('id');
        if ($ids->isNotEmpty()) {
            Tipotransaccion::query()
                ->whereIn('concepto_venta_id', $ids->all())
                ->update(['concepto_venta_id' => null]);
            Concepto_Venta::query()->whereIn('id', $ids->all())->delete();
        }
    }

    private function upsertConcepto(
        string $codigo,
        string $nombre,
        string $descripcion,
        int $impuestoId,
        int $unidadId
    ): int {
        $existente = Concepto_Venta::query()->where('codigo', $codigo)->first();
        if ($existente) {
            $existente->fill([
                'nombre' => $nombre,
                'descripcion' => $descripcion,
                'impuesto_id' => $impuestoId,
                'unidadmedida_id' => $unidadId,
                'activo' => true,
            ]);
            $existente->save();

            return (int) $existente->id;
        }

        $nuevo = Concepto_Venta::query()->create([
            'codigo' => $codigo,
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'codigo_gtin' => null,
            'unidades_mtx' => 1,
            'impuesto_id' => $impuestoId,
            'unidadmedida_id' => $unidadId,
            'activo' => true,
            'codigo_anita' => null,
        ]);

        return (int) $nuevo->id;
    }
};
