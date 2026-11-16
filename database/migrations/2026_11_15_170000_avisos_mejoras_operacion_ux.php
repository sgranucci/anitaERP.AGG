<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mejoras operación/UX/producto del módulo avisos:
 * SLA por fuente, empresa en user_task (KPI), sin SoftDeletes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_task_sla_fuente')) {
            Schema::create('user_task_sla_fuente', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('fuente', 60);
                $table->unsignedSmallInteger('sla_horas')->default(48);
                $table->timestamps();
                $table->unique('fuente', 'uk_user_task_sla_fuente');
            });
        }

        if (Schema::hasTable('user_task') && ! Schema::hasColumn('user_task', 'empresa_id')) {
            Schema::table('user_task', function (Blueprint $table) {
                $table->unsignedBigInteger('empresa_id')->nullable()->after('usuario_id');
                $table->index(['empresa_id', 'estado'], 'idx_user_task_empresa_estado');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('user_task') && Schema::hasColumn('user_task', 'empresa_id')) {
            Schema::table('user_task', function (Blueprint $table) {
                $table->dropIndex('idx_user_task_empresa_estado');
                $table->dropColumn('empresa_id');
            });
        }
        Schema::dropIfExists('user_task_sla_fuente');
    }
};
