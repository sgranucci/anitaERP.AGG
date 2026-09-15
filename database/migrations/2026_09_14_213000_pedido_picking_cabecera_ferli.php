<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cabecera de picking (ID secuencial) para agrupar líneas preparadas (varios clientes).
 * Solo Calzados Ferli. Sin SoftDeletes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        if (! Schema::hasTable('pedido_picking')) {
            Schema::create('pedido_picking', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('codigo')->unique();
                $table->date('fecha');
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->string('observacion', 255)->nullable();
                $table->timestamps();
                $table->index(['fecha', 'codigo'], 'pedido_picking_fecha_codigo_idx');
            });
        }

        if (! Schema::hasColumn('pedido_combinacion', 'picking_id')) {
            Schema::table('pedido_combinacion', function (Blueprint $table) {
                $table->unsignedBigInteger('picking_id')->nullable()->after('picking');
                $table->index('picking_id', 'pedido_combinacion_picking_id_idx');
            });
        }

        // Backfill: un picking por día de líneas ya preparadas sin cabecera.
        $dias = DB::table('pedido_combinacion')
            ->where('picking', 'S')
            ->whereNull('picking_id')
            ->whereNotNull('picking_at')
            ->selectRaw('DATE(picking_at) as dia')
            ->groupBy('dia')
            ->orderBy('dia')
            ->pluck('dia');

        $codigo = (int) (DB::table('pedido_picking')->max('codigo') ?? 0);
        foreach ($dias as $dia) {
            $codigo++;
            $pickingId = (int) DB::table('pedido_picking')->insertGetId([
                'codigo' => $codigo,
                'fecha' => $dia,
                'usuario_id' => null,
                'observacion' => 'Migración líneas previas '.$dia,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('pedido_combinacion')
                ->where('picking', 'S')
                ->whereNull('picking_id')
                ->whereDate('picking_at', $dia)
                ->update(['picking_id' => $pickingId, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        if (Schema::hasColumn('pedido_combinacion', 'picking_id')) {
            Schema::table('pedido_combinacion', function (Blueprint $table) {
                try {
                    $table->dropIndex('pedido_combinacion_picking_id_idx');
                } catch (\Throwable $e) {
                    // índice ausente
                }
                $table->dropColumn('picking_id');
            });
        }

        Schema::dropIfExists('pedido_picking');
    }
};
