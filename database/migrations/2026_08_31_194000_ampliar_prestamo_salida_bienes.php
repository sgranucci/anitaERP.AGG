<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Amplía el circuito de préstamos a «Salida de bienes»:
 * destinatario depósito / usuario / externo, ítems sin artículo,
 * y campos premium (serie, condición, prioridad).
 *
 * Sin gate de entorno: corre en AGG, Bierzo y demás instalaciones.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('prestamo')) {
            Schema::table('prestamo', function (Blueprint $table) {
                if (! Schema::hasColumn('prestamo', 'tipo')) {
                    $table->string('tipo', 30)->default('PRESTAMO')->after('codigo');
                }
                if (! Schema::hasColumn('prestamo', 'destinatario_tipo')) {
                    $table->string('destinatario_tipo', 20)->default('DEPOSITO')->after('tipo');
                }
                if (! Schema::hasColumn('prestamo', 'destinatario_usuario_id')) {
                    $table->unsignedBigInteger('destinatario_usuario_id')->nullable()->after('deposito_destino_id');
                }
                if (! Schema::hasColumn('prestamo', 'externo_nombre')) {
                    $table->string('externo_nombre', 180)->nullable()->after('destinatario_usuario_id');
                }
                if (! Schema::hasColumn('prestamo', 'externo_documento')) {
                    $table->string('externo_documento', 40)->nullable()->after('externo_nombre');
                }
                if (! Schema::hasColumn('prestamo', 'externo_telefono')) {
                    $table->string('externo_telefono', 60)->nullable()->after('externo_documento');
                }
                if (! Schema::hasColumn('prestamo', 'externo_email')) {
                    $table->string('externo_email', 120)->nullable()->after('externo_telefono');
                }
                if (! Schema::hasColumn('prestamo', 'externo_empresa')) {
                    $table->string('externo_empresa', 180)->nullable()->after('externo_email');
                }
                if (! Schema::hasColumn('prestamo', 'espera_devolucion')) {
                    $table->boolean('espera_devolucion')->default(true)->after('externo_empresa');
                }
                if (! Schema::hasColumn('prestamo', 'prioridad')) {
                    $table->string('prioridad', 20)->default('NORMAL')->after('espera_devolucion');
                }
            });

            // deposito_destino_id nullable (destino usuario/externo)
            try {
                Schema::table('prestamo', function (Blueprint $table) {
                    $table->dropForeign('fk_prestamo_dep_destino');
                });
            } catch (\Throwable $e) {
                // FK puede tener otro nombre en algún entorno.
            }

            Schema::table('prestamo', function (Blueprint $table) {
                $table->unsignedBigInteger('deposito_destino_id')->nullable()->change();
            });

            try {
                Schema::table('prestamo', function (Blueprint $table) {
                    $table->foreign('deposito_destino_id', 'fk_prestamo_dep_destino')
                        ->references('id')->on('depmae')
                        ->onDelete('restrict')->onUpdate('restrict');
                });
            } catch (\Throwable $e) {
                // Ya existe.
            }

            // fecha_devolucion_prometida nullable cuando no espera devolución
            Schema::table('prestamo', function (Blueprint $table) {
                $table->date('fecha_devolucion_prometida')->nullable()->change();
            });

            if (Schema::hasColumn('prestamo', 'destinatario_usuario_id')) {
                try {
                    Schema::table('prestamo', function (Blueprint $table) {
                        $table->foreign('destinatario_usuario_id', 'fk_prestamo_dest_usuario')
                            ->references('id')->on('usuario')
                            ->onDelete('restrict')->onUpdate('restrict');
                    });
                } catch (\Throwable $e) {
                    // Ya existe.
                }
            }

            try {
                Schema::table('prestamo', function (Blueprint $table) {
                    $table->index('destinatario_tipo', 'ix_prestamo_destinatario_tipo');
                });
            } catch (\Throwable $e) {
                // índice ya existe
            }
            try {
                Schema::table('prestamo', function (Blueprint $table) {
                    $table->index('tipo', 'ix_prestamo_tipo');
                });
            } catch (\Throwable $e) {
                // índice ya existe
            }
        }

        if (Schema::hasTable('prestamo_item')) {
            try {
                Schema::table('prestamo_item', function (Blueprint $table) {
                    $table->dropForeign('fk_prestamoitem_articulo');
                });
            } catch (\Throwable $e) {
                // ignore
            }

            Schema::table('prestamo_item', function (Blueprint $table) {
                $table->unsignedBigInteger('articulo_id')->nullable()->change();
            });

            try {
                Schema::table('prestamo_item', function (Blueprint $table) {
                    $table->foreign('articulo_id', 'fk_prestamoitem_articulo')
                        ->references('id')->on('articulo')
                        ->onDelete('restrict')->onUpdate('restrict');
                });
            } catch (\Throwable $e) {
                // ignore
            }

            Schema::table('prestamo_item', function (Blueprint $table) {
                if (! Schema::hasColumn('prestamo_item', 'descripcion')) {
                    $table->string('descripcion', 255)->nullable()->after('articulo_id');
                }
                if (! Schema::hasColumn('prestamo_item', 'nro_serie')) {
                    $table->string('nro_serie', 80)->nullable()->after('descripcion');
                }
                if (! Schema::hasColumn('prestamo_item', 'condicion_salida')) {
                    $table->string('condicion_salida', 20)->nullable()->after('nro_serie');
                }
                if (! Schema::hasColumn('prestamo_item', 'condicion_devolucion')) {
                    $table->string('condicion_devolucion', 20)->nullable()->after('condicion_salida');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('prestamo_item')) {
            Schema::table('prestamo_item', function (Blueprint $table) {
                foreach (['descripcion', 'nro_serie', 'condicion_salida', 'condicion_devolucion'] as $col) {
                    if (Schema::hasColumn('prestamo_item', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('prestamo')) {
            try {
                Schema::table('prestamo', function (Blueprint $table) {
                    $table->dropForeign('fk_prestamo_dest_usuario');
                });
            } catch (\Throwable $e) {
                // ignore
            }

            Schema::table('prestamo', function (Blueprint $table) {
                foreach ([
                    'tipo', 'destinatario_tipo', 'destinatario_usuario_id',
                    'externo_nombre', 'externo_documento', 'externo_telefono',
                    'externo_email', 'externo_empresa', 'espera_devolucion', 'prioridad',
                ] as $col) {
                    if (Schema::hasColumn('prestamo', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
