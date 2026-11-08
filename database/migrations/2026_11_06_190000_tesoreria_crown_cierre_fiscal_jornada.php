<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cierres fiscales del día (Z/X) anclados a la jornada Crown, no solo a la rendición.
 * Así el Anexo II puede armar Ventas Z / anulados hotel desde el ERP.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        if (! Schema::hasTable('tesoreria_crown_cierre_fiscal')) {
            return;
        }
        if (! Schema::hasColumn('tesoreria_crown_cierre_fiscal', 'jornada_id')) {
            Schema::table('tesoreria_crown_cierre_fiscal', function (Blueprint $table) {
                $table->foreignId('jornada_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('tesoreria_crown_jornada')
                    ->nullOnDelete();
            });
        }

        $nullable = DB::selectOne(
            "SELECT IS_NULLABLE AS n FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'tesoreria_crown_cierre_fiscal'
               AND COLUMN_NAME = 'comprobante_id'"
        );
        if ($nullable && strtoupper((string) $nullable->n) === 'NO') {
            Schema::table('tesoreria_crown_cierre_fiscal', function (Blueprint $table) {
                $table->dropForeign(['comprobante_id']);
            });
            DB::statement('ALTER TABLE tesoreria_crown_cierre_fiscal MODIFY comprobante_id BIGINT UNSIGNED NULL');
            Schema::table('tesoreria_crown_cierre_fiscal', function (Blueprint $table) {
                $table->foreign('comprobante_id')
                    ->references('id')
                    ->on('tesoreria_crown_comprobante')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        if (! Schema::hasTable('tesoreria_crown_cierre_fiscal')) {
            return;
        }
        if (Schema::hasColumn('tesoreria_crown_cierre_fiscal', 'jornada_id')) {
            Schema::table('tesoreria_crown_cierre_fiscal', function (Blueprint $table) {
                $table->dropForeign(['jornada_id']);
                $table->dropColumn('jornada_id');
            });
        }
    }
};
