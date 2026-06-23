<?php

namespace App\Services\Uif;

use App\Models\Uif\UifConciliacionWigosPeriodo;
use App\Models\Uif\UifConciliacionWigosPm;
use App\Models\Uif\UifConciliacionWigosTito;
use App\Models\Uif\UifConciliacionWigosUnificado;
use App\Support\Uif\UifWigosConciliacionEmpresaSupport;
use App\Support\Uif\UifWigosConciliacionFiltros;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class UifWigosConciliacionService
{
    public function __construct(
        private readonly UifWigosExcelReader $excelReader,
    ) {
    }

    public function resolverOCrearPeriodo(int $empresaId, int $anio, int $mes, int $usuarioId): UifConciliacionWigosPeriodo
    {
        return UifConciliacionWigosPeriodo::query()->firstOrCreate(
            [
                'empresa_id' => $empresaId,
                'anio' => $anio,
                'mes' => $mes,
            ],
            [
                'usuario_id' => $usuarioId,
            ],
        );
    }

    /**
     * @return array{periodo: UifConciliacionWigosPeriodo, titos: int, pm: int, hojas: list<string>}
     */
    public function cargarArchivoTitos(string $ruta, int $empresaId, int $anio, int $mes, int $usuarioId, ?string $nombreArchivo = null): array
    {
        $datos = $this->excelReader->leerArchivoSoloTitos($ruta);

        return $this->persistirCarga(
            $empresaId,
            $anio,
            $mes,
            $usuarioId,
            $datos['filas'],
            [],
            $nombreArchivo,
            null,
            $datos['hojas'],
            soloTitos: true,
        );
    }

    /**
     * @return array{periodo: UifConciliacionWigosPeriodo, titos: int, pm: int, hojas: list<string>}
     */
    public function cargarArchivoPm(string $ruta, int $empresaId, int $anio, int $mes, int $usuarioId, ?string $nombreArchivo = null): array
    {
        $datos = $this->excelReader->leerArchivoSoloPm($ruta);

        return $this->persistirCarga(
            $empresaId,
            $anio,
            $mes,
            $usuarioId,
            [],
            $datos['filas'],
            null,
            $nombreArchivo,
            $datos['hojas'],
            soloPm: true,
        );
    }

    /**
     * @return array{periodo: UifConciliacionWigosPeriodo, titos: int, pm: int, hojas: list<string>}
     */
    public function cargarArchivoGlobal(string $ruta, int $anio, int $mes, int $usuarioId, ?string $nombreArchivo = null): array
    {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($ruta);
        $resumenHojas = [];
        $totalTitos = 0;
        $totalPm = 0;
        $hojasProcesadas = 0;

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $meta = $this->excelReader->detectarHoja($sheet->getTitle());
            if ($meta === null || $meta['codigo'] === null) {
                continue;
            }

            $empresaId = UifWigosConciliacionEmpresaSupport::empresaIdDesdeCodigo($meta['codigo']);
            if ($empresaId === null) {
                continue;
            }

            if ($meta['tipo'] === 'titos') {
                $filas = $this->excelReader->leerTitosDesdeHoja($sheet);
                $resultado = $this->persistirCarga(
                    $empresaId,
                    $anio,
                    $mes,
                    $usuarioId,
                    $filas,
                    [],
                    $nombreArchivo,
                    null,
                    [$sheet->getTitle()],
                    soloTitos: true,
                );
                $totalTitos += $resultado['titos'];
            } else {
                $filas = $this->excelReader->leerPmDesdeHoja($sheet);
                $resultado = $this->persistirCarga(
                    $empresaId,
                    $anio,
                    $mes,
                    $usuarioId,
                    [],
                    $filas,
                    null,
                    $nombreArchivo,
                    [$sheet->getTitle()],
                    soloPm: true,
                );
                $totalPm += $resultado['pm'];
            }

            $resumenHojas[] = $sheet->getTitle();
            $hojasProcesadas++;
        }

        $spreadsheet->disconnectWorksheets();

        if ($hojasProcesadas === 0) {
            throw new \RuntimeException('No se encontraron planillas Titos o PM Wigos reconocibles en el archivo.');
        }

        return [
            'periodo' => $this->resolverOCrearPeriodo(
                UifWigosConciliacionEmpresaSupport::empresaIdsOrdenados()[0],
                $anio,
                $mes,
                $usuarioId,
            ),
            'titos' => $totalTitos,
            'pm' => $totalPm,
            'hojas' => $resumenHojas,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $titosFilas
     * @param  list<array<string, mixed>>  $pmFilas
     * @param  list<string>  $hojas
     * @return array{periodo: UifConciliacionWigosPeriodo, titos: int, pm: int, hojas: list<string>}
     */
    private function persistirCarga(
        int $empresaId,
        int $anio,
        int $mes,
        int $usuarioId,
        array $titosFilas,
        array $pmFilas,
        ?string $titosArchivo,
        ?string $pmArchivo,
        array $hojas,
        bool $soloTitos = false,
        bool $soloPm = false,
    ): array {
        DB::beginTransaction();

        try {
            $periodo = $this->resolverOCrearPeriodo($empresaId, $anio, $mes, $usuarioId);
            $periodo->usuario_id = $usuarioId;

            if ($titosArchivo !== null) {
                $periodo->titos_archivo = $titosArchivo;
            }

            if ($pmArchivo !== null) {
                $periodo->pm_archivo = $pmArchivo;
            }

            $periodo->save();

            if (! $soloPm && $titosFilas !== []) {
                UifConciliacionWigosTito::query()->where('periodo_id', $periodo->id)->delete();
                foreach ($titosFilas as $fila) {
                    UifConciliacionWigosTito::query()->create([
                        'periodo_id' => $periodo->id,
                        'numero' => $fila['numero'],
                        'secuencia' => $fila['secuencia'],
                        'tipo' => $fila['tipo'] ?: null,
                        'promocion' => $fila['promocion'] ?: null,
                        'monto' => $fila['monto'],
                        'estado' => $fila['estado'] ?: null,
                        'terminal' => $fila['terminal'] ?: null,
                        'cuenta' => $fila['cuenta'] ?: null,
                        'fecha_emision' => $fila['fecha_emision'],
                        'terminal_caja' => $fila['terminal_caja'] ?: null,
                        'fecha_pago' => $fila['fecha_pago'],
                        'observaciones' => $fila['observaciones'] ?: null,
                    ]);
                }
            }

            if (! $soloTitos && $pmFilas !== []) {
                UifConciliacionWigosPm::query()->where('periodo_id', $periodo->id)->delete();
                foreach ($pmFilas as $fila) {
                    UifConciliacionWigosPm::query()->create([
                        'periodo_id' => $periodo->id,
                        'fecha' => $fila['fecha'],
                        'proveedor' => $fila['proveedor'] ?: null,
                        'nombre' => $fila['nombre'] ?: null,
                        'id_planta' => $fila['id_planta'] ?: null,
                        'monto_original' => $fila['monto_original'],
                        'monto_pagado' => $fila['monto_pagado'],
                        'tipo' => $fila['tipo'] ?: null,
                        'estado' => $fila['estado'] ?: null,
                        'observaciones' => $fila['observaciones'] ?: null,
                    ]);
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return [
            'periodo' => $periodo->fresh(),
            'titos' => count($titosFilas),
            'pm' => count($pmFilas),
            'hojas' => $hojas,
        ];
    }

  /**
     * @return array{
     *     unificado: int,
     *     titos_periodo: int,
     *     pm_periodo: int,
     *     conciliados_ticket: int,
     *     conciliados_importe: int
     * }
     */
    public function conciliar(UifConciliacionWigosPeriodo $periodo): array
    {
        $anio = (int) $periodo->anio;
        $mes = (int) $periodo->mes;

        $titos = UifConciliacionWigosTito::query()
            ->where('periodo_id', $periodo->id)
            ->get()
            ->filter(fn (UifConciliacionWigosTito $t) => UifWigosConciliacionFiltros::enPeriodo($t->fecha_pago, $anio, $mes))
            ->values();

        $pm = UifConciliacionWigosPm::query()
            ->where('periodo_id', $periodo->id)
            ->get()
            ->filter(fn (UifConciliacionWigosPm $p) => UifWigosConciliacionFiltros::enPeriodo($p->fecha, $anio, $mes))
            ->values();

        $usedPm = [];
        $usedTito = [];
        $filasUnificadas = [];
        $conciliadosImporte = 0;

        foreach ($titos as $tito) {
            $tieneParImporte = false;
            foreach ($pm as $pmRow) {
                if ($this->montosIguales((float) $tito->monto, (float) $pmRow->monto_pagado)
                    && $this->terminalesRelacionados($tito->terminal, $pmRow->nombre, $pmRow->id_planta)) {
                    $tieneParImporte = true;
                    $usedPm[$pmRow->id] = true;
                    $usedTito[$tito->id] = true;
                    $conciliadosImporte++;
                    break;
                }
            }

            $estado = $tieneParImporte ? 'conciliado_importe' : 'solo_titos';
            $filasUnificadas[] = $this->armarFilaUnificada(
                $tito,
                null,
                'titos',
                $estado,
                $periodo->id,
            );
        }

        foreach ($pm as $pmRow) {
            $estado = isset($usedPm[$pmRow->id]) ? 'conciliado_importe' : 'solo_pm';
            $filasUnificadas[] = $this->armarFilaUnificada(
                null,
                $pmRow,
                'pm',
                $estado,
                $periodo->id,
            );
        }

        usort($filasUnificadas, function (array $a, array $b) {
            $fa = $a['fecha_pago'] instanceof Carbon ? $a['fecha_pago']->timestamp : 0;
            $fb = $b['fecha_pago'] instanceof Carbon ? $b['fecha_pago']->timestamp : 0;

            return $fa <=> $fb;
        });

        DB::beginTransaction();

        try {
            UifConciliacionWigosUnificado::query()->where('periodo_id', $periodo->id)->delete();

            $orden = 0;
            foreach ($filasUnificadas as $fila) {
                $fila['orden'] = $orden++;
                UifConciliacionWigosUnificado::query()->create($fila);
            }

            $periodo->conciliado_at = now();
            $periodo->save();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return [
            'unificado' => count($filasUnificadas),
            'titos_periodo' => $titos->count(),
            'pm_periodo' => $pm->count(),
            'conciliados_ticket' => 0,
            'conciliados_importe' => $conciliadosImporte,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function armarFilaUnificada(
        ?UifConciliacionWigosTito $tito,
        ?UifConciliacionWigosPm $pm,
        string $origen,
        string $estado,
        int $periodoId,
    ): array {
        if ($tito !== null && $pm !== null) {
            return [
                'periodo_id' => $periodoId,
                'fecha_pago' => $tito->fecha_pago ?? $pm->fecha,
                'fecha_emision' => $tito->fecha_emision,
                'monto' => (float) $tito->monto,
                'terminal' => $tito->terminal ?: ($pm->nombre ?: $pm->id_planta),
                'numero' => $tito->numero,
                'origen' => $origen,
                'estado_conciliacion' => $estado,
                'observaciones' => $tito->observaciones ?: $pm->observaciones,
                'tito_id' => $tito->id,
                'pm_id' => $pm->id,
            ];
        }

        if ($tito !== null) {
            return [
                'periodo_id' => $periodoId,
                'fecha_pago' => $tito->fecha_pago,
                'fecha_emision' => $tito->fecha_emision,
                'monto' => (float) $tito->monto,
                'terminal' => $tito->terminal,
                'numero' => $tito->numero,
                'origen' => $origen,
                'estado_conciliacion' => $estado,
                'observaciones' => $tito->observaciones,
                'tito_id' => $tito->id,
                'pm_id' => null,
            ];
        }

        return [
            'periodo_id' => $periodoId,
            'fecha_pago' => $pm->fecha,
            'fecha_emision' => null,
            'monto' => (float) $pm->monto_pagado,
            'terminal' => $pm->nombre ?: $pm->id_planta,
            'numero' => null,
            'origen' => $origen,
            'estado_conciliacion' => $estado,
            'observaciones' => $pm->observaciones,
            'tito_id' => null,
            'pm_id' => $pm->id,
        ];
    }

    private function montosIguales(float $a, float $b): bool
    {
        return abs($a - $b) < 0.01;
    }

    private function terminalesRelacionados(?string $terminalTito, ?string $nombrePm, ?string $idPlanta): bool
    {
        $terminalTito = trim((string) $terminalTito);
        $nombrePm = trim((string) $nombrePm);
        $idPlanta = trim((string) $idPlanta);

        if ($terminalTito === '') {
            return true;
        }

        if ($nombrePm !== '' && ($terminalTito === $nombrePm || str_ends_with($terminalTito, $nombrePm) || str_ends_with($nombrePm, $terminalTito))) {
            return true;
        }

        if ($idPlanta !== '' && ($terminalTito === $idPlanta || str_ends_with($terminalTito, $idPlanta))) {
            return true;
        }

        return false;
    }

    /**
     * @return array<int, array{empresa_id: int, codigo: string, titos: int, pm: int, unificado: int, conciliado_at: ?string}>
     */
    public function resumenEmpresasPeriodo(int $anio, int $mes): array
    {
        $resumen = [];

        foreach (UifWigosConciliacionEmpresaSupport::empresaIdsOrdenados() as $empresaId) {
            $periodo = UifConciliacionWigosPeriodo::query()
                ->where('empresa_id', $empresaId)
                ->where('anio', $anio)
                ->where('mes', $mes)
                ->first();

            if ($periodo === null) {
                $resumen[$empresaId] = [
                    'empresa_id' => $empresaId,
                    'codigo' => UifWigosConciliacionEmpresaSupport::codigoDesdeEmpresaId($empresaId) ?? '',
                    'titos' => 0,
                    'pm' => 0,
                    'unificado' => 0,
                    'conciliado_at' => null,
                ];

                continue;
            }

            $resumen[$empresaId] = [
                'empresa_id' => $empresaId,
                'codigo' => UifWigosConciliacionEmpresaSupport::codigoDesdeEmpresaId($empresaId) ?? '',
                'titos' => $periodo->titos()->count(),
                'pm' => $periodo->premiosMaquina()->count(),
                'unificado' => $periodo->unificado()->count(),
                'conciliado_at' => $periodo->conciliado_at?->format('d/m/Y H:i'),
            ];
        }

        return $resumen;
    }
}
