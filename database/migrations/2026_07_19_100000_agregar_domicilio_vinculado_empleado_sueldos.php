<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Domicilio del empleado vinculado a maestros reales (país / provincia / localidad),
 * al igual que el maestro de proveedores. Las columnas de texto existentes
 * (provincia, localidad) quedan como descripción denormalizada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empleado_sueldos', function (Blueprint $table) {
            if (! Schema::hasColumn('empleado_sueldos', 'pais_id')) {
                $table->unsignedBigInteger('pais_id')->nullable()->after('provincia');
            }
            if (! Schema::hasColumn('empleado_sueldos', 'provincia_id')) {
                $table->unsignedBigInteger('provincia_id')->nullable()->after('pais_id');
            }
            if (! Schema::hasColumn('empleado_sueldos', 'localidad_id')) {
                $table->unsignedBigInteger('localidad_id')->nullable()->after('provincia_id');
            }
        });

        Schema::table('empleado_sueldos', function (Blueprint $table) {
            $table->index('provincia_id', 'emp_sueldos_provincia_id_idx');
            $table->index('localidad_id', 'emp_sueldos_localidad_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('empleado_sueldos', function (Blueprint $table) {
            $table->dropIndex('emp_sueldos_provincia_id_idx');
            $table->dropIndex('emp_sueldos_localidad_id_idx');
            $table->dropColumn(['pais_id', 'provincia_id', 'localidad_id']);
        });
    }
};
