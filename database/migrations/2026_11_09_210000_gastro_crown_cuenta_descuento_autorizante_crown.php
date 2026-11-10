<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos de F8 / carga_dto_autorizante en la cuenta Crown:
 * tipo (invitados/staff/descuento), autorizante, centro de costo y cliente interno del dto.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        if (! Schema::hasTable('gastro_crown_cuenta')) {
            return;
        }

        Schema::table('gastro_crown_cuenta', function (Blueprint $table) {
            if (! Schema::hasColumn('gastro_crown_cuenta', 'tipo_descuento')) {
                $table->string('tipo_descuento', 20)->nullable()->after('detalle_descuento');
            }
            if (! Schema::hasColumn('gastro_crown_cuenta', 'cliente_interno_descuento_id')) {
                $table->unsignedBigInteger('cliente_interno_descuento_id')->nullable()->after('tipo_descuento');
            }
            if (! Schema::hasColumn('gastro_crown_cuenta', 'autorizante_usuario_id')) {
                $table->unsignedBigInteger('autorizante_usuario_id')->nullable()->after('cliente_interno_descuento_id');
            }
            if (! Schema::hasColumn('gastro_crown_cuenta', 'autorizante_nombre')) {
                $table->string('autorizante_nombre', 60)->nullable()->after('autorizante_usuario_id');
            }
            if (! Schema::hasColumn('gastro_crown_cuenta', 'centrocosto_id')) {
                $table->unsignedBigInteger('centrocosto_id')->nullable()->after('autorizante_nombre');
            }
        });
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        if (! Schema::hasTable('gastro_crown_cuenta')) {
            return;
        }

        Schema::table('gastro_crown_cuenta', function (Blueprint $table) {
            foreach ([
                'tipo_descuento',
                'cliente_interno_descuento_id',
                'autorizante_usuario_id',
                'autorizante_nombre',
                'centrocosto_id',
            ] as $col) {
                if (Schema::hasColumn('gastro_crown_cuenta', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
