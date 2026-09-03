<?php

use App\ApiAnita;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * I.T.C. (código Anita 510) era tipo N (no gravado) y el asiento de factura vs COM
 * lo sumaba al neto. Debe ser T (impuesto interno): Debe en su cuenta, no revierte provisión.
 */
return new class extends Migration
{
    private const CODIGO_ANITA = 510;

    public function up(): void
    {
        if (! Schema::hasTable('concepto_ivacompra')) {
            return;
        }

        $ids = DB::table('concepto_ivacompra')
            ->where(function ($q) {
                $q->where('codigo', self::CODIGO_ANITA)
                    ->orWhere('nombre', 'like', 'I.T.C.%')
                    ->orWhere('nombre', 'I.T.C.');
            })
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        DB::table('concepto_ivacompra')
            ->whereIn('id', $ids)
            ->update([
                'tipoconcepto' => 'T',
                'updated_at' => now(),
            ]);

        $this->actualizarAnitaTipoT();
    }

    public function down(): void
    {
        if (! Schema::hasTable('concepto_ivacompra')) {
            return;
        }

        DB::table('concepto_ivacompra')
            ->where('codigo', self::CODIGO_ANITA)
            ->update([
                'tipoconcepto' => 'N',
                'updated_at' => now(),
            ]);
    }

    private function actualizarAnitaTipoT(): void
    {
        if (! class_exists(ApiAnita::class)) {
            return;
        }

        try {
            $api = new ApiAnita;
            $api->apiCallEscritura([
                'acc' => 'update',
                'tabla' => 'conccomp',
                'sistema' => 'compras',
                'valores' => " concc_tipo_conc = 'T' ",
                'whereArmado' => " WHERE concc_concepto = '".self::CODIGO_ANITA."' ",
            ]);
        } catch (\Throwable $e) {
            Log::warning('concepto_ivacompra ITC: no se pudo actualizar conccomp en Anita', [
                'codigo' => self::CODIGO_ANITA,
                'error' => $e->getMessage(),
            ]);
        }
    }
};
