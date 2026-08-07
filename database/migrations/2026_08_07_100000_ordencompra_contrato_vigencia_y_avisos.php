<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gestión de contratos / OC abiertas (abonos, honorarios, servicios).
 *
 * Agrega a la cabecera de OC la ventana de vigencia, el tope contratado y los datos
 * de renovación automática, y crea el log idempotente de avisos de vencimiento para
 * que el cron no reenvíe el mismo umbral todos los días.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ordencompra')) {
            Schema::table('ordencompra', function (Blueprint $table) {
                if (! Schema::hasColumn('ordencompra', 'es_contrato')) {
                    $table->boolean('es_contrato')->default(false)->after('tratamiento')
                        ->comment('OC abierta / contrato: abono, honorarios, servicio recurrente');
                }
                if (! Schema::hasColumn('ordencompra', 'contrato_vigencia_desde')) {
                    $table->date('contrato_vigencia_desde')->nullable()->after('es_contrato');
                }
                if (! Schema::hasColumn('ordencompra', 'contrato_vigencia_hasta')) {
                    $table->date('contrato_vigencia_hasta')->nullable()->after('contrato_vigencia_desde');
                }
                if (! Schema::hasColumn('ordencompra', 'contrato_monto_tope')) {
                    $table->decimal('contrato_monto_tope', 18, 4)->nullable()->after('contrato_vigencia_hasta')
                        ->comment('Monto máximo contratado; se compara contra lo facturado');
                }
                if (! Schema::hasColumn('ordencompra', 'contrato_moneda_id')) {
                    $table->unsignedBigInteger('contrato_moneda_id')->nullable()->after('contrato_monto_tope');
                }
                if (! Schema::hasColumn('ordencompra', 'contrato_auto_renovable')) {
                    $table->boolean('contrato_auto_renovable')->default(false)->after('contrato_moneda_id');
                }
                if (! Schema::hasColumn('ordencompra', 'contrato_dias_preaviso')) {
                    $table->unsignedSmallInteger('contrato_dias_preaviso')->nullable()->after('contrato_auto_renovable')
                        ->comment('Días de preaviso para notificar la no renovación');
                }
                if (! Schema::hasColumn('ordencompra', 'contrato_dias_aviso')) {
                    $table->string('contrato_dias_aviso', 60)->nullable()->after('contrato_dias_preaviso')
                        ->comment('Umbrales propios en días, separados por coma (ej. 60,30,15). Vacío = default de config');
                }
                if (! Schema::hasColumn('ordencompra', 'contrato_responsable_id')) {
                    $table->unsignedBigInteger('contrato_responsable_id')->nullable()->after('contrato_dias_aviso')
                        ->comment('Usuario responsable del seguimiento del contrato');
                }
            });

            $this->agregarForeign('ordencompra', 'contrato_moneda_id', 'moneda', 'fk_ordencompra_contrato_moneda');
            $this->agregarForeign('ordencompra', 'contrato_responsable_id', 'usuario', 'fk_ordencompra_contrato_responsable');

            try {
                Schema::table('ordencompra', function (Blueprint $table) {
                    $table->index(['es_contrato', 'contrato_vigencia_hasta'], 'idx_ordencompra_contrato_vigencia');
                });
            } catch (\Throwable) {
                // Ya existía.
            }
        }

        if (! Schema::hasTable('ordencompra_contrato_aviso')) {
            Schema::create('ordencompra_contrato_aviso', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('ordencompra_id');
                $table->string('tipo_aviso', 20)->comment('VIGENCIA | PREAVISO | CONSUMO | VENCIDO');
                $table->unsignedSmallInteger('umbral')->default(0)
                    ->comment('Días de anticipación o porcentaje de consumo según tipo_aviso');
                $table->string('clave', 120)
                    ->comment('Idempotencia: tipo|fecha_referencia|umbral. Si cambia la vigencia el aviso vuelve a dispararse');
                $table->date('fecha_referencia')->nullable();
                $table->decimal('monto_consumido', 18, 4)->nullable();
                $table->decimal('porcentaje_consumido', 8, 4)->nullable();
                $table->text('destinatarios')->nullable();
                $table->timestamp('enviado_at')->nullable();
                $table->timestamps();

                $table->unique(['ordencompra_id', 'clave'], 'uq_oc_contrato_aviso_clave');
                $table->index(['tipo_aviso', 'enviado_at'], 'idx_oc_contrato_aviso_tipo');
                $table->foreign('ordencompra_id', 'fk_oc_contrato_aviso_oc')
                    ->references('id')->on('ordencompra')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ordencompra_contrato_aviso');

        if (! Schema::hasTable('ordencompra')) {
            return;
        }

        $this->quitarForeign('ordencompra', 'fk_ordencompra_contrato_moneda');
        $this->quitarForeign('ordencompra', 'fk_ordencompra_contrato_responsable');

        try {
            Schema::table('ordencompra', function (Blueprint $table) {
                $table->dropIndex('idx_ordencompra_contrato_vigencia');
            });
        } catch (\Throwable) {
            // No existía.
        }

        $columnas = [
            'contrato_responsable_id',
            'contrato_dias_aviso',
            'contrato_dias_preaviso',
            'contrato_auto_renovable',
            'contrato_moneda_id',
            'contrato_monto_tope',
            'contrato_vigencia_hasta',
            'contrato_vigencia_desde',
            'es_contrato',
        ];

        foreach ($columnas as $columna) {
            if (Schema::hasColumn('ordencompra', $columna)) {
                Schema::table('ordencompra', function (Blueprint $table) use ($columna) {
                    $table->dropColumn($columna);
                });
            }
        }
    }

    private function agregarForeign(string $tabla, string $columna, string $tablaReferida, string $nombre): void
    {
        if (! Schema::hasColumn($tabla, $columna) || ! Schema::hasTable($tablaReferida)) {
            return;
        }

        try {
            Schema::table($tabla, function (Blueprint $table) use ($columna, $tablaReferida, $nombre) {
                $table->foreign($columna, $nombre)
                    ->references('id')->on($tablaReferida)
                    ->onDelete('set null')->onUpdate('restrict');
            });
        } catch (\Throwable) {
            // La FK ya existe o el motor no la admite: la columna igual queda utilizable.
        }
    }

    private function quitarForeign(string $tabla, string $nombre): void
    {
        try {
            Schema::table($tabla, function (Blueprint $table) use ($nombre) {
                $table->dropForeign($nombre);
            });
        } catch (\Throwable) {
            // No existía.
        }
    }
};
