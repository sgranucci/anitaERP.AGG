<?php

use App\Support\Database\MigrationDialectSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Saldos por (articulo, deposito) mantenidos on-line por el observer
 * de Articulo_Movimiento. Permite consultas rápidas sin recalcular
 * sumas sobre articulo_movimiento cada vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('articulo_saldo_deposito')) {
            Schema::create('articulo_saldo_deposito', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('articulo_id');
                $table->unsignedBigInteger('deposito_id');
                $table->decimal('cantidad', 20, 6)->default(0);
                $table->timestamp('fecha_ult_movimiento')->nullable();
                $table->timestamps();

                $table->foreign('articulo_id', 'fk_artsalddep_articulo')
                    ->references('id')->on('articulo')
                    ->onDelete('cascade')->onUpdate('restrict');
                $table->foreign('deposito_id', 'fk_artsalddep_depmae')
                    ->references('id')->on('depmae')
                    ->onDelete('cascade')->onUpdate('restrict');

                $table->unique(['articulo_id', 'deposito_id'], 'uk_artsalddep_articulo_deposito');
                $table->index('deposito_id', 'ix_artsalddep_deposito');
            });
        }

        // Pre-carga inicial: suma articulo_movimiento.cantidad agrupado por
        // (articulo_id, deposito_id). cantidad ya viene firmada (R = negativo).
        $this->reconstruirSaldos();
    }

    private function reconstruirSaldos(): void
    {
        // Solo MySQL: el GROUP BY de esta query ya es compatible con ONLY_FULL_GROUP_BY.
        if (MigrationDialectSupport::esMysql()) {
            DB::statement('SET SESSION sql_mode = (SELECT REPLACE(@@sql_mode, "ONLY_FULL_GROUP_BY", ""))');
        }

        DB::table('articulo_saldo_deposito')->truncate();

        $rows = DB::table('articulo_movimiento')
            ->selectRaw('articulo_id, deposito_id,
                SUM(cantidad) AS total,
                MAX(fecha) AS ultima_fecha')
            ->whereNotNull('articulo_id')
            ->whereNotNull('deposito_id')
            ->groupBy('articulo_id', 'deposito_id')
            ->get();

        $now = now();
        $batch = [];
        foreach ($rows as $row) {
            $batch[] = [
                'articulo_id' => (int) $row->articulo_id,
                'deposito_id' => (int) $row->deposito_id,
                'cantidad' => (float) $row->total,
                'fecha_ult_movimiento' => $row->ultima_fecha
                    ? (string) $row->ultima_fecha.' 00:00:00'
                    : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) >= 1000) {
                DB::table('articulo_saldo_deposito')->insert($batch);
                $batch = [];
            }
        }
        if (! empty($batch)) {
            DB::table('articulo_saldo_deposito')->insert($batch);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('articulo_saldo_deposito');
    }
};
