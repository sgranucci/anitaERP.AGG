<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tecnico_laboratorio')) {
            Schema::create('tecnico_laboratorio', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('empresa_id');
                $table->foreign('empresa_id', 'fk_tecnico_laboratorio_empresa')
                    ->references('id')->on('empresa')->onDelete('restrict')->onUpdate('restrict');
                $table->string('nombre', 255);
                $table->unsignedInteger('legajo')->nullable();
                $table->string('activo', 1)->default('S');
                $table->timestamps();
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_spanish_ci';
            });
        }

        if (Schema::hasTable('requisicion_sala_articulo')) {
            Schema::table('requisicion_sala_articulo', function (Blueprint $table) {
                if (! Schema::hasColumn('requisicion_sala_articulo', 'cantidadentregada')) {
                    $table->decimal('cantidadentregada', 22, 4)->default(0)->after('cantidad');
                }
                if (! Schema::hasColumn('requisicion_sala_articulo', 'fecha_entrega')) {
                    $table->date('fecha_entrega')->nullable()->after('estado');
                }
                if (! Schema::hasColumn('requisicion_sala_articulo', 'tecnico_laboratorio_id')) {
                    $table->unsignedBigInteger('tecnico_laboratorio_id')->nullable()->after('nombreresponsable');
                    $table->foreign('tecnico_laboratorio_id', 'fk_req_sala_art_tecnico_lab')
                        ->references('id')->on('tecnico_laboratorio')->onDelete('set null')->onUpdate('restrict');
                }
                if (! Schema::hasColumn('requisicion_sala_articulo', 'deposito_origen_id')) {
                    $table->unsignedBigInteger('deposito_origen_id')->nullable()->after('tecnico_laboratorio_id');
                    $table->foreign('deposito_origen_id', 'fk_req_sala_art_deposito_origen')
                        ->references('id')->on('depmae')->onDelete('restrict')->onUpdate('restrict');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('requisicion_sala_articulo')) {
            Schema::table('requisicion_sala_articulo', function (Blueprint $table) {
                if (Schema::hasColumn('requisicion_sala_articulo', 'deposito_origen_id')) {
                    $table->dropForeign('fk_req_sala_art_deposito_origen');
                    $table->dropColumn('deposito_origen_id');
                }
                if (Schema::hasColumn('requisicion_sala_articulo', 'tecnico_laboratorio_id')) {
                    $table->dropForeign('fk_req_sala_art_tecnico_lab');
                    $table->dropColumn('tecnico_laboratorio_id');
                }
                if (Schema::hasColumn('requisicion_sala_articulo', 'fecha_entrega')) {
                    $table->dropColumn('fecha_entrega');
                }
                if (Schema::hasColumn('requisicion_sala_articulo', 'cantidadentregada')) {
                    $table->dropColumn('cantidadentregada');
                }
            });
        }

        Schema::dropIfExists('tecnico_laboratorio');
    }
};
