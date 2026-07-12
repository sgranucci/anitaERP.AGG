<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Empresa dueña del usuario de vianda.
 *
 * Los usuarios ya importados son de Biyemas → empresa_id = 1. Kandiko (2) y Rebisco (3)
 * se traen del bridge Anita de cada empresa. El código Anita (usuv_usuario) se repite
 * entre empresas, así que la unicidad pasa a ser (empresa_id, codigo_usuario).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('vianda_usuario', 'empresa_id')) {
            Schema::table('vianda_usuario', function (Blueprint $table) {
                $table->unsignedBigInteger('empresa_id')->default(1)->after('codigo_usuario');
            });
        }

        // Backfill: todo lo existente es Biyemas.
        DB::statement('UPDATE vianda_usuario SET empresa_id = 1 WHERE empresa_id IS NULL OR empresa_id = 0');

        $dbName = DB::getDatabaseName();
        $hasIndex = fn (string $name): bool => DB::table('information_schema.statistics')
            ->where('table_schema', $dbName)
            ->where('table_name', 'vianda_usuario')
            ->where('index_name', $name)
            ->exists();
        $hasFk = fn (string $name): bool => DB::table('information_schema.table_constraints')
            ->where('constraint_schema', $dbName)
            ->where('table_name', 'vianda_usuario')
            ->where('constraint_name', $name)
            ->where('constraint_type', 'FOREIGN KEY')
            ->exists();

        // Unicidad por empresa: dropear el índice único global viejo y crear el compuesto.
        if ($hasIndex('uq_vianda_usuario_codigo')) {
            Schema::table('vianda_usuario', function (Blueprint $table) {
                $table->dropUnique('uq_vianda_usuario_codigo');
            });
        }

        if (! $hasIndex('uq_vianda_usuario_empresa_codigo')) {
            Schema::table('vianda_usuario', function (Blueprint $table) {
                $table->unique(['empresa_id', 'codigo_usuario'], 'uq_vianda_usuario_empresa_codigo');
            });
        }

        // El índice único compuesto (empresa_id primero) satisface el índice requerido por la FK.
        if (! $hasFk('fk_vianda_usuario_empresa')) {
            Schema::table('vianda_usuario', function (Blueprint $table) {
                $table->foreign('empresa_id', 'fk_vianda_usuario_empresa')
                    ->references('id')->on('empresa')
                    ->onDelete('restrict')->onUpdate('restrict');
            });
        }
    }

    public function down(): void
    {
        $dbName = DB::getDatabaseName();
        $hasIndex = fn (string $name): bool => DB::table('information_schema.statistics')
            ->where('table_schema', $dbName)
            ->where('table_name', 'vianda_usuario')
            ->where('index_name', $name)
            ->exists();
        $hasFk = fn (string $name): bool => DB::table('information_schema.table_constraints')
            ->where('constraint_schema', $dbName)
            ->where('table_name', 'vianda_usuario')
            ->where('constraint_name', $name)
            ->where('constraint_type', 'FOREIGN KEY')
            ->exists();

        if ($hasFk('fk_vianda_usuario_empresa')) {
            Schema::table('vianda_usuario', function (Blueprint $table) {
                $table->dropForeign('fk_vianda_usuario_empresa');
            });
        }

        if ($hasIndex('uq_vianda_usuario_empresa_codigo')) {
            Schema::table('vianda_usuario', function (Blueprint $table) {
                $table->dropUnique('uq_vianda_usuario_empresa_codigo');
            });
        }

        if (! $hasIndex('uq_vianda_usuario_codigo')) {
            Schema::table('vianda_usuario', function (Blueprint $table) {
                $table->unique('codigo_usuario', 'uq_vianda_usuario_codigo');
            });
        }

        if (Schema::hasColumn('vianda_usuario', 'empresa_id')) {
            Schema::table('vianda_usuario', function (Blueprint $table) {
                $table->dropColumn('empresa_id');
            });
        }
    }
};
