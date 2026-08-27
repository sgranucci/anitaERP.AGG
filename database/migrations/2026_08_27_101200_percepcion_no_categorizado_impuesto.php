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
        Schema::create('configuracion_percepcion_no_categorizado', function (Blueprint $table) {
            $table->id();
            $table->boolean('habilitado')->default(false);
            $table->decimal('tasa', 8, 4)->default(10.5);
            $table->decimal('minimo', 15, 2)->default(0);
            $table->timestamps();
        });

        $ahora = now();
        DB::table('configuracion_percepcion_no_categorizado')->insert([
            'habilitado' => EntornoEmpresaSupport::esElBierzo(),
            'tasa' => 10.5,
            'minimo' => 0,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);

        if (! DB::table('impuesto')->where('codigo', 'PNC')->exists()) {
            DB::table('impuesto')->insert([
                'nombre' => 'Percepcion no categorizado',
                'valor' => 10.5,
                'fechavigencia' => '2000-01-01',
                'codigo' => 'PNC',
                'codigoarca' => '99',
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('impuesto')->where('codigo', 'PNC')->delete();
        Schema::dropIfExists('configuracion_percepcion_no_categorizado');
    }
};
