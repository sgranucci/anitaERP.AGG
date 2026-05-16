<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ubicaciones_gastronomia')) {
            Schema::create('ubicaciones_gastronomia', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('nombre', 255);
                $table->unsignedBigInteger('empresa_id');
                $table->foreign('empresa_id', 'fk_ubicaciones_gastronomia_empresa')
                    ->references('id')->on('empresa')
                    ->onDelete('restrict')
                    ->onUpdate('restrict');
                $table->timestamps();
                $table->unique(['nombre', 'empresa_id'], 'uk_ubicaciones_gastronomia_nombre_empresa');
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_spanish_ci';
            });
        }

        if (! Schema::hasColumn('mesa_gastronomia', 'ubicacion')) {
            if (! Schema::hasColumn('mesa_gastronomia', 'ubicacion_id')) {
                Schema::table('mesa_gastronomia', function (Blueprint $table) {
                    $table->unsignedBigInteger('ubicacion_id')->nullable()->after('nombre');
                    $table->foreign('ubicacion_id', 'fk_mesa_gastronomia_ubicacion')
                        ->references('id')->on('ubicaciones_gastronomia')
                        ->onDelete('set null')
                        ->onUpdate('restrict');
                });
            }

            return;
        }

        $filas = DB::table('mesa_gastronomia')
            ->select('ubicacion', 'empresa_id')
            ->whereNotNull('ubicacion')
            ->where('ubicacion', '!=', '')
            ->distinct()
            ->get();

        $mapa = [];
        $now = now();

        foreach ($filas as $fila) {
            $nombre = trim((string) $fila->ubicacion);
            if ($nombre === '') {
                continue;
            }

            $clave = $nombre.'|'.(int) $fila->empresa_id;
            if (isset($mapa[$clave])) {
                continue;
            }

            $existente = DB::table('ubicaciones_gastronomia')
                ->where('nombre', $nombre)
                ->where('empresa_id', $fila->empresa_id)
                ->value('id');

            if ($existente) {
                $mapa[$clave] = (int) $existente;

                continue;
            }

            $mapa[$clave] = (int) DB::table('ubicaciones_gastronomia')->insertGetId([
                'nombre' => $nombre,
                'empresa_id' => $fila->empresa_id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::table('mesa_gastronomia', function (Blueprint $table) {
            $table->unsignedBigInteger('ubicacion_id')->nullable()->after('nombre');
        });

        foreach (DB::table('mesa_gastronomia')->get(['id', 'ubicacion', 'empresa_id']) as $mesa) {
            $nombre = trim((string) ($mesa->ubicacion ?? ''));
            if ($nombre === '') {
                continue;
            }

            $clave = $nombre.'|'.(int) $mesa->empresa_id;
            $ubicacionId = $mapa[$clave] ?? null;

            if ($ubicacionId) {
                DB::table('mesa_gastronomia')->where('id', $mesa->id)->update([
                    'ubicacion_id' => $ubicacionId,
                ]);
            }
        }

        Schema::table('mesa_gastronomia', function (Blueprint $table) {
            $table->dropColumn('ubicacion');
        });

        Schema::table('mesa_gastronomia', function (Blueprint $table) {
            $table->foreign('ubicacion_id', 'fk_mesa_gastronomia_ubicacion')
                ->references('id')->on('ubicaciones_gastronomia')
                ->onDelete('set null')
                ->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('mesa_gastronomia') || ! Schema::hasColumn('mesa_gastronomia', 'ubicacion_id')) {
            return;
        }

        Schema::table('mesa_gastronomia', function (Blueprint $table) {
            $table->dropForeign('fk_mesa_gastronomia_ubicacion');
        });

        Schema::table('mesa_gastronomia', function (Blueprint $table) {
            $table->string('ubicacion', 255)->nullable()->after('nombre');
        });

        foreach (DB::table('mesa_gastronomia')->whereNotNull('ubicacion_id')->get(['id', 'ubicacion_id']) as $mesa) {
            $nombre = DB::table('ubicaciones_gastronomia')->where('id', $mesa->ubicacion_id)->value('nombre');
            if ($nombre !== null) {
                DB::table('mesa_gastronomia')->where('id', $mesa->id)->update(['ubicacion' => $nombre]);
            }
        }

        Schema::table('mesa_gastronomia', function (Blueprint $table) {
            $table->dropColumn('ubicacion_id');
        });

        if (Schema::hasTable('ubicaciones_gastronomia')) {
            Schema::dropIfExists('ubicaciones_gastronomia');
        }
    }
};
