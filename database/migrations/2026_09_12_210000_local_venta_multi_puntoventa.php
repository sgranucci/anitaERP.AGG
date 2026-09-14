<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un local de venta puede tener N puntos de venta (pivot).
 * Se mantiene local_venta.puntoventa_id como “default rápido” sincronizado (nullable).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('local_venta_puntoventa')) {
            Schema::create('local_venta_puntoventa', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('local_venta_id');
                $table->unsignedBigInteger('puntoventa_id');
                $table->boolean('es_default')->default(false);
                $table->unsignedInteger('orden')->default(0);
                $table->timestamps();

                $table->unique(['local_venta_id', 'puntoventa_id'], 'local_venta_pv_unique');
                $table->foreign('local_venta_id', 'fk_local_venta_pv_local')
                    ->references('id')->on('local_venta')->onDelete('cascade');
                $table->foreign('puntoventa_id', 'fk_local_venta_pv_pv')
                    ->references('id')->on('puntoventa')->onDelete('restrict');
            });
        }

        if (Schema::hasTable('local_venta') && Schema::hasColumn('local_venta', 'puntoventa_id')) {
            $rows = DB::table('local_venta')
                ->whereNotNull('puntoventa_id')
                ->select('id', 'puntoventa_id')
                ->get();

            $now = now();
            foreach ($rows as $row) {
                $exists = DB::table('local_venta_puntoventa')
                    ->where('local_venta_id', $row->id)
                    ->where('puntoventa_id', $row->puntoventa_id)
                    ->exists();
                if ($exists) {
                    continue;
                }
                DB::table('local_venta_puntoventa')->insert([
                    'local_venta_id' => $row->id,
                    'puntoventa_id' => $row->puntoventa_id,
                    'es_default' => true,
                    'orden' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            try {
                Schema::table('local_venta', function (Blueprint $table) {
                    $table->dropForeign(['puntoventa_id']);
                });
            } catch (\Throwable $e) {
                try {
                    Schema::table('local_venta', function (Blueprint $table) {
                        $table->dropForeign('local_venta_puntoventa_id_foreign');
                    });
                } catch (\Throwable $e2) {
                    // FK ya inexistente o nombre distinto
                }
            }

            Schema::table('local_venta', function (Blueprint $table) {
                $table->unsignedBigInteger('puntoventa_id')->nullable()->change();
            });

            Schema::table('local_venta', function (Blueprint $table) {
                $table->foreign('puntoventa_id', 'fk_local_venta_puntoventa_default')
                    ->references('id')->on('puntoventa')->onDelete('restrict');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('local_venta') && Schema::hasColumn('local_venta', 'puntoventa_id')) {
            try {
                Schema::table('local_venta', function (Blueprint $table) {
                    $table->dropForeign('fk_local_venta_puntoventa_default');
                });
            } catch (\Throwable $e) {
                // ignore
            }

            Schema::table('local_venta', function (Blueprint $table) {
                $table->unsignedBigInteger('puntoventa_id')->nullable(false)->change();
            });

            Schema::table('local_venta', function (Blueprint $table) {
                $table->foreign('puntoventa_id', 'local_venta_puntoventa_id_foreign')
                    ->references('id')->on('puntoventa')->onDelete('restrict');
            });
        }

        Schema::dropIfExists('local_venta_puntoventa');
    }
};
