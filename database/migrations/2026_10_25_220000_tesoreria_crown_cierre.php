<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cierre de una caja de la jornada. Distinto de la rendición.
 * La historia de estados queda en tesoreria_crown_cierre_evento.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        if (! Schema::hasTable('tesoreria_crown_cierre')) {
            Schema::create('tesoreria_crown_cierre', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sala_id')->constrained('sala');
                $table->foreignId('empresa_id')->constrained('empresa');
                $table->foreignId('jornada_id')->constrained('tesoreria_crown_jornada');
                $table->foreignId('asignacion_id')->constrained('tesoreria_crown_asignacion');
                $table->unsignedInteger('numero');
                $table->decimal('efectivo_habilitado', 14, 2)->default(0);
                $table->decimal('valores_rendidos', 14, 2)->default(0);
                $table->decimal('resultado', 14, 2)->default(0);
                $table->decimal('diferencia', 14, 2)->default(0);
                $table->char('estado', 1);
                $table->string('observacion', 500)->nullable();
                $table->foreignId('tesoreria_usuario_id')->nullable()->constrained('usuario');
                $table->dateTime('tesoreria_at')->nullable();
                $table->dateTime('cajero_at')->nullable();
                $table->foreignId('auditor_usuario_id')->nullable()->constrained('usuario');
                $table->dateTime('auditor_at')->nullable();
                $table->timestamps();
                $table->unique('asignacion_id', 'tc_cierre_asignacion_uq');
                $table->unique(['sala_id', 'numero'], 'tc_cierre_sala_numero_uq');
            });
        }
        if (! Schema::hasTable('tesoreria_crown_cierre_evento')) {
            Schema::create('tesoreria_crown_cierre_evento', function (Blueprint $table) {
                $table->id();
                $table->foreignId('cierre_id')->constrained('tesoreria_crown_cierre');
                $table->char('estado', 1);
                $table->foreignId('usuario_id')->nullable()->constrained('usuario');
                $table->string('detalle', 500)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        Schema::dropIfExists('tesoreria_crown_cierre_evento');
        Schema::dropIfExists('tesoreria_crown_cierre');
    }
};
