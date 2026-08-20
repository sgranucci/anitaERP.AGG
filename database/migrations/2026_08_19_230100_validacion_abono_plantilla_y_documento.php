<?php

use App\Support\Compras\ContratoValidacionAbonoEstados;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Plantillas de preguntas y documento de validación de abono (COM o factura).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('validacion_abono_plantilla')) {
            Schema::create('validacion_abono_plantilla', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('codigo', 40)->unique();
                $table->string('nombre', 120);
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('validacion_abono_plantilla_pregunta')) {
            Schema::create('validacion_abono_plantilla_pregunta', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('validacion_abono_plantilla_id');
                $table->string('codigo', 40);
                $table->unsignedTinyInteger('orden')->default(1);
                $table->string('enunciado', 255);
                $table->string('comentario_si_valor', 8)->nullable()
                    ->comment('si | no: valor que obliga comentario');
                $table->boolean('es_tickets')->default(false);
                $table->timestamps();

                $table->unique(
                    ['validacion_abono_plantilla_id', 'codigo'],
                    'uq_val_abono_preg_codigo'
                );
                $table->foreign('validacion_abono_plantilla_id', 'fk_val_abono_preg_plantilla')
                    ->references('id')->on('validacion_abono_plantilla')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('contrato_validacion_abono')) {
            Schema::create('contrato_validacion_abono', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('ordencompra_id');
                $table->unsignedBigInteger('recepcion_proveedor_id')->nullable();
                $table->unsignedBigInteger('comprobante_proveedor_id')->nullable();
                $table->unsignedBigInteger('plantilla_id');
                $table->string('estado', 20)->default('PENDIENTE');
                $table->string('periodo_modalidad', 20)->nullable();
                $table->date('periodo_desde')->nullable();
                $table->date('periodo_hasta')->nullable();
                $table->unsignedInteger('ingresos_informados')->default(0);
                $table->json('snapshot_ingresos_json')->nullable();
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->timestamp('confirmado_at')->nullable();
                $table->timestamp('aviso_pendiente_enviado_at')->nullable();
                $table->timestamps();

                $table->unique('recepcion_proveedor_id', 'uq_val_abono_recepcion');
                $table->unique('comprobante_proveedor_id', 'uq_val_abono_comprobante');
                $table->index(['ordencompra_id', 'estado'], 'idx_val_abono_oc_estado');

                $table->foreign('ordencompra_id', 'fk_val_abono_oc')
                    ->references('id')->on('ordencompra')->onDelete('cascade');
                $table->foreign('plantilla_id', 'fk_val_abono_plantilla')
                    ->references('id')->on('validacion_abono_plantilla')->onDelete('restrict');
            });
        }

        if (! Schema::hasTable('contrato_validacion_abono_respuesta')) {
            Schema::create('contrato_validacion_abono_respuesta', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('contrato_validacion_abono_id');
                $table->unsignedBigInteger('pregunta_id');
                $table->string('valor', 8);
                $table->text('comentario')->nullable();
                $table->timestamps();

                $table->unique(
                    ['contrato_validacion_abono_id', 'pregunta_id'],
                    'uq_val_abono_resp_preg'
                );
                $table->foreign('contrato_validacion_abono_id', 'fk_val_abono_resp')
                    ->references('id')->on('contrato_validacion_abono')->onDelete('cascade');
                $table->foreign('pregunta_id', 'fk_val_abono_resp_preg')
                    ->references('id')->on('validacion_abono_plantilla_pregunta')->onDelete('restrict');
            });
        }

        $this->sembrarPlantillaEstandar();
        $this->vincularFkPlantillaOc();
    }

    public function down(): void
    {
        if (Schema::hasTable('ordencompra') && Schema::hasColumn('ordencompra', 'contrato_validacion_plantilla_id')) {
            try {
                Schema::table('ordencompra', function (Blueprint $table) {
                    $table->dropForeign('fk_oc_contrato_val_plantilla');
                });
            } catch (\Throwable) {
            }
        }

        Schema::dropIfExists('contrato_validacion_abono_respuesta');
        Schema::dropIfExists('contrato_validacion_abono');
        Schema::dropIfExists('validacion_abono_plantilla_pregunta');
        Schema::dropIfExists('validacion_abono_plantilla');
    }

    private function sembrarPlantillaEstandar(): void
    {
        $now = now();
        $plantillaId = (int) (DB::table('validacion_abono_plantilla')->where('codigo', 'estandar')->value('id') ?? 0);
        if ($plantillaId === 0) {
            $plantillaId = (int) DB::table('validacion_abono_plantilla')->insertGetId([
                'codigo' => 'estandar',
                'nombre' => 'Validación de Abono — Estándar',
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $preguntas = [
            [
                'codigo' => 'servicio_prestado',
                'orden' => 1,
                'enunciado' => '¿El servicio se prestó efectivamente durante el período?',
                'comentario_si_valor' => 'no',
                'es_tickets' => false,
            ],
            [
                'codigo' => 'conformidad_area',
                'orden' => 2,
                'enunciado' => '¿El área receptora dio conformidad?',
                'comentario_si_valor' => 'no',
                'es_tickets' => false,
            ],
            [
                'codigo' => ContratoValidacionAbonoEstados::CODIGO_TICKETS,
                'orden' => 3,
                'enunciado' => '¿Hay tickets de ingreso del proveedor registrados en el período?',
                'comentario_si_valor' => 'no',
                'es_tickets' => true,
            ],
            [
                'codigo' => 'monto_coincide',
                'orden' => 4,
                'enunciado' => '¿El monto facturado coincide con el contrato?',
                'comentario_si_valor' => 'no',
                'es_tickets' => false,
            ],
            [
                'codigo' => 'reclamos_pendientes',
                'orden' => 5,
                'enunciado' => '¿Hay reclamos o incidentes pendientes con este proveedor?',
                'comentario_si_valor' => 'si',
                'es_tickets' => false,
            ],
        ];

        foreach ($preguntas as $pregunta) {
            $existe = DB::table('validacion_abono_plantilla_pregunta')
                ->where('validacion_abono_plantilla_id', $plantillaId)
                ->where('codigo', $pregunta['codigo'])
                ->exists();
            if ($existe) {
                continue;
            }
            DB::table('validacion_abono_plantilla_pregunta')->insert([
                'validacion_abono_plantilla_id' => $plantillaId,
                'codigo' => $pregunta['codigo'],
                'orden' => $pregunta['orden'],
                'enunciado' => $pregunta['enunciado'],
                'comentario_si_valor' => $pregunta['comentario_si_valor'],
                'es_tickets' => $pregunta['es_tickets'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function vincularFkPlantillaOc(): void
    {
        if (! Schema::hasTable('ordencompra')
            || ! Schema::hasColumn('ordencompra', 'contrato_validacion_plantilla_id')
        ) {
            return;
        }
        try {
            Schema::table('ordencompra', function (Blueprint $table) {
                $table->foreign('contrato_validacion_plantilla_id', 'fk_oc_contrato_val_plantilla')
                    ->references('id')->on('validacion_abono_plantilla')
                    ->onDelete('set null');
            });
        } catch (\Throwable) {
        }
    }
};
