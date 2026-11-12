<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tarea operativa del cajero (reemplaza tipo_tarea de la asignación de jornada).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        if (! Schema::hasTable('cajero_crown')) {
            return;
        }

        Schema::table('cajero_crown', function (Blueprint $table) {
            if (! Schema::hasColumn('cajero_crown', 'tipo_tarea')) {
                $table->char('tipo_tarea', 1)->nullable()->after('mozo_gastronomia_id');
            }
            if (! Schema::hasColumn('cajero_crown', 'permite_rendir_sin_habilitacion')) {
                $table->boolean('permite_rendir_sin_habilitacion')->default(false)->after('tipo_tarea');
            }
        });
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        if (! Schema::hasTable('cajero_crown')) {
            return;
        }

        Schema::table('cajero_crown', function (Blueprint $table) {
            if (Schema::hasColumn('cajero_crown', 'permite_rendir_sin_habilitacion')) {
                $table->dropColumn('permite_rendir_sin_habilitacion');
            }
            if (Schema::hasColumn('cajero_crown', 'tipo_tarea')) {
                $table->dropColumn('tipo_tarea');
            }
        });
    }
};
