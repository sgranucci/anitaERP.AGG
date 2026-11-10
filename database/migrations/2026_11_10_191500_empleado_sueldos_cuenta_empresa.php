<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anita emp_cuenta_empresa (columna Informix emp_coche): empresa en la que
 * cuenta el empleado cuando el mismo legajo existe en varias.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empleado_sueldos', function (Blueprint $table) {
            if (! Schema::hasColumn('empleado_sueldos', 'cuenta_empresa_id')) {
                $table->unsignedBigInteger('cuenta_empresa_id')->nullable()->after('empresa_id');
                $table->index('cuenta_empresa_id', 'emp_sueldos_cuenta_empresa_idx');
                $table->foreign('cuenta_empresa_id', 'fk_emp_sueldos_cuenta_empresa')
                    ->references('id')->on('empresa')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('empleado_sueldos', function (Blueprint $table) {
            if (Schema::hasColumn('empleado_sueldos', 'cuenta_empresa_id')) {
                $table->dropForeign('fk_emp_sueldos_cuenta_empresa');
                $table->dropIndex('emp_sueldos_cuenta_empresa_idx');
                $table->dropColumn('cuenta_empresa_id');
            }
        });
    }
};
