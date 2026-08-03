<?php

use App\Support\Caja\Remesa\RemesaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tipo de asiento contable REM para separar remesas de TES (tesorería genérica).
 */
return new class extends Migration
{
    public function up(): void
    {
        $abrev = RemesaSupport::ABREV_TIPOASIENTO;

        $tipoId = (int) (DB::table('tipoasiento')->where('abreviatura', $abrev)->value('id') ?? 0);
        if ($tipoId === 0) {
            $tipoId = (int) DB::table('tipoasiento')->insertGetId([
                'nombre' => 'Remesa',
                'abreviatura' => $abrev,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('tipoasiento')->where('id', $tipoId)->update([
                'nombre' => 'Remesa',
                'updated_at' => now(),
            ]);
        }

        // Históricos de remesa que hayan quedado como TES.
        if ($tipoId > 0 && DB::getSchemaBuilder()->hasColumn('asiento', 'remesa_id')) {
            $tesId = (int) (DB::table('tipoasiento')->where('abreviatura', 'TES')->value('id') ?? 0);
            if ($tesId > 0) {
                DB::table('asiento')
                    ->whereNotNull('remesa_id')
                    ->where('remesa_id', '>', 0)
                    ->where('tipoasiento_id', $tesId)
                    ->update([
                        'tipoasiento_id' => $tipoId,
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    public function down(): void
    {
        // No eliminar: puede haber asientos históricos con el tipo.
    }
};
