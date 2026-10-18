<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crown: salas asignadas al usuario (paridad usuario_empresa) + sala_id en descuentos gastronomía.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('usuario_sala')) {
            Schema::create('usuario_sala', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('usuario_id');
                $table->unsignedBigInteger('sala_id');
                $table->timestamps();

                $table->foreign('usuario_id', 'fk_usuario_sala_usuario')
                    ->references('id')->on('usuario')->cascadeOnDelete();
                $table->foreign('sala_id', 'fk_usuario_sala_sala')
                    ->references('id')->on('sala')->cascadeOnDelete();
                $table->unique(['usuario_id', 'sala_id'], 'uk_usuario_sala');
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_spanish_ci';
            });
        }

        if (Schema::hasTable('descuento_gastronomia')
            && ! Schema::hasColumn('descuento_gastronomia', 'sala_id')) {
            Schema::table('descuento_gastronomia', function (Blueprint $table) {
                $table->unsignedBigInteger('sala_id')->nullable()->after('cliente_id');
                $table->foreign('sala_id', 'fk_dto_gastro_sala')
                    ->references('id')->on('sala')->nullOnDelete();
                $table->index(['sala_id', 'codigo'], 'idx_dto_gastro_sala_codigo');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('descuento_gastronomia')
            && Schema::hasColumn('descuento_gastronomia', 'sala_id')) {
            Schema::table('descuento_gastronomia', function (Blueprint $table) {
                $table->dropForeign('fk_dto_gastro_sala');
                $table->dropIndex('idx_dto_gastro_sala_codigo');
                $table->dropColumn('sala_id');
            });
        }

        Schema::dropIfExists('usuario_sala');
    }
};
