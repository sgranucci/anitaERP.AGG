<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vínculo al comprobante de cumplimiento, entrega parcial, receptor, plazo, tope por centro e historial de UID.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->ampliarSolicitud();
        $this->ampliarItems();
        $this->ampliarArchivo();
        $this->crearSla();
        $this->crearTope();
        $this->crearUid();
    }

    public function down(): void
    {
        Schema::dropIfExists('logistica_uid_historial');
        Schema::dropIfExists('logistica_centrocosto_tope');
        Schema::dropIfExists('logistica_sla');

        if (Schema::hasTable('solicitud_logistica_archivo') && Schema::hasColumn('solicitud_logistica_archivo', 'clase')) {
            Schema::table('solicitud_logistica_archivo', function (Blueprint $table) {
                $table->dropColumn('clase');
            });
        }

        if (Schema::hasTable('solicitud_logistica_item')) {
            Schema::table('solicitud_logistica_item', function (Blueprint $table) {
                foreach (['cantidad_preparada', 'cantidad_entregada'] as $columna) {
                    if (Schema::hasColumn('solicitud_logistica_item', $columna)) {
                        $table->dropColumn($columna);
                    }
                }
            });
        }

        if (Schema::hasTable('solicitud_logistica')) {
            Schema::table('solicitud_logistica', function (Blueprint $table) {
                foreach (['fk_sollog_movstk', 'fk_sollog_tm', 'fk_sollog_req'] as $fk) {
                    try {
                        $table->dropForeign($fk);
                    } catch (\Throwable) {
                    }
                }
            });
            Schema::table('solicitud_logistica', function (Blueprint $table) {
                foreach ([
                    'fecha_compromiso', 'fecha_preparacion', 'fecha_entrega',
                    'receptor_nombre', 'receptor_en',
                    'movimientostock_id', 'transferencia_mercaderia_id', 'requisicion_id',
                ] as $columna) {
                    if (Schema::hasColumn('solicitud_logistica', $columna)) {
                        $table->dropColumn($columna);
                    }
                }
            });
        }
    }

    private function ampliarSolicitud(): void
    {
        if (! Schema::hasTable('solicitud_logistica')) {
            return;
        }

        Schema::table('solicitud_logistica', function (Blueprint $table) {
            if (! Schema::hasColumn('solicitud_logistica', 'fecha_compromiso')) {
                $table->dateTime('fecha_compromiso')->nullable();
            }
            if (! Schema::hasColumn('solicitud_logistica', 'fecha_preparacion')) {
                $table->dateTime('fecha_preparacion')->nullable();
            }
            if (! Schema::hasColumn('solicitud_logistica', 'fecha_entrega')) {
                $table->dateTime('fecha_entrega')->nullable();
            }
            if (! Schema::hasColumn('solicitud_logistica', 'receptor_nombre')) {
                $table->string('receptor_nombre', 120)->nullable();
            }
            if (! Schema::hasColumn('solicitud_logistica', 'receptor_en')) {
                $table->dateTime('receptor_en')->nullable();
            }
            if (! Schema::hasColumn('solicitud_logistica', 'movimientostock_id')) {
                $table->unsignedBigInteger('movimientostock_id')->nullable();
                $table->foreign('movimientostock_id', 'fk_sollog_movstk')->references('id')->on('movimientostock')->nullOnDelete();
            }
            if (! Schema::hasColumn('solicitud_logistica', 'transferencia_mercaderia_id')) {
                $table->unsignedBigInteger('transferencia_mercaderia_id')->nullable();
                $table->foreign('transferencia_mercaderia_id', 'fk_sollog_tm')->references('id')->on('transferencia_mercaderia')->nullOnDelete();
            }
            if (! Schema::hasColumn('solicitud_logistica', 'requisicion_id')) {
                $table->unsignedBigInteger('requisicion_id')->nullable();
                $table->foreign('requisicion_id', 'fk_sollog_req')->references('id')->on('requisicion')->nullOnDelete();
            }
        });
    }

    private function ampliarItems(): void
    {
        if (! Schema::hasTable('solicitud_logistica_item')) {
            return;
        }

        Schema::table('solicitud_logistica_item', function (Blueprint $table) {
            if (! Schema::hasColumn('solicitud_logistica_item', 'cantidad_preparada')) {
                $table->decimal('cantidad_preparada', 14, 4)->default(0);
            }
            if (! Schema::hasColumn('solicitud_logistica_item', 'cantidad_entregada')) {
                $table->decimal('cantidad_entregada', 14, 4)->default(0);
            }
        });
    }

    private function ampliarArchivo(): void
    {
        if (! Schema::hasTable('solicitud_logistica_archivo')) {
            return;
        }
        if (! Schema::hasColumn('solicitud_logistica_archivo', 'clase')) {
            Schema::table('solicitud_logistica_archivo', function (Blueprint $table) {
                $table->string('clase', 20)->default('adjunto');
            });
        }
    }

    private function crearSla(): void
    {
        if (Schema::hasTable('logistica_sla')) {
            return;
        }

        Schema::create('logistica_sla', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('prioridad', 20);
            $table->unsignedInteger('horas_preparacion');
            $table->unsignedInteger('horas_entrega');
            $table->timestamps();
            $table->unique('prioridad', 'uq_logistica_sla_prioridad');
        });

        $ahora = now();
        DB::table('logistica_sla')->insert([
            ['prioridad' => 'Urgente', 'horas_preparacion' => 4, 'horas_entrega' => 8, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['prioridad' => 'Alta', 'horas_preparacion' => 8, 'horas_entrega' => 24, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['prioridad' => 'Media', 'horas_preparacion' => 24, 'horas_entrega' => 48, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['prioridad' => 'Normal', 'horas_preparacion' => 24, 'horas_entrega' => 72, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['prioridad' => 'Baja', 'horas_preparacion' => 72, 'horas_entrega' => 120, 'created_at' => $ahora, 'updated_at' => $ahora],
        ]);
    }

    private function crearTope(): void
    {
        if (Schema::hasTable('logistica_centrocosto_tope')) {
            return;
        }

        Schema::create('logistica_centrocosto_tope', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('centrocosto_id');
            $table->decimal('monto_mensual', 18, 2);
            $table->timestamps();
            $table->unique('centrocosto_id', 'uq_logistica_tope_cc');
            $table->foreign('centrocosto_id', 'fk_logtope_cc')->references('id')->on('centrocosto');
        });
    }

    private function crearUid(): void
    {
        if (Schema::hasTable('logistica_uid_historial')) {
            return;
        }

        Schema::create('logistica_uid_historial', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('uid', 40);
            $table->unsignedBigInteger('solicitud_logistica_id');
            $table->date('fecha');
            $table->string('destino', 180)->nullable();
            $table->unsignedBigInteger('usuario_id');
            $table->timestamps();
            $table->index('uid', 'ix_logistica_uid');
            $table->foreign('solicitud_logistica_id', 'fk_loguid_sol')->references('id')->on('solicitud_logistica');
            $table->foreign('usuario_id', 'fk_loguid_usuario')->references('id')->on('usuario');
        });
    }
};
