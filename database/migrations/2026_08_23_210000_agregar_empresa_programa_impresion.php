<?php

use App\Support\Database\MigrationDialectSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('comprobante_impresion_programa')) {
            return;
        }

        Schema::table('comprobante_impresion_programa', function (Blueprint $table) {
            if (! Schema::hasColumn('comprobante_impresion_programa', 'empresa_id')) {
                $table->unsignedBigInteger('empresa_id')->nullable()->after('nombre');
            }
        });

        MigrationDialectSupport::dropIndiceOUnique(
            'comprobante_impresion_programa',
            'comprobante_impresion_programa_codigo_unique'
        );

        if (! MigrationDialectSupport::tieneIndice('comprobante_impresion_programa', 'uk_compimp_prog_emp_codigo')) {
            Schema::table('comprobante_impresion_programa', function (Blueprint $table) {
                $table->unique(['empresa_id', 'codigo'], 'uk_compimp_prog_emp_codigo');
            });
        }

        if (! MigrationDialectSupport::tieneForeignKey('comprobante_impresion_programa', 'fk_compimp_prog_empresa')) {
            Schema::table('comprobante_impresion_programa', function (Blueprint $table) {
                $table->foreign('empresa_id', 'fk_compimp_prog_empresa')
                    ->references('id')->on('empresa')->onDelete('restrict');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('comprobante_impresion_programa')) {
            return;
        }

        if (MigrationDialectSupport::tieneForeignKey('comprobante_impresion_programa', 'fk_compimp_prog_empresa')) {
            Schema::table('comprobante_impresion_programa', function (Blueprint $table) {
                $table->dropForeign('fk_compimp_prog_empresa');
            });
        }
        MigrationDialectSupport::dropIndiceOUnique('comprobante_impresion_programa', 'uk_compimp_prog_emp_codigo');

        Schema::table('comprobante_impresion_programa', function (Blueprint $table) {
            if (Schema::hasColumn('comprobante_impresion_programa', 'empresa_id')) {
                $table->dropColumn('empresa_id');
            }
        });

        if (! MigrationDialectSupport::tieneIndice('comprobante_impresion_programa', 'comprobante_impresion_programa_codigo_unique')) {
            Schema::table('comprobante_impresion_programa', function (Blueprint $table) {
                $table->unique('codigo');
            });
        }
    }
};
