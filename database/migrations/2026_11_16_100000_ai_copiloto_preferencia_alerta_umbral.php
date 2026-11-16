<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Umbrales de alerta proactiva del copiloto (cheques vencidos / deuda AP).
 * 0 = deshabilitado para ese umbral. Opt-in por notify_alerta_umbral.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_copiloto_preferencia')) {
            return;
        }

        Schema::table('ai_copiloto_preferencia', function (Blueprint $table) {
            if (! Schema::hasColumn('ai_copiloto_preferencia', 'notify_alerta_umbral')) {
                $table->boolean('notify_alerta_umbral')->default(false)->after('notify_brief_mail');
            }
            if (! Schema::hasColumn('ai_copiloto_preferencia', 'umbral_cheques_vencidos')) {
                $table->decimal('umbral_cheques_vencidos', 18, 2)->nullable()->after('notify_alerta_umbral');
            }
            if (! Schema::hasColumn('ai_copiloto_preferencia', 'umbral_deuda_ap')) {
                $table->decimal('umbral_deuda_ap', 18, 2)->nullable()->after('umbral_cheques_vencidos');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ai_copiloto_preferencia')) {
            return;
        }

        Schema::table('ai_copiloto_preferencia', function (Blueprint $table) {
            if (Schema::hasColumn('ai_copiloto_preferencia', 'umbral_deuda_ap')) {
                $table->dropColumn('umbral_deuda_ap');
            }
            if (Schema::hasColumn('ai_copiloto_preferencia', 'umbral_cheques_vencidos')) {
                $table->dropColumn('umbral_cheques_vencidos');
            }
            if (Schema::hasColumn('ai_copiloto_preferencia', 'notify_alerta_umbral')) {
                $table->dropColumn('notify_alerta_umbral');
            }
        });
    }
};
