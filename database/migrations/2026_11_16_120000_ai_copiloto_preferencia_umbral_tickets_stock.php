<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Umbrales extra: tickets abiertos (cantidad) y stock crítico (cantidad de insumos).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_copiloto_preferencia')) {
            return;
        }

        Schema::table('ai_copiloto_preferencia', function (Blueprint $table) {
            if (! Schema::hasColumn('ai_copiloto_preferencia', 'umbral_tickets_abiertos')) {
                $table->unsignedInteger('umbral_tickets_abiertos')->nullable()->after('umbral_deuda_ap');
            }
            if (! Schema::hasColumn('ai_copiloto_preferencia', 'umbral_stock_critico')) {
                $table->unsignedInteger('umbral_stock_critico')->nullable()->after('umbral_tickets_abiertos');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ai_copiloto_preferencia')) {
            return;
        }

        Schema::table('ai_copiloto_preferencia', function (Blueprint $table) {
            if (Schema::hasColumn('ai_copiloto_preferencia', 'umbral_stock_critico')) {
                $table->dropColumn('umbral_stock_critico');
            }
            if (Schema::hasColumn('ai_copiloto_preferencia', 'umbral_tickets_abiertos')) {
                $table->dropColumn('umbral_tickets_abiertos');
            }
        });
    }
};
