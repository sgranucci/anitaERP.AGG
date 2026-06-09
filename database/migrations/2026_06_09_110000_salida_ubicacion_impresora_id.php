<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ubicacion_impresora')) {
            return;
        }

        if (! Schema::hasColumn('salida', 'ubicacion_impresora_id')) {
            Schema::table('salida', function (Blueprint $table) {
                $table->unsignedBigInteger('ubicacion_impresora_id')->nullable()->after('nombre');
            });
        }

        if (Schema::hasColumn('salida', 'ubicacion')) {
            $filas = DB::table('salida')
                ->select('ubicacion')
                ->whereNotNull('ubicacion')
                ->where('ubicacion', '!=', '')
                ->distinct()
                ->pluck('ubicacion');

            $mapa = [];
            $now = now();

            foreach ($filas as $textoUbicacion) {
                $nombre = trim((string) $textoUbicacion);
                if ($nombre === '') {
                    continue;
                }

                if (isset($mapa[$nombre])) {
                    continue;
                }

                $existente = DB::table('ubicacion_impresora')->where('nombre', $nombre)->value('id');
                if ($existente) {
                    $mapa[$nombre] = (int) $existente;

                    continue;
                }

                $mapa[$nombre] = (int) DB::table('ubicacion_impresora')->insertGetId([
                    'nombre' => $nombre,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach (DB::table('salida')->get(['id', 'ubicacion']) as $salida) {
                $nombre = trim((string) ($salida->ubicacion ?? ''));
                if ($nombre === '' || ! isset($mapa[$nombre])) {
                    continue;
                }

                DB::table('salida')->where('id', $salida->id)->update([
                    'ubicacion_impresora_id' => $mapa[$nombre],
                ]);
            }

            Schema::table('salida', function (Blueprint $table) {
                $table->dropColumn('ubicacion');
            });
        }

        Schema::table('salida', function (Blueprint $table) {
            if (Schema::hasColumn('salida', 'ubicacion_impresora_id')) {
                $table->foreign('ubicacion_impresora_id', 'fk_salida_ubicacion_impresora')
                    ->references('id')->on('ubicacion_impresora')
                    ->onDelete('restrict')
                    ->onUpdate('restrict');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('salida')) {
            return;
        }

        Schema::table('salida', function (Blueprint $table) {
            if (Schema::hasColumn('salida', 'ubicacion_impresora_id')) {
                $table->dropForeign('fk_salida_ubicacion_impresora');
            }
        });

        if (! Schema::hasColumn('salida', 'ubicacion')) {
            Schema::table('salida', function (Blueprint $table) {
                $table->string('ubicacion', 255)->nullable()->after('nombre');
            });
        }

        if (Schema::hasColumn('salida', 'ubicacion_impresora_id')) {
            foreach (DB::table('salida')->get(['id', 'ubicacion_impresora_id']) as $salida) {
                if (! $salida->ubicacion_impresora_id) {
                    continue;
                }

                $nombre = DB::table('ubicacion_impresora')
                    ->where('id', $salida->ubicacion_impresora_id)
                    ->value('nombre');

                if ($nombre !== null) {
                    DB::table('salida')->where('id', $salida->id)->update([
                        'ubicacion' => $nombre,
                    ]);
                }
            }

            Schema::table('salida', function (Blueprint $table) {
                $table->dropColumn('ubicacion_impresora_id');
            });
        }
    }
};
