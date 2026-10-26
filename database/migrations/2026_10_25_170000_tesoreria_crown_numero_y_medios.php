<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Número propio por sala (una secuencia de habilitación y otra de rendición)
 * y los importes de gastronomía, hotel y medios que Anita pide en la rendición.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        if (! Schema::hasTable('tesoreria_crown_comprobante')) {
            return;
        }

        if (! Schema::hasTable('tesoreria_crown_numerador')) {
            Schema::create('tesoreria_crown_numerador', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sala_id')->constrained('sala');
                $table->char('tipo', 1);
                $table->unsignedInteger('ultimo')->default(0);
                $table->timestamps();
                $table->unique(['sala_id', 'tipo'], 'tc_numerador_sala_tipo_uq');
            });
        }

        if (! Schema::hasColumn('tesoreria_crown_comprobante', 'numero')) {
            Schema::table('tesoreria_crown_comprobante', function (Blueprint $table) {
                $table->unsignedInteger('numero')->nullable()->after('tipo');
                $table->decimal('total_x', 14, 2)->default(0);
                $table->decimal('invitacion', 14, 2)->default(0);
                $table->decimal('total_z', 14, 2)->default(0);
                $table->string('nro_z', 20)->nullable();
                $table->unsignedBigInteger('ult_ticket')->nullable();
                $table->decimal('total_nc', 14, 2)->default(0);
                $table->decimal('iva_facturas', 14, 2)->default(0);
                $table->decimal('iva_nc', 14, 2)->default(0);
                $table->string('nro_z_cash', 20)->nullable();
                $table->decimal('venta_cash', 14, 2)->default(0);
                $table->decimal('vta_apuesta', 14, 2)->default(0);
                $table->decimal('venta_online', 14, 2)->default(0);
                $table->decimal('tarjeta_debito', 14, 2)->default(0);
                $table->decimal('tarjeta_credito', 14, 2)->default(0);
                $table->decimal('recargo_fin', 14, 2)->default(0);
                $table->decimal('ctacte_hotel', 14, 2)->default(0);
                $table->decimal('transferencia_hotel', 14, 2)->default(0);
                $table->decimal('venta_voucher', 14, 2)->default(0);
                $table->decimal('pago_voucher', 14, 2)->default(0);
                $table->decimal('total_qr', 14, 2)->default(0);
            });
        }

        $grupos = DB::table('tesoreria_crown_comprobante')
            ->select('sala_id', 'tipo')
            ->whereNull('numero')
            ->distinct()
            ->get();
        foreach ($grupos as $grupo) {
            $ids = DB::table('tesoreria_crown_comprobante')
                ->where('sala_id', $grupo->sala_id)
                ->where('tipo', $grupo->tipo)
                ->orderBy('id')
                ->pluck('id');
            $numero = 0;
            foreach ($ids as $id) {
                $numero++;
                DB::table('tesoreria_crown_comprobante')->where('id', $id)->update(['numero' => $numero]);
            }
            DB::table('tesoreria_crown_numerador')->updateOrInsert(
                ['sala_id' => $grupo->sala_id, 'tipo' => $grupo->tipo],
                ['ultimo' => $numero, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        Schema::table('tesoreria_crown_comprobante', function (Blueprint $table) {
            $table->unsignedInteger('numero')->nullable(false)->change();
            $table->unique(['sala_id', 'tipo', 'numero'], 'tc_comp_sala_tipo_numero_uq');
        });
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        Schema::dropIfExists('tesoreria_crown_numerador');
        if (! Schema::hasTable('tesoreria_crown_comprobante') || ! Schema::hasColumn('tesoreria_crown_comprobante', 'numero')) {
            return;
        }
        Schema::table('tesoreria_crown_comprobante', function (Blueprint $table) {
            $table->dropUnique('tc_comp_sala_tipo_numero_uq');
            $table->dropColumn([
                'numero', 'total_x', 'invitacion', 'total_z', 'nro_z', 'ult_ticket', 'total_nc',
                'iva_facturas', 'iva_nc', 'nro_z_cash', 'venta_cash', 'vta_apuesta', 'venta_online',
                'tarjeta_debito', 'tarjeta_credito', 'recargo_fin', 'ctacte_hotel', 'transferencia_hotel',
                'venta_voucher', 'pago_voucher', 'total_qr',
            ]);
        });
    }
};
