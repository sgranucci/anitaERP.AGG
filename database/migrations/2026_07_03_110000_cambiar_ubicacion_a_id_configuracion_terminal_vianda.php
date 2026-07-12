<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_terminal_vianda', function (Blueprint $table) {
            $table->unsignedBigInteger('ubicacion_id')->nullable()->after('descripcion');
            $table->foreign('ubicacion_id', 'fk_config_terminal_vianda_ubicacion')
                ->references('id')->on('ubicaciones_gastronomia')
                ->onDelete('set null')
                ->onUpdate('restrict');
        });

        $this->migrarUbicacionTextoAId();

        Schema::table('configuracion_terminal_vianda', function (Blueprint $table) {
            $table->dropColumn('ubicacion');
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_terminal_vianda', function (Blueprint $table) {
            $table->string('ubicacion', 255)->nullable()->after('descripcion');
        });

        DB::table('configuracion_terminal_vianda')
            ->join('ubicaciones_gastronomia', 'ubicaciones_gastronomia.id', '=', 'configuracion_terminal_vianda.ubicacion_id')
            ->update(['configuracion_terminal_vianda.ubicacion' => DB::raw('ubicaciones_gastronomia.nombre')]);

        Schema::table('configuracion_terminal_vianda', function (Blueprint $table) {
            $table->dropForeign('fk_config_terminal_vianda_ubicacion');
            $table->dropColumn('ubicacion_id');
        });
    }

    /**
     * Convierte el texto libre de "ubicacion" en un id de ubicaciones_gastronomia,
     * creando la ubicación por empresa si todavía no existe (mismo criterio que el CRUD).
     */
    private function migrarUbicacionTextoAId(): void
    {
        $filas = DB::table('configuracion_terminal_vianda')
            ->select('id', 'ubicacion', 'empresa_id')
            ->whereNotNull('ubicacion')
            ->where('ubicacion', '<>', '')
            ->get();

        foreach ($filas as $fila) {
            $nombre = trim((string) $fila->ubicacion);
            if ($nombre === '' || (int) $fila->empresa_id <= 0) {
                continue;
            }

            $ubicacionId = DB::table('ubicaciones_gastronomia')
                ->where('nombre', $nombre)
                ->where('empresa_id', $fila->empresa_id)
                ->value('id');

            if ($ubicacionId === null) {
                $ubicacionId = DB::table('ubicaciones_gastronomia')->insertGetId([
                    'nombre' => $nombre,
                    'empresa_id' => $fila->empresa_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('configuracion_terminal_vianda')
                ->where('id', $fila->id)
                ->update(['ubicacion_id' => $ubicacionId]);
        }
    }
};
