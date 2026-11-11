<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flag en la apertura de caja: permite rendir sin habilitación conforme
 * (caja de empleados, QR dinámico, kiosco, etc.).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        if (! Schema::hasTable('tesoreria_crown_asignacion')) {
            return;
        }
        if (Schema::hasColumn('tesoreria_crown_asignacion', 'permite_rendir_sin_habilitacion')) {
            return;
        }

        Schema::table('tesoreria_crown_asignacion', function (Blueprint $table) {
            $table->boolean('permite_rendir_sin_habilitacion')->default(false)->after('turno');
        });
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        if (! Schema::hasTable('tesoreria_crown_asignacion')) {
            return;
        }
        if (! Schema::hasColumn('tesoreria_crown_asignacion', 'permite_rendir_sin_habilitacion')) {
            return;
        }

        Schema::table('tesoreria_crown_asignacion', function (Blueprint $table) {
            $table->dropColumn('permite_rendir_sin_habilitacion');
        });
    }
};
