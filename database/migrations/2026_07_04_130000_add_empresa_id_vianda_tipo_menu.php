<?php

use App\Support\Database\MigrationDialectSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Empresa dueña del tipo de menú de vianda.
 *
 * Los tipos de menú ya importados son de Biyemas → empresa_id = 1. Kandiko (2) y Rebisco (3)
 * traen los suyos del bridge Anita de cada empresa. El código Anita (tipom_codigo) se repite
 * entre empresas, así que la unicidad pasa a ser (empresa_id, codigo_anita).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('vianda_tipo_menu', 'empresa_id')) {
            Schema::table('vianda_tipo_menu', function (Blueprint $table) {
                $table->unsignedBigInteger('empresa_id')->default(1)->after('id');
            });
        }

        DB::statement('UPDATE vianda_tipo_menu SET empresa_id = 1 WHERE empresa_id IS NULL OR empresa_id = 0');

        $hasIndex = fn (string $name): bool => MigrationDialectSupport::tieneIndice('vianda_tipo_menu', $name);
        $hasFk = fn (string $name): bool => MigrationDialectSupport::tieneForeignKey('vianda_tipo_menu', $name);

        if ($hasIndex('uq_vianda_tipo_menu_codigo_anita')) {
            Schema::table('vianda_tipo_menu', function (Blueprint $table) {
                $table->dropUnique('uq_vianda_tipo_menu_codigo_anita');
            });
        }

        if (! $hasIndex('uq_vianda_tipo_menu_empresa_codigo')) {
            Schema::table('vianda_tipo_menu', function (Blueprint $table) {
                $table->unique(['empresa_id', 'codigo_anita'], 'uq_vianda_tipo_menu_empresa_codigo');
            });
        }

        if (! $hasFk('fk_vianda_tipo_menu_empresa')) {
            Schema::table('vianda_tipo_menu', function (Blueprint $table) {
                $table->foreign('empresa_id', 'fk_vianda_tipo_menu_empresa')
                    ->references('id')->on('empresa')
                    ->onDelete('restrict')->onUpdate('restrict');
            });
        }
    }

    public function down(): void
    {
        $hasIndex = fn (string $name): bool => MigrationDialectSupport::tieneIndice('vianda_tipo_menu', $name);
        $hasFk = fn (string $name): bool => MigrationDialectSupport::tieneForeignKey('vianda_tipo_menu', $name);

        if ($hasFk('fk_vianda_tipo_menu_empresa')) {
            Schema::table('vianda_tipo_menu', function (Blueprint $table) {
                $table->dropForeign('fk_vianda_tipo_menu_empresa');
            });
        }

        if ($hasIndex('uq_vianda_tipo_menu_empresa_codigo')) {
            Schema::table('vianda_tipo_menu', function (Blueprint $table) {
                $table->dropUnique('uq_vianda_tipo_menu_empresa_codigo');
            });
        }

        if (! $hasIndex('uq_vianda_tipo_menu_codigo_anita')) {
            Schema::table('vianda_tipo_menu', function (Blueprint $table) {
                $table->unique('codigo_anita', 'uq_vianda_tipo_menu_codigo_anita');
            });
        }

        if (Schema::hasColumn('vianda_tipo_menu', 'empresa_id')) {
            Schema::table('vianda_tipo_menu', function (Blueprint $table) {
                $table->dropColumn('empresa_id');
            });
        }
    }
};
