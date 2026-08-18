<?php

namespace App\Console\Commands;

use App\Models\Sueldos\ReporteSueldosDefinible;
use App\Models\Sueldos\ReporteSueldosDefinibleColumna;
use App\Models\Sueldos\ReporteSueldosDefinibleConcepto;
use App\Support\Sueldos\ReporteDefinible\ReporteSueldosDefinibleSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SueldosSembrarPlantillasReporteDefinible extends Command
{
    protected $signature = 'sueldos:sembrar-plantillas-reporte-definible {--ejecutar : Persistir}';

    protected $description = 'Siembra plantillas base de listados definibles de sueldos (códigos 9001+)';

    public function handle(): int
    {
        $plantillas = [
            [
                'codigo' => 9001,
                'titulo' => 'Plantilla genérica neto + básicos',
                'tipo' => ReporteSueldosDefinibleSupport::TIPO_GENERICO,
                'columnas' => [
                    ['nro' => 1, 'desc' => 'CUIL', 'contenido' => 'campo_empleado', 'campo' => 4, 'largo' => 13],
                    ['nro' => 2, 'desc' => 'Categoría', 'contenido' => 'campo_empleado', 'campo' => 1, 'largo' => 30],
                    ['nro' => 3, 'desc' => 'Neto', 'contenido' => 'importe', 'conceptos' => [['codigo' => 3009, 'signo' => '+']]],
                ],
            ],
            [
                'codigo' => 9002,
                'titulo' => 'Plantilla obra social (ejemplo)',
                'tipo' => ReporteSueldosDefinibleSupport::TIPO_OSOCIAL,
                'columnas' => [
                    ['nro' => 1, 'desc' => 'Afiliado OS', 'contenido' => 'campo_empleado', 'campo' => 10, 'largo' => 30],
                    ['nro' => 2, 'desc' => 'Aporte OS', 'contenido' => 'importe', 'conceptos' => [['codigo' => 1510, 'signo' => '+']]],
                ],
            ],
        ];

        if (! $this->option('ejecutar')) {
            $this->warn('Dry-run. Use --ejecutar para grabar.');
            foreach ($plantillas as $p) {
                $this->line(sprintf('%d %s (%d cols)', $p['codigo'], $p['titulo'], count($p['columnas'])));
            }

            return self::SUCCESS;
        }

        foreach ($plantillas as $p) {
            DB::transaction(function () use ($p) {
                $reporte = ReporteSueldosDefinible::query()->updateOrCreate(
                    ['codigo' => $p['codigo']],
                    [
                        'titulo' => $p['titulo'],
                        'tipo' => $p['tipo'],
                        'origen' => 'plantilla',
                        'activo' => true,
                    ]
                );
                $reporte->columnas()->each(function (ReporteSueldosDefinibleColumna $c) {
                    $c->conceptos()->delete();
                    $c->delete();
                });
                foreach ($p['columnas'] as $col) {
                    $columna = ReporteSueldosDefinibleColumna::query()->create([
                        'reporte_sueldos_definible_id' => $reporte->id,
                        'nro_columna' => $col['nro'],
                        'descripcion' => $col['desc'],
                        'contenido' => $col['contenido'],
                        'campo_empleado' => $col['campo'] ?? null,
                        'largo' => $col['largo'] ?? null,
                        'orden' => $col['nro'],
                    ]);
                    foreach ($col['conceptos'] ?? [] as $i => $con) {
                        ReporteSueldosDefinibleConcepto::query()->create([
                            'columna_id' => $columna->id,
                            'concepto_codigo' => $con['codigo'],
                            'orden' => $i + 1,
                            'signo' => $con['signo'] ?? '+',
                        ]);
                    }
                }
            });
            $this->info('Plantilla '.$p['codigo'].' OK');
        }

        return self::SUCCESS;
    }
}
