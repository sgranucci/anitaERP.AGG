<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cuenta_gastronomia')) {
            Schema::table('cuenta_gastronomia', function (Blueprint $table) {
                if (! Schema::hasColumn('cuenta_gastronomia', 'origen_pos')) {
                    $table->string('origen_pos', 30)->default('salon')->after('tipo');
                    $table->index(['origen_pos', 'estado', 'identificador_pc'], 'idx_cuenta_gastro_origen_pos');
                }
                if (! Schema::hasColumn('cuenta_gastronomia', 'cliente_vip_gastronomia_id')) {
                    $table->unsignedBigInteger('cliente_vip_gastronomia_id')->nullable()->after('cliente_interno_descuento_id');
                    $table->foreign('cliente_vip_gastronomia_id', 'fk_cuenta_gastro_cliente_vip')
                        ->references('id')->on('cliente_vip_gastronomia')
                        ->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('venta_gastronomia_emision')) {
            Schema::table('venta_gastronomia_emision', function (Blueprint $table) {
                if (! Schema::hasColumn('venta_gastronomia_emision', 'cliente_vip_gastronomia_id')) {
                    $table->unsignedBigInteger('cliente_vip_gastronomia_id')->nullable()->after('cuenta_gastronomia_id');
                    $table->foreign('cliente_vip_gastronomia_id', 'fk_vge_cliente_vip')
                        ->references('id')->on('cliente_vip_gastronomia')
                        ->nullOnDelete();
                }
                if (! Schema::hasColumn('venta_gastronomia_emision', 'origen_pos')) {
                    $table->string('origen_pos', 30)->nullable()->after('cliente_vip_gastronomia_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('venta_gastronomia_emision')) {
            Schema::table('venta_gastronomia_emision', function (Blueprint $table) {
                if (Schema::hasColumn('venta_gastronomia_emision', 'origen_pos')) {
                    $table->dropColumn('origen_pos');
                }
                if (Schema::hasColumn('venta_gastronomia_emision', 'cliente_vip_gastronomia_id')) {
                    $table->dropForeign('fk_vge_cliente_vip');
                    $table->dropColumn('cliente_vip_gastronomia_id');
                }
            });
        }

        if (Schema::hasTable('cuenta_gastronomia')) {
            Schema::table('cuenta_gastronomia', function (Blueprint $table) {
                if (Schema::hasColumn('cuenta_gastronomia', 'cliente_vip_gastronomia_id')) {
                    $table->dropForeign('fk_cuenta_gastro_cliente_vip');
                    $table->dropColumn('cliente_vip_gastronomia_id');
                }
                if (Schema::hasColumn('cuenta_gastronomia', 'origen_pos')) {
                    $table->dropIndex('idx_cuenta_gastro_origen_pos');
                    $table->dropColumn('origen_pos');
                }
            });
        }
    }
};
