<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChequeReemplazaIngresoegreso extends Migration
{
    public function up(): void
    {
        Schema::table('cheque', function (Blueprint $table) {
            if (! Schema::hasColumn('cheque', 'cheque_reemplaza_id')) {
                $table->unsignedBigInteger('cheque_reemplaza_id')->nullable()->after('cobranza_id');
                $table->foreign('cheque_reemplaza_id', 'fk_cheque_reemplaza')
                    ->references('id')->on('cheque')
                    ->onDelete('restrict')->onUpdate('restrict');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cheque', function (Blueprint $table) {
            if (Schema::hasColumn('cheque', 'cheque_reemplaza_id')) {
                $table->dropForeign('fk_cheque_reemplaza');
                $table->dropColumn('cheque_reemplaza_id');
            }
        });
    }
}
