<?php

use App\Support\Database\MigrationDialectSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tiposervicio_proveedor', function (Blueprint $table) {
            $table->string('controla_unicidad_cuit', 50)
                ->default('CONTROLA')
                ->after('nombre');
        });

        DB::table('tiposervicio_proveedor')->where('id', '!=', 4)->update([
            'controla_unicidad_cuit' => 'CONTROLA',
            'updated_at' => now(),
        ]);

        $now = now();
        $row = DB::table('tiposervicio_proveedor')->where('id', 4)->first();
        if ($row) {
            DB::table('tiposervicio_proveedor')->where('id', 4)->update([
                'nombre' => 'Entidades',
                'controla_unicidad_cuit' => 'NO CONTROLA',
                'updated_at' => $now,
            ]);
        } else {
            DB::table('tiposervicio_proveedor')->insert([
                'id' => 4,
                'nombre' => 'Entidades',
                'controla_unicidad_cuit' => 'NO CONTROLA',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $maxId = (int) DB::table('tiposervicio_proveedor')->max('id');
        MigrationDialectSupport::reiniciarAutoincrement('tiposervicio_proveedor', 'id', $maxId + 1);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tiposervicio_proveedor', function (Blueprint $table) {
            $table->dropColumn('controla_unicidad_cuit');
        });
    }
};
