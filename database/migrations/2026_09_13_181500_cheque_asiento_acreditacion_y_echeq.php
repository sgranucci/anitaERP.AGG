<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cheque', function (Blueprint $table) {
            if (! Schema::hasColumn('cheque', 'asiento_acreditacion_id')) {
                $table->unsignedBigInteger('asiento_acreditacion_id')->nullable()->after('fecha_acreditacion');
                $table->index('asiento_acreditacion_id', 'idx_cheque_asiento_acreditacion');
            }
            if (! Schema::hasColumn('cheque', 'echeq_estado')) {
                $table->string('echeq_estado', 30)->nullable()->after('nro_echeq');
            }
            if (! Schema::hasColumn('cheque', 'echeq_sync_at')) {
                $table->timestamp('echeq_sync_at')->nullable()->after('echeq_estado');
            }
            if (! Schema::hasColumn('cheque', 'echeq_provider')) {
                $table->string('echeq_provider', 40)->nullable()->after('echeq_sync_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cheque', function (Blueprint $table) {
            foreach (['asiento_acreditacion_id', 'echeq_estado', 'echeq_sync_at', 'echeq_provider'] as $col) {
                if (Schema::hasColumn('cheque', $col)) {
                    if ($col === 'asiento_acreditacion_id') {
                        $table->dropIndex('idx_cheque_asiento_acreditacion');
                    }
                    $table->dropColumn($col);
                }
            }
        });
    }
};
