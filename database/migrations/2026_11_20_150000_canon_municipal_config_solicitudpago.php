<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Datos de solicitud de pago por empresa en config de canon municipal.
 * Seed solo BSA (empresa_id=1); KSA/RSA se completan desde la pantalla.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('canon_municipal_config')) {
            return;
        }

        Schema::table('canon_municipal_config', function (Blueprint $table) {
            if (! Schema::hasColumn('canon_municipal_config', 'proveedor_id')) {
                $table->unsignedBigInteger('proveedor_id')->nullable()->after('activo');
            }
            if (! Schema::hasColumn('canon_municipal_config', 'sector_solicitudpago_id')) {
                $table->unsignedBigInteger('sector_solicitudpago_id')->nullable()->after('proveedor_id');
            }
            if (! Schema::hasColumn('canon_municipal_config', 'concepto_solicitudpago_id')) {
                $table->unsignedBigInteger('concepto_solicitudpago_id')->nullable()->after('sector_solicitudpago_id');
            }
            if (! Schema::hasColumn('canon_municipal_config', 'formapagosol_id')) {
                $table->unsignedBigInteger('formapagosol_id')->nullable()->after('concepto_solicitudpago_id');
            }
            if (! Schema::hasColumn('canon_municipal_config', 'moneda_id')) {
                $table->unsignedBigInteger('moneda_id')->nullable()->after('formapagosol_id');
            }
            if (! Schema::hasColumn('canon_municipal_config', 'centrocosto_id')) {
                $table->unsignedBigInteger('centrocosto_id')->nullable()->after('moneda_id');
            }
            if (! Schema::hasColumn('canon_municipal_config', 'cuentacontable_debe_id')) {
                $table->unsignedBigInteger('cuentacontable_debe_id')->nullable()->after('centrocosto_id');
            }
            if (! Schema::hasColumn('canon_municipal_config', 'cuentacontable_haber_id')) {
                $table->unsignedBigInteger('cuentacontable_haber_id')->nullable()->after('cuentacontable_debe_id');
            }
            if (! Schema::hasColumn('canon_municipal_config', 'plantilla_detalle')) {
                $table->string('plantilla_detalle', 180)->nullable()->after('cuentacontable_haber_id');
            }
        });

        // BSA (empresa_id=1): valores de la SP #12045 de referencia.
        DB::table('canon_municipal_config')
            ->where('empresa_id', 1)
            ->update([
                'proveedor_id' => 1281,
                'sector_solicitudpago_id' => 1,
                'concepto_solicitudpago_id' => 12,
                'formapagosol_id' => 4,
                'moneda_id' => 1,
                'centrocosto_id' => 12,
                'cuentacontable_debe_id' => 397,
                'cuentacontable_haber_id' => 41,
                'plantilla_detalle' => '{ORDINAL} SEMANA {MES} CANON BINGO',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('canon_municipal_config')) {
            return;
        }

        Schema::table('canon_municipal_config', function (Blueprint $table) {
            foreach ([
                'proveedor_id',
                'sector_solicitudpago_id',
                'concepto_solicitudpago_id',
                'formapagosol_id',
                'moneda_id',
                'centrocosto_id',
                'cuentacontable_debe_id',
                'cuentacontable_haber_id',
                'plantilla_detalle',
            ] as $col) {
                if (Schema::hasColumn('canon_municipal_config', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
