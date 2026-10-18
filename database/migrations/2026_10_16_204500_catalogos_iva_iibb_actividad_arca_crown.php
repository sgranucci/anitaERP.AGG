<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Crown arranca sin estos catálogos. Los ids de IVA e IIBB son los que ya
 * usan los importadores de clientes y proveedores (no son los códigos Anita).
 * Actividades: constancia ARCA de Emprendimientos Crown (CUIT 30-70822285-9).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $ahora = now();
        $this->condicionesIva($ahora);
        $this->condicionesIibb($ahora);
        $this->actividades($ahora);
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        if (Schema::hasTable('actividad_arca') && DB::table('actividad_arca')->count() === 11) {
            DB::table('actividad_arca')->whereIn('codigoarca', [
                '920009', '410011', '451210', '471190', '472300', '551022',
                '561012', '649290', '681010', '681098', '731009',
            ])->delete();
        }

        if (Schema::hasTable('condicionIIBB') && DB::table('condicionIIBB')->count() === 4) {
            DB::table('condicionIIBB')->whereIn('id', [1, 2, 3, 4])->delete();
        }

        if (Schema::hasTable('condicioniva') && DB::table('condicioniva')->count() === 7) {
            DB::table('condicioniva')->whereIn('id', [1, 2, 3, 4, 5, 6, 7])->delete();
        }
    }

    private function condicionesIva($ahora): void
    {
        if (! Schema::hasTable('condicioniva') || DB::table('condicioniva')->exists()) {
            return;
        }

        $filas = [
            [1, 'Responsable Inscripto', 'A', 'A', 'S', 'S', '1'],
            [2, 'Exento', 'B', 'C', 'N', 'S', '4'],
            [3, 'Consumidor Final', 'B', 'B', 'N', 'N', '5'],
            [4, 'Monotributo', 'A', 'C', 'N', 'S', '6'],
            [5, 'Exento Exportación', 'E', 'E', 'N', 'N', '9'],
            [6, 'Sujeto No Categorizado', 'B', 'B', 'S', 'N', '7'],
            [7, 'Baja de impuestos', 'B', 'B', 'N', 'N', ''],
        ];

        foreach ($filas as [$id, $nombre, $letra, $letraCompras, $coniva, $coniibb, $codigo]) {
            DB::table('condicioniva')->insert([
                'id' => $id,
                'nombre' => $nombre,
                'letra' => $letra,
                'letra_compras' => $letraCompras,
                'coniva' => $coniva,
                'coniibb' => $coniibb,
                'codigoexterno' => $codigo,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }

    private function condicionesIibb($ahora): void
    {
        if (! Schema::hasTable('condicionIIBB') || DB::table('condicionIIBB')->exists()) {
            return;
        }

        $filas = [
            [1, 'Inscripto', 'R', 'A'],
            [2, 'Local', 'R', 'A'],
            [3, 'No inscripto', 'R', 'A'],
            [4, 'No alcanzado', 'N', 'A'],
        ];

        foreach ($filas as [$id, $nombre, $forma, $estado]) {
            DB::table('condicionIIBB')->insert([
                'id' => $id,
                'nombre' => $nombre,
                'formacalculo' => $forma,
                'estado' => $estado,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }

    private function actividades($ahora): void
    {
        if (! Schema::hasTable('actividad_arca') || DB::table('actividad_arca')->exists()) {
            return;
        }

        $filas = [
            ['920009', 'Servicios relacionados con juegos de azar y apuestas n.c.p.'],
            ['410011', 'Construcción, reforma y reparación de edificios residenciales'],
            ['451210', 'Venta de autos, camionetas y utilitarios, usados'],
            ['471190', 'Venta al por menor en kioscos, polirrubros y comercios no especializados n.c.p.'],
            ['472300', 'Venta al por menor de tabaco en comercios especializados'],
            ['551022', 'Servicios de alojamiento en hoteles, hosterías y residenciales similares, excepto por hora, que incluyen servicio de restaurante al público'],
            ['561012', 'Servicios de restaurantes y cantinas con espectáculo'],
            ['649290', 'Servicios de crédito n.c.p.'],
            ['681010', 'Servicios de alquiler y explotación de inmuebles para fiestas, convenciones y otros eventos similares'],
            ['681098', 'Servicios inmobiliarios realizados por cuenta propia, con bienes urbanos propios o arrendados n.c.p.'],
            ['731009', 'Servicios de publicidad n.c.p.'],
        ];

        foreach ($filas as [$codigo, $nombre]) {
            DB::table('actividad_arca')->insert([
                'nombre' => $nombre,
                'codigoarca' => $codigo,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }
};
