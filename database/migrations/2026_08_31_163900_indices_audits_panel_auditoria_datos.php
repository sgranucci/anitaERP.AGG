<?php

use App\Support\Database\MigrationDialectSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices para el panel de cambios de datos (audits ~millones de filas).
 * Sin ellos, filtro por usuario+fecha hace full scan + filesort.
 *
 * Nota: (auditable_type, created_at) completo falló en este servidor (InnoDB 1030
 * en /tmp chico); el índice morph existente ya cubre filtro por modelo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audits')) {
            return;
        }

        if (! MigrationDialectSupport::tieneIndice('audits', 'idx_audits_user_created')) {
            Schema::table('audits', function (Blueprint $table) {
                $table->index(['user_id', 'created_at'], 'idx_audits_user_created');
            });
        }

        if (! MigrationDialectSupport::tieneIndice('audits', 'idx_audits_created')) {
            Schema::table('audits', function (Blueprint $table) {
                $table->index(['created_at'], 'idx_audits_created');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('audits')) {
            return;
        }

        if (MigrationDialectSupport::tieneIndice('audits', 'idx_audits_user_created')) {
            Schema::table('audits', function (Blueprint $table) {
                $table->dropIndex('idx_audits_user_created');
            });
        }

        if (MigrationDialectSupport::tieneIndice('audits', 'idx_audits_created')) {
            Schema::table('audits', function (Blueprint $table) {
                $table->dropIndex('idx_audits_created');
            });
        }
    }
};
