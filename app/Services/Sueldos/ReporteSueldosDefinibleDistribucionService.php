<?php

namespace App\Services\Sueldos;

use App\Exports\Sueldos\ReporteSueldosDefinibleExport;
use App\Mail\Sueldos\ReporteSueldosDefinibleDistribucion;
use App\Models\Sueldos\ReporteSueldosDefinibleEjecucion;
use App\Models\Sueldos\ReporteSueldosDefinibleEnvio;
use App\Models\Sueldos\ReporteSueldosDefinibleSuscripcion;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Sueldos\ReporteDefinible\ReporteSueldosDefinibleAlertaSupport;
use App\Support\Sueldos\ReporteDefinible\ReporteSueldosDefinibleProcesador;
use App\Support\Sueldos\ReporteDefinible\ReporteSueldosDefinibleSuscripcionSupport;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Excel;
use Throwable;

class ReporteSueldosDefinibleDistribucionService
{
    public function __construct(
        private ReporteSueldosDefinibleEjecucionService $ejecuciones,
        private ReporteSueldosDefinibleProcesador $procesador,
        private ReporteSueldosDefinibleSuscripcionSupport $suscripciones,
        private ReporteSueldosDefinibleAlertaSupport $alertas,
    ) {}

    /**
     * @return array{estado:string,mensaje:string,envios:int,envios_ok:int,envios_error:int,filas:int}
     */
    public function enviar(ReporteSueldosDefinibleSuscripcion $suscripcion, bool $dryRun = false): array
    {
        $reporte = $suscripcion->reporte;
        $filtros = $this->suscripciones->filtrosEfectivos($suscripcion);
        if ((int) ($filtros['liquidacion_id'] ?? 0) <= 0) {
            return $this->respuesta('error', 'No hay una liquidación disponible para ejecutar.', 0, 0);
        }

        $dimension = (string) $suscripcion->burst_dimension;
        if ($dimension !== ReporteSueldosDefinibleSuscripcion::BURST_NINGUNA) {
            $filtros['agrupacion'] = 'empleado';
            $filtros['agrupaciones'] = [];
            $filtros['resumido'] = false;
        }

        if ($dryRun) {
            $resultado = $this->procesador->ejecutar($reporte, $filtros);
            $evaluacion = $this->alertas->evaluar((int) $reporte->id, $resultado);
            $resultado['meta']['advertencias'] = $evaluacion['mensajes'];
            $resultado['meta']['controles_bloqueantes'] = $evaluacion['bloqueantes'];
            $padre = null;
        } else {
            $corrida = $this->ejecuciones->ejecutar($reporte, $filtros, [
                'suscripcion_id' => (int) $suscripcion->id,
                'usuario_id' => $suscripcion->usuario_id,
                'origen' => 'programada',
                'publicar' => (bool) $suscripcion->publicar,
            ]);
            $resultado = $corrida['resultado'];
            $padre = $corrida['ejecucion'];
        }

        $filas = count((array) ($resultado['filas'] ?? []));
        if ($filas === 0) {
            return $this->respuesta('omitida', 'El informe no devolvió filas.', 0, 0);
        }
        if (! empty($resultado['meta']['controles_bloqueantes'])) {
            return $this->respuesta(
                'error',
                'Controles bloqueantes: '.implode('; ', (array) $resultado['meta']['controles_bloqueantes']),
                0,
                $filas
            );
        }
        if ($suscripcion->solo_si_alertas && empty($resultado['meta']['advertencias'])) {
            return $this->respuesta('omitida', 'No se disparó ningún control de calidad.', 0, $filas);
        }

        $paquetes = $this->paquetes($resultado, $dimension);
        $enviosOk = 0;
        $enviosError = 0;
        $sinDestino = [];

        foreach ($paquetes as $paquete) {
            $destinatarios = $this->suscripciones->destinatariosResueltos($suscripcion, $paquete['clave']);
            if ($destinatarios === []) {
                $sinDestino[] = $paquete['etiqueta'] ?: 'general';
                if (
                    ! $dryRun
                    && $padre instanceof ReporteSueldosDefinibleEjecucion
                    && $dimension === ReporteSueldosDefinibleSuscripcion::BURST_EMPLEADO
                ) {
                    ReporteSueldosDefinibleEnvio::query()->create([
                        'suscripcion_id' => (int) $suscripcion->id,
                        'ejecucion_id' => (int) $padre->id,
                        'destinatario' => '',
                        'burst_clave' => $paquete['clave'],
                        'burst_etiqueta' => $paquete['etiqueta'] ?: null,
                        'estado' => ReporteSueldosDefinibleEnvio::ESTADO_ERROR,
                        'mensaje' => 'Empleado sin email',
                    ]);
                    $enviosError++;
                }
                continue;
            }

            $ejecucion = $padre;
            if (! $dryRun && $padre !== null && $dimension !== ReporteSueldosDefinibleSuscripcion::BURST_NINGUNA) {
                $ejecucion = $this->ejecuciones->registrarDerivada($padre, $paquete['resultado'], [
                    'burst_clave' => $paquete['clave'],
                    'burst_etiqueta' => $paquete['etiqueta'],
                    'dimensiones' => [$dimension => $paquete['clave']],
                ]);
            }

            $adjuntos = $this->adjuntos($suscripcion, $reporte->titulo, $paquete['resultado'], $paquete['etiqueta']);
            try {
                foreach ($destinatarios as $destinatario) {
                    $envio = null;
                    try {
                        if (! $dryRun && $ejecucion instanceof ReporteSueldosDefinibleEjecucion) {
                            $envio = ReporteSueldosDefinibleEnvio::query()->create([
                                'suscripcion_id' => (int) $suscripcion->id,
                                'ejecucion_id' => (int) $ejecucion->id,
                                'destinatario' => $destinatario,
                                'burst_clave' => $paquete['clave'],
                                'burst_etiqueta' => $paquete['etiqueta'] ?: null,
                                'estado' => ReporteSueldosDefinibleEnvio::ESTADO_PENDIENTE,
                            ]);
                            Mail::to($destinatario)->send(new ReporteSueldosDefinibleDistribucion(
                                $suscripcion,
                                $reporte,
                                $ejecucion,
                                $paquete['etiqueta'],
                                $adjuntos,
                            ));
                            $envio->update([
                                'estado' => ReporteSueldosDefinibleEnvio::ESTADO_ENVIADO,
                                'mensaje' => null,
                            ]);
                        }
                        $enviosOk++;
                    } catch (Throwable $e) {
                        $enviosError++;
                        if ($envio instanceof ReporteSueldosDefinibleEnvio) {
                            $envio->update([
                                'estado' => ReporteSueldosDefinibleEnvio::ESTADO_ERROR,
                                'mensaje' => mb_substr($e->getMessage(), 0, 65535),
                            ]);
                        }
                    }
                }
            } finally {
                $this->limpiar($adjuntos);
            }
        }

        $mensaje = sprintf(
            '%s: %d envío(s) correcto(s), %d error(es), %d fila(s), %d segmento(s).',
            $dryRun ? 'Simulación' : 'Distribución completa',
            $enviosOk,
            $enviosError,
            $filas,
            count($paquetes)
        );
        if ($sinDestino !== []) {
            $mensaje .= ' Sin destinatario: '.implode(', ', $sinDestino).'.';
        }

        $estado = 'ok';
        if ($enviosError > 0) {
            $estado = $enviosOk > 0 ? 'advertencia' : 'error';
        } elseif ($sinDestino !== []) {
            $estado = 'advertencia';
        }

        return $this->respuesta($estado, $mensaje, $enviosOk, $filas, $enviosError);
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @return list<array{clave:string,etiqueta:string,resultado:array<string,mixed>}>
     */
    private function paquetes(array $resultado, string $dimension): array
    {
        if ($dimension === ReporteSueldosDefinibleSuscripcion::BURST_NINGUNA) {
            return [['clave' => '*', 'etiqueta' => '', 'resultado' => $resultado]];
        }

        $grupos = [];
        foreach ((array) ($resultado['filas'] ?? []) as $fila) {
            $clave = (string) (($fila['dimension_keys'][$dimension] ?? $dimension.':0'));
            $etiqueta = (string) (($fila['dimension_labels'][$dimension] ?? 'Sin dato'));
            $grupos[$clave]['etiqueta'] = $etiqueta;
            $grupos[$clave]['filas'][] = $fila;
        }

        ksort($grupos, SORT_NATURAL);
        $out = [];
        foreach ($grupos as $clave => $grupo) {
            $segmentado = $resultado;
            $segmentado['filas'] = array_values($grupo['filas']);
            $segmentado['totales'] = $this->totales($segmentado);
            $segmentado['meta']['cantidad_filas'] = count($segmentado['filas']);
            $segmentado['meta']['burst_clave'] = $clave;
            $segmentado['meta']['burst_etiqueta'] = $grupo['etiqueta'];
            $out[] = [
                'clave' => (string) $clave,
                'etiqueta' => (string) $grupo['etiqueta'],
                'resultado' => $segmentado,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @return array<int, float>
     */
    private function totales(array $resultado): array
    {
        $out = [];
        foreach ((array) ($resultado['columnas'] ?? []) as $columna) {
            if (empty($columna['numerica'])) {
                continue;
            }
            $nro = (int) $columna['nro'];
            $out[$nro] = round(array_sum(array_map(
                fn (array $fila) => (float) ($fila['c'.$nro] ?? 0),
                (array) ($resultado['filas'] ?? [])
            )), 2);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @return list<array{path:string,nombre:string,mime:string}>
     */
    private function adjuntos(
        ReporteSueldosDefinibleSuscripcion $suscripcion,
        string $titulo,
        array $resultado,
        string $segmento
    ): array {
        $dir = storage_path('app/reporte_sueldos_definible_envios');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $slug = preg_replace('/[^A-Za-z0-9_-]+/', '_', $segmento) ?: 'general';
        $base = 'sueldos_'.$suscripcion->reporte_sueldos_definible_id.'_'.$slug.'_'.now()->format('Ymd_His');
        $formato = strtoupper((string) $suscripcion->formato);
        $adjuntos = [];

        if ($formato === 'PDF') {
            $path = $dir.'/'.$base.'.pdf';
            \PDF::loadView('sueldos.reporte_definible.listado', [
                'resultado' => $resultado,
                'titulo' => $titulo,
                'subtitulo' => $segmento,
                'logos' => EmpresaLogoArchivo::logosCabeceraDesdeColeccion(
                    collect([(object) ['nombreempresa' => config('app.empresa')]])
                ),
            ])->setPaper('legal', 'landscape')->save($path);
            $adjuntos[] = ['path' => $path, 'nombre' => $base.'.pdf', 'mime' => 'application/pdf'];
        } else {
            $writer = $formato === 'CSV' ? Excel::CSV : Excel::XLSX;
            $ext = $formato === 'CSV' ? 'csv' : 'xlsx';
            $relativo = 'reporte_sueldos_definible_envios/'.$base.'.'.$ext;
            (new ReporteSueldosDefinibleExport())
                ->parametros($resultado, $titulo, $segmento)
                ->store($relativo, 'local', $writer);
            $adjuntos[] = [
                'path' => storage_path('app/'.$relativo),
                'nombre' => $base.'.'.$ext,
                'mime' => $formato === 'CSV'
                    ? 'text/csv'
                    : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ];
        }

        return $adjuntos;
    }

    /**
     * @param  list<array{path:string,nombre:string,mime:string}>  $adjuntos
     */
    private function limpiar(array $adjuntos): void
    {
        foreach ($adjuntos as $adjunto) {
            if (is_file($adjunto['path'])) {
                unlink($adjunto['path']);
            }
        }
    }

    /**
     * @return array{estado:string,mensaje:string,envios:int,envios_ok:int,envios_error:int,filas:int}
     */
    private function respuesta(
        string $estado,
        string $mensaje,
        int $envios,
        int $filas,
        int $enviosError = 0
    ): array {
        return [
            'estado' => $estado,
            'mensaje' => $mensaje,
            'envios' => $envios,
            'envios_ok' => $envios,
            'envios_error' => $enviosError,
            'filas' => $filas,
        ];
    }
}
