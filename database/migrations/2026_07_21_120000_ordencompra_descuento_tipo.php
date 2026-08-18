<?php

use App\Support\Database\MigrationDialectSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Amplía descuento: ahora puede ser % o monto según descuento_tipo.
        MigrationDialectSupport::statementPorDriver(
            'ALTER TABLE ordencompra MODIFY descuento DECIMAL(18,4) NULL',
            'ALTER TABLE ordencompra ALTER COLUMN descuento TYPE DECIMAL(18,4) USING descuento::DECIMAL(18,4)'
        );

        Schema::table('ordencompra', function (Blueprint $table) {
            $table->string('descuento_tipo', 20)
                ->default('porcentaje')
                ->after('descuento');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ordencompra', function (Blueprint $table) {
            $table->dropColumn('descuento_tipo');
        });

        MigrationDialectSupport::statementPorDriver(
            'ALTER TABLE ordencompra MODIFY descuento DECIMAL(5,2) NULL',
            'ALTER TABLE ordencompra ALTER COLUMN descuento TYPE DECIMAL(5,2) USING descuento::DECIMAL(5,2)'
        );
    }
};
