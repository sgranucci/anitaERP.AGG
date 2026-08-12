<?php

namespace App\Console\Commands;

use App\Models\Contable\ReporteContable;
use App\Models\Contable\ReporteContableRubro;
use Illuminate\Console\Command;

/**
 * Siembra plantillas tipadas Balance / Estado de resultados.
 */
class ContableSembrarPlantillasReporteDefinible extends Command
{
    protected $signature = 'contable:sembrar-plantillas-reporte-definible {--force : Recrear si ya existen}';

    protected $description = 'Crea informes plantilla (origen=plantilla) Balance y Estado de resultados';

    public function handle(): int
    {
        $this->sembrarPlantilla(
            9001,
            'PLANTILLA Balance',
            'balance',
            'Estado de situación patrimonial',
            [
                ['codigo' => 'A', 'nombre' => 'ACTIVO', 'tipo' => 'total', 'nivel' => 1],
                ['codigo' => 'A1', 'nombre' => 'Activo corriente', 'tipo' => 'cuentas', 'nivel' => 2, 'parent' => 'A'],
                ['codigo' => 'A2', 'nombre' => 'Activo no corriente', 'tipo' => 'cuentas', 'nivel' => 2, 'parent' => 'A'],
                ['codigo' => 'P', 'nombre' => 'PASIVO', 'tipo' => 'total', 'nivel' => 1],
                ['codigo' => 'P1', 'nombre' => 'Pasivo corriente', 'tipo' => 'cuentas', 'nivel' => 2, 'parent' => 'P'],
                ['codigo' => 'P2', 'nombre' => 'Pasivo no corriente', 'tipo' => 'cuentas', 'nivel' => 2, 'parent' => 'P'],
                ['codigo' => 'PN', 'nombre' => 'PATRIMONIO NETO', 'tipo' => 'cuentas', 'nivel' => 1],
            ]
        );

        $this->sembrarPlantilla(
            9002,
            'PLANTILLA Estado de resultados',
            'resultado',
            'Estado de resultados',
            [
                ['codigo' => 'R001', 'nombre' => 'Ingresos', 'tipo' => 'cuentas', 'nivel' => 1],
                ['codigo' => 'R002', 'nombre' => 'Costos', 'tipo' => 'cuentas', 'nivel' => 1],
                ['codigo' => 'R003', 'nombre' => 'Resultado bruto', 'tipo' => 'formula', 'nivel' => 1, 'formula' => 'R001-R002'],
                ['codigo' => 'R004', 'nombre' => 'Gastos de administración', 'tipo' => 'cuentas', 'nivel' => 1],
                ['codigo' => 'R005', 'nombre' => 'Gastos de comercialización', 'tipo' => 'cuentas', 'nivel' => 1],
                ['codigo' => 'R006', 'nombre' => 'Resultado operativo', 'tipo' => 'formula', 'nivel' => 1, 'formula' => 'R003-R004-R005'],
            ]
        );

        $this->info('Plantillas listas (códigos 9001 / 9002).');

        return self::SUCCESS;
    }

    /**
     * @param  list<array<string, mixed>>  $nodos
     */
    private function sembrarPlantilla(int $codigo, string $nombre, string $tipo, string $titulo, array $nodos): void
    {
        $existente = ReporteContable::query()->where('codigo', $codigo)->first();
        if ($existente && ! $this->option('force')) {
            $this->line("Ya existe código {$codigo}, omitido.");

            return;
        }
        if ($existente) {
            $existente->rubros()->each(function (ReporteContableRubro $r) {
                $r->cuentas()->delete();
                $r->delete();
            });
            $existente->delete();
        }

        $rep = ReporteContable::query()->create([
            'codigo' => $codigo,
            'nombre' => $nombre,
            'titulo1' => $titulo,
            'titulo2' => 'Plantilla — crear copia para usar',
            'tipo' => $tipo,
            'origen' => 'plantilla',
            'activo' => false,
            'observaciones' => 'Plantilla de sistema. Use «Crear desde plantilla» en el catálogo.',
        ]);

        $mapCodigo = [];
        $orden = 0;
        foreach ($nodos as $n) {
            $orden++;
            $parentId = null;
            if (! empty($n['parent']) && isset($mapCodigo[$n['parent']])) {
                $parentId = $mapCodigo[$n['parent']];
            }
            $rubro = ReporteContableRubro::query()->create([
                'reporte_contable_id' => $rep->id,
                'parent_id' => $parentId,
                'codigo_linea' => $n['codigo'],
                'nombre' => $n['nombre'],
                'nivel' => (int) $n['nivel'],
                'orden' => $orden,
                'tipo' => $n['tipo'],
                'formula' => $n['formula'] ?? null,
                'estilo_negrita' => (int) $n['nivel'] === 1,
                'mostrar_total' => true,
            ]);
            $mapCodigo[$n['codigo']] = (int) $rubro->id;
        }
        $this->info("Plantilla {$codigo} creada.");
    }
}
