<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ferli: ubicación Oficina de Administración + salidas Laser PDF Monica / Laura.
 */
return new class extends Migration
{
    private const UBICACION = 'Oficina de Administración';

    private const SALIDA_MONICA = 'Laser PDF Monica';

    private const SALIDA_LAURA = 'Laser PDF Laura';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        if (! Schema::hasTable('salida') || ! Schema::hasTable('ubicacion_impresora')) {
            return;
        }

        $ahora = now();
        $ubicacionId = DB::table('ubicacion_impresora')
            ->where('nombre', self::UBICACION)
            ->value('id');

        if (! $ubicacionId) {
            $ubicacionId = DB::table('ubicacion_impresora')->insertGetId([
                'nombre' => self::UBICACION,
                'descripcion' => 'Impresoras láser de administración (facturas / comprobantes PDF).',
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }

        $script = base_path('bin/imprimir-pdf-laser.sh');

        $this->upsertSalida(
            self::SALIDA_MONICA,
            (int) $ubicacionId,
            $script.' "%s" 160.132.0.201',
            $ahora,
        );

        $this->upsertSalida(
            self::SALIDA_LAURA,
            (int) $ubicacionId,
            $script.' "%s" 160.132.0.200',
            $ahora,
        );
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        if (Schema::hasTable('salida')) {
            DB::table('salida')
                ->whereIn('nombre', [self::SALIDA_MONICA, self::SALIDA_LAURA])
                ->delete();
        }

        if (Schema::hasTable('ubicacion_impresora')) {
            $ubicacionId = DB::table('ubicacion_impresora')
                ->where('nombre', self::UBICACION)
                ->value('id');

            if ($ubicacionId && ! DB::table('salida')->where('ubicacion_impresora_id', $ubicacionId)->exists()) {
                DB::table('ubicacion_impresora')->where('id', $ubicacionId)->delete();
            }
        }
    }

    private function upsertSalida(string $nombre, int $ubicacionId, string $comando, $ahora): void
    {
        $existenteId = DB::table('salida')->where('nombre', $nombre)->value('id');

        $payload = [
            'ubicacion_impresora_id' => $ubicacionId,
            'comando' => $comando,
            'updated_at' => $ahora,
        ];

        if ($existenteId) {
            DB::table('salida')->where('id', $existenteId)->update($payload);

            return;
        }

        DB::table('salida')->insert($payload + [
            'nombre' => $nombre,
            'created_at' => $ahora,
        ]);
    }
};
