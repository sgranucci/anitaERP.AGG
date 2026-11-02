<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos de a-efeboveda que la carga manual usa: hora, legajo y cliente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown() || ! Schema::hasTable('tesoreria_crown_boveda')) {
            return;
        }

        Schema::table('tesoreria_crown_boveda', function (Blueprint $table) {
            if (! Schema::hasColumn('tesoreria_crown_boveda', 'hora')) {
                $table->char('hora', 5)->nullable()->after('comentario');
            }
            if (! Schema::hasColumn('tesoreria_crown_boveda', 'legajo')) {
                $table->unsignedInteger('legajo')->nullable()->after('hora');
            }
            if (! Schema::hasColumn('tesoreria_crown_boveda', 'cliente_id')) {
                $table->unsignedBigInteger('cliente_id')->nullable()->after('legajo');
            }
        });
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown() || ! Schema::hasTable('tesoreria_crown_boveda')) {
            return;
        }

        $columnas = array_values(array_filter(
            ['cliente_id', 'legajo', 'hora'],
            fn (string $columna) => Schema::hasColumn('tesoreria_crown_boveda', $columna)
        ));
        if ($columnas === []) {
            return;
        }

        Schema::table('tesoreria_crown_boveda', function (Blueprint $table) use ($columnas) {
            $table->dropColumn($columnas);
        });
    }
};
