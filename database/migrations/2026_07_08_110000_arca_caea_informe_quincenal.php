<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('arca_caea', function (Blueprint $table) {
            if (! Schema::hasColumn('arca_caea', 'informe_estado')) {
                $table->string('informe_estado', 20)->nullable()->after('observaciones');
            }
            if (! Schema::hasColumn('arca_caea', 'informe_resumen')) {
                $table->json('informe_resumen')->nullable()->after('informe_estado');
            }
            if (! Schema::hasColumn('arca_caea', 'informe_procesado_at')) {
                $table->timestamp('informe_procesado_at')->nullable()->after('informe_resumen');
            }
            if (! Schema::hasColumn('arca_caea', 'informe_usuario_id')) {
                $table->unsignedBigInteger('informe_usuario_id')->nullable()->after('informe_procesado_at');
            }
        });

        Schema::table('venta', function (Blueprint $table) {
            if (! Schema::hasColumn('venta', 'caea_informado_estado')) {
                $table->string('caea_informado_estado', 20)->nullable()->after('fechavencimientocae');
            }
            if (! Schema::hasColumn('venta', 'caea_informado_at')) {
                $table->timestamp('caea_informado_at')->nullable()->after('caea_informado_estado');
            }
            if (! Schema::hasColumn('venta', 'caea_informado_codigo_error')) {
                $table->string('caea_informado_codigo_error', 20)->nullable()->after('caea_informado_at');
            }
            if (! Schema::hasColumn('venta', 'caea_informado_mensaje')) {
                $table->text('caea_informado_mensaje')->nullable()->after('caea_informado_codigo_error');
            }
        });

        Schema::table('venta', function (Blueprint $table) {
            if (Schema::hasColumn('venta', 'caea_informado_estado')) {
                $table->index(['caea_informado_estado', 'fecha'], 'venta_caea_informado_estado_fecha_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('venta', function (Blueprint $table) {
            if (Schema::hasColumn('venta', 'caea_informado_estado')) {
                $table->dropIndex('venta_caea_informado_estado_fecha_idx');
            }
            $table->dropColumn([
                'caea_informado_estado',
                'caea_informado_at',
                'caea_informado_codigo_error',
                'caea_informado_mensaje',
            ]);
        });

        Schema::table('arca_caea', function (Blueprint $table) {
            $table->dropColumn([
                'informe_estado',
                'informe_resumen',
                'informe_procesado_at',
                'informe_usuario_id',
            ]);
        });
    }
};
