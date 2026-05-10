<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAnitaInropremioidClientePremioUif extends Migration
{
    /**
     * Run the migrations.
     * ID premio Anita (nombre de foto en tesorería: pago_{inropremioid}.{ext})
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cliente_premio_uif', function (Blueprint $table) {
            $table->unsignedBigInteger('anita_inropremioid')->nullable()->after('id');
            $table->index(['cliente_uif_id', 'anita_inropremioid'], 'idx_uif_premio_cliente_anita');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cliente_premio_uif', function (Blueprint $table) {
            $table->dropIndex('idx_uif_premio_cliente_anita');
            $table->dropColumn('anita_inropremioid');
        });
    }
}
