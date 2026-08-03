<?php

use App\Support\Caja\Remesa\RemesaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FK remesa ↔ caja_movimiento ↔ asiento + tipos REM/RMI.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('remesa') && ! Schema::hasColumn('remesa', 'caja_movimiento_id')) {
            Schema::table('remesa', function (Blueprint $table) {
                $table->unsignedBigInteger('caja_movimiento_id')->nullable()->after('asiento_id');
                $table->index('caja_movimiento_id', 'idx_remesa_caja_movimiento');
            });
        }

        if (Schema::hasTable('asiento') && ! Schema::hasColumn('asiento', 'remesa_id')) {
            Schema::table('asiento', function (Blueprint $table) {
                $table->unsignedBigInteger('remesa_id')->nullable()->after('caja_movimiento_id');
                $table->index('remesa_id', 'idx_asiento_remesa');
            });
        }

        $this->upsertTipo(RemesaSupport::ABREV_REM, 'Remesa externa', 'I', 'I');
        $this->upsertTipo(RemesaSupport::ABREV_RMI, 'Remesa interna', 'I', 'I');
    }

    public function down(): void
    {
        if (Schema::hasTable('asiento') && Schema::hasColumn('asiento', 'remesa_id')) {
            Schema::table('asiento', function (Blueprint $table) {
                $table->dropIndex('idx_asiento_remesa');
                $table->dropColumn('remesa_id');
            });
        }

        if (Schema::hasTable('remesa') && Schema::hasColumn('remesa', 'caja_movimiento_id')) {
            Schema::table('remesa', function (Blueprint $table) {
                $table->dropIndex('idx_remesa_caja_movimiento');
                $table->dropColumn('caja_movimiento_id');
            });
        }

        // No borra tipos REM/RMI: pueden tener movimientos.
    }

    private function upsertTipo(string $abreviatura, string $nombre, string $operacion, string $signoDb): void
    {
        $signo = $signoDb === 'E' ? -1 : 1;
        $existente = DB::table('tipotransaccion_caja')
            ->where('abreviatura', $abreviatura)
            ->whereNull('deleted_at')
            ->first();

        if ($existente) {
            DB::table('tipotransaccion_caja')->where('id', $existente->id)->update([
                'nombre' => $nombre,
                'operacion' => $operacion,
                'signo' => $signo,
                'estado' => 'A',
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('tipotransaccion_caja')->insert([
            'nombre' => $nombre,
            'operacion' => $operacion,
            'abreviatura' => $abreviatura,
            'signo' => $signo,
            'estado' => 'A',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
