<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alcance compartido de widgets del tablero CI (empresa opcional).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('consulta_inteligente_tablero_widget')) {
            return;
        }
        Schema::table('consulta_inteligente_tablero_widget', function (Blueprint $table) {
            if (! Schema::hasColumn('consulta_inteligente_tablero_widget', 'compartido')) {
                $table->boolean('compartido')->default(false)->after('orden');
            }
            if (! Schema::hasColumn('consulta_inteligente_tablero_widget', 'compartido_empresa_id')) {
                $table->unsignedBigInteger('compartido_empresa_id')->nullable()->index()->after('compartido');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('consulta_inteligente_tablero_widget')) {
            return;
        }
        Schema::table('consulta_inteligente_tablero_widget', function (Blueprint $table) {
            if (Schema::hasColumn('consulta_inteligente_tablero_widget', 'compartido_empresa_id')) {
                $table->dropColumn('compartido_empresa_id');
            }
            if (Schema::hasColumn('consulta_inteligente_tablero_widget', 'compartido')) {
                $table->dropColumn('compartido');
            }
        });
    }
};
