<?php

use App\Support\Database\MigrationDialectSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sector_legajocompra')) {
            return;
        }

        $existeCompras = DB::table('sector_legajocompra')->where('nombre', 'Compras')->exists();
        if (! $existeCompras) {
            DB::table('sector_legajocompra')->insert([
                'nombre' => 'Compras',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('ordencompra', function (Blueprint $table) {
            if (! Schema::hasColumn('ordencompra', 'sector_legajocompra_id')) {
                $table->unsignedBigInteger('sector_legajocompra_id')->nullable()->after('estadoordencompra');
                $table->foreign('sector_legajocompra_id', 'fk_ordencompra_sector_legajocompra')
                    ->references('id')->on('sector_legajocompra')->nullOnDelete();
            }
            if (! Schema::hasColumn('ordencompra', 'condiciones_contratacion')) {
                $table->longText('condiciones_contratacion')->nullable()->after('sector_legajocompra_id');
            }
        });

        if (Schema::hasColumn('arbolaprobacion_nivel', 'requisicion_estado_al_aprobar')
            && ! Schema::hasColumn('arbolaprobacion_nivel', 'documento_estado_al_aprobar')) {
            MigrationDialectSupport::renombrarColumna(
                'arbolaprobacion_nivel',
                'requisicion_estado_al_aprobar',
                'documento_estado_al_aprobar',
                'VARCHAR(50) NULL'
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('arbolaprobacion_nivel', 'documento_estado_al_aprobar')
            && ! Schema::hasColumn('arbolaprobacion_nivel', 'requisicion_estado_al_aprobar')) {
            MigrationDialectSupport::renombrarColumna(
                'arbolaprobacion_nivel',
                'documento_estado_al_aprobar',
                'requisicion_estado_al_aprobar',
                'VARCHAR(50) NULL'
            );
        }

        Schema::table('ordencompra', function (Blueprint $table) {
            if (Schema::hasColumn('ordencompra', 'condiciones_contratacion')) {
                $table->dropColumn('condiciones_contratacion');
            }
            if (Schema::hasColumn('ordencompra', 'sector_legajocompra_id')) {
                $table->dropForeign('fk_ordencompra_sector_legajocompra');
                $table->dropColumn('sector_legajocompra_id');
            }
        });
    }
};
