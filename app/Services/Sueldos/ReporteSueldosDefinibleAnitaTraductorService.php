<?php

namespace App\Services\Sueldos;

use App\Models\Sueldos\ReporteSueldosDefinible;
use App\Models\Sueldos\ReporteSueldosDefinibleColumna;
use App\Models\Sueldos\ReporteSueldosDefinibleConcepto;
use App\Support\Sueldos\ReporteDefinible\ReporteSueldosDefinibleAnitaBridgeReader;
use App\Support\Sueldos\ReporteDefinible\ReporteSueldosDefinibleSupport;
use Illuminate\Support\Facades\DB;

class ReporteSueldosDefinibleAnitaTraductorService
{
    public function __construct(
        private readonly ReporteSueldosDefinibleAnitaBridgeReader $bridge
    ) {
    }

    /**
     * @return array{
     *   dry_run: bool,
     *   importados: int,
     *   actualizados: int,
     *   columnas: int,
     *   conceptos: int,
     *   preview: list<array{codigo:int,titulo:string,tipo:string,columnas:int,conceptos:int}>,
     *   errores: list<string>,
     *   advertencias: list<string>
     * }
     */
    public function importar(?int $desde = null, ?int $hasta = null, bool $reemplazar = true, bool $dryRun = true): array
    {
        $pack = $this->bridge->cargarTodo($desde, $hasta);
        $errores = $pack['errores'];
        $advertencias = [];

        $colsByListado = [];
        foreach ($pack['columnas'] as $o) {
            $r = (array) $o;
            $listado = (int) ($r['lisc_listado'] ?? 0);
            $colsByListado[$listado][] = $r;
        }
        $consByKey = [];
        foreach ($pack['conceptos'] as $o) {
            $r = (array) $o;
            $listado = (int) ($r['liscn_listado'] ?? 0);
            $nro = (int) ($r['liscn_nro_columna'] ?? 0);
            $consByKey[$listado.'|'.$nro][] = $r;
        }

        $preview = [];
        $importados = 0;
        $actualizados = 0;
        $totalCols = 0;
        $totalCons = 0;

        foreach ($pack['cabeceras'] as $o) {
            $r = (array) $o;
            $codigo = (int) ($r['lism_listado'] ?? 0);
            if ($codigo <= 0) {
                continue;
            }
            $titulo = trim((string) ($r['lism_titulo'] ?? ''));
            $tipoAnita = trim((string) ($r['lism_tipo_list'] ?? '3'));
            if ($tipoAnita === '') {
                $tipoAnita = '3';
            }
            $tipo = ReporteSueldosDefinibleSupport::tipoDesdeAnita((int) $tipoAnita[0]);
            $asociado = (int) ($r['lism_asociado'] ?? 0);
            $columnas = $colsByListado[$codigo] ?? [];
            $qCons = 0;
            foreach ($columnas as $col) {
                $nro = (int) ($col['lisc_nro_columna'] ?? 0);
                $qCons += count($consByKey[$codigo.'|'.$nro] ?? []);
            }
            $preview[] = [
                'codigo' => $codigo,
                'titulo' => $titulo !== '' ? $titulo : ('Listado '.$codigo),
                'tipo' => $tipo,
                'columnas' => count($columnas),
                'conceptos' => $qCons,
            ];

            if ($dryRun) {
                $totalCols += count($columnas);
                $totalCons += $qCons;
                $existente = ReporteSueldosDefinible::query()->where('codigo', $codigo)->exists();
                if ($existente) {
                    $actualizados++;
                } else {
                    $importados++;
                }
                continue;
            }

            DB::transaction(function () use (
                $codigo,
                $titulo,
                $tipo,
                $asociado,
                $columnas,
                $consByKey,
                $reemplazar,
                &$importados,
                &$actualizados,
                &$totalCols,
                &$totalCons
            ) {
                $reporte = ReporteSueldosDefinible::query()->where('codigo', $codigo)->first();
                $payload = [
                    'titulo' => $titulo !== '' ? $titulo : ('Listado '.$codigo),
                    'tipo' => $tipo,
                    'asociado_codigo' => $asociado > 0 ? $asociado : null,
                    'origen' => 'anita',
                    'anita_listado' => $codigo,
                    'activo' => true,
                ];
                if ($reporte) {
                    $reporte->update($payload);
                    $actualizados++;
                    if ($reemplazar) {
                        $reporte->columnas()->each(function (ReporteSueldosDefinibleColumna $col) {
                            $col->conceptos()->delete();
                            $col->delete();
                        });
                    }
                } else {
                    $reporte = ReporteSueldosDefinible::query()->create(array_merge($payload, [
                        'codigo' => $codigo,
                    ]));
                    $importados++;
                }

                foreach ($columnas as $col) {
                    $nro = (int) ($col['lisc_nro_columna'] ?? 0);
                    if ($nro <= 0) {
                        continue;
                    }
                    $contenidoRaw = $col['lisc_contenido'] ?? '1';
                    $contenidoCod = is_numeric($contenidoRaw) ? (int) $contenidoRaw : (int) ((string) $contenidoRaw);
                    $campoEmpl = (int) ($col['lisc_campo_empl'] ?? 0);
                    $columna = ReporteSueldosDefinibleColumna::query()->create([
                        'reporte_sueldos_definible_id' => $reporte->id,
                        'nro_columna' => $nro,
                        'descripcion' => trim((string) ($col['lisc_desc'] ?? '')) ?: ('Col '.$nro),
                        'contenido' => ReporteSueldosDefinibleSupport::contenidoDesdeAnita($contenidoCod),
                        'campo_empleado' => $campoEmpl > 0 ? $campoEmpl : null,
                        'largo' => ((int) ($col['lisc_largo_campo'] ?? 0)) ?: null,
                        'orden' => $nro,
                    ]);
                    $totalCols++;
                    foreach ($consByKey[$codigo.'|'.$nro] ?? [] as $con) {
                        $conc = (int) ($con['liscn_concepto'] ?? 0);
                        if ($conc <= 0) {
                            continue;
                        }
                        $signo = trim((string) ($con['liscn_signo'] ?? '+'));
                        if ($signo !== '-' && $signo !== '+') {
                            $signo = '+';
                        }
                        ReporteSueldosDefinibleConcepto::query()->create([
                            'columna_id' => $columna->id,
                            'concepto_codigo' => $conc,
                            'orden' => (int) ($con['liscn_orden'] ?? 0),
                            'signo' => $signo,
                        ]);
                        $totalCons++;
                    }
                }
            });
        }

        if ($pack['cabeceras'] === [] && $errores === []) {
            $advertencias[] = 'Anita no devolvió cabeceras listmae en el rango solicitado.';
        }

        return [
            'dry_run' => $dryRun,
            'importados' => $importados,
            'actualizados' => $actualizados,
            'columnas' => $totalCols,
            'conceptos' => $totalCons,
            'preview' => $preview,
            'errores' => $errores,
            'advertencias' => $advertencias,
        ];
    }
}
