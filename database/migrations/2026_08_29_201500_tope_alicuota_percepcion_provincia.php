<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provincia', function (Blueprint $table) {
            if (! Schema::hasColumn('provincia', 'tope_alicuota_percepcion')) {
                $table->decimal('tope_alicuota_percepcion', 8, 4)->nullable()->after('minimocoeficientecm05');
            }
        });

        if (EntornoEmpresaSupport::esElBierzo()) {
            DB::table('provincia')
                ->where('jurisdiccion', 904)
                ->update(['tope_alicuota_percepcion' => 0.4]);
        }
    }

    public function down(): void
    {
        Schema::table('provincia', function (Blueprint $table) {
            if (Schema::hasColumn('provincia', 'tope_alicuota_percepcion')) {
                $table->dropColumn('tope_alicuota_percepcion');
            }
        });
    }
};
