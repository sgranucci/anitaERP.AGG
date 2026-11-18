<?php

namespace App\Services\Solicitudpago;

use App\Imports\Solicitudpago\SolicitudpagoCuotasImportLecturaCruda;
use App\Support\Archivo\TextoUtf8Support;
use App\Support\Contable\AsientoImportColumnasSupport;
use App\Support\Solicitudpago\SolicitudpagoCuotasImportColumnasSupport;
use App\Support\Stock\PrecioImportColumnasSupport;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Preview / parseo de Excel-CSV de cuotas SP (estilo novedades sueldos).
 */
class SolicitudpagoCuotasImportService
{
    private const MAX_PREVIEW = 40;

    private const MAX_APLICAR = 500;

    private const MAX_ERRORES_SAMPLE = 15;

    /**
     * @param  array<string, mixed>  $mappingOverride
     * @return array<string, mixed>
     */
    public function preview(
        UploadedFile $archivo,
        array $mappingOverride,
        ?int $hoja1Based = null,
        ?int $filaEncabezadoManual = null,
        ?string $modoColumnas = null,
        ?string $csvSeparador = null
    ): array {
        $archivo = TextoUtf8Support::asegurarCsvUtf8($archivo);
        $leido = $this->leerHoja(
            $archivo,
            $hoja1Based,
            $filaEncabezadoManual,
            $mappingOverride,
            $modoColumnas,
            $csvSeparador
        );
        if (isset($leido['error'])) {
            return $leido['error'];
        }

        $ok = 0;
        $error = 0;
        $omitidas = 0;
        $sumaMonto = 0.0;
        $filasPreview = [];
        $filasAplicar = [];
        $erroresSample = [];
        $totalDatos = 0;
        $nroAuto = 1;

        foreach ($leido['filas'] as $filaExcel => $fila) {
            $totalDatos++;
            $resultado = $this->validarFila($fila, $leido['mapa'], $nroAuto, $filaExcel);
            if ($resultado['estado'] === 'ok') {
                $ok++;
                $sumaMonto += (float) ($resultado['monto_num'] ?? 0);
                $nroAuto = max($nroAuto, (int) $resultado['nro_cuota']) + 1;
                if (count($filasAplicar) < self::MAX_APLICAR) {
                    $filasAplicar[] = [
                        'nro_cuota' => (int) $resultado['nro_cuota'],
                        'fecha_vencimiento' => (string) $resultado['fecha_vencimiento'],
                        'monto' => (float) $resultado['monto_num'],
                    ];
                }
            } elseif ($resultado['estado'] === 'omitida') {
                $omitidas++;
            } else {
                $error++;
                if (count($erroresSample) < self::MAX_ERRORES_SAMPLE) {
                    $erroresSample[] = [
                        'fila' => $filaExcel,
                        'mensaje' => $resultado['mensaje'],
                    ];
                }
            }

            if (count($filasPreview) < self::MAX_PREVIEW) {
                $filasPreview[] = array_merge(['fila_excel' => $filaExcel], $resultado);
            }
        }

        $requeridosOk = ($leido['mapa']['fecha_vencimiento'] ?? null) !== null
            && ($leido['mapa']['monto'] ?? null) !== null;

        $advertencias = $leido['advertencias'] ?? [];
        if (! $requeridosOk) {
            $advertencias[] = SolicitudpagoCuotasImportColumnasSupport::esModoNumero($leido['modo_columnas'] ?? null)
                ? 'Asigná en el mapeo Vencimiento y Monto a las columnas del archivo (Columna 1, 2…).'
                : 'Faltan columnas obligatorias: vencimiento y monto.';
        }
        if ($ok > self::MAX_APLICAR) {
            $advertencias[] = 'Se aplicarán solo las primeras '.self::MAX_APLICAR.' cuotas válidas.';
        }

        $porNumero = SolicitudpagoCuotasImportColumnasSupport::esModoNumero($leido['modo_columnas'] ?? null);

        return [
            'ok' => $requeridosOk && $ok > 0,
            'headers' => $leido['headers'],
            'mapping' => $leido['mapa'],
            'mapping_ui' => SolicitudpagoCuotasImportColumnasSupport::mappingParaUi(
                $leido['mapa'],
                $leido['headers'],
                $porNumero
            ),
            'campos' => SolicitudpagoCuotasImportColumnasSupport::metaCampos(),
            'filas_preview' => $filasPreview,
            'filas_aplicar' => $filasAplicar,
            'totales' => [
                'ok' => $ok,
                'error' => $error,
                'omitidas' => $omitidas,
                'total' => $totalDatos,
                'suma_monto' => round($sumaMonto, 2),
            ],
            'errores' => $erroresSample,
            'fila_encabezado' => $leido['fila_encabezado'],
            'fila_encabezado_automatica' => (bool) ($leido['fila_encabezado_automatica'] ?? ($filaEncabezadoManual === null)),
            'sin_encabezado' => (bool) ($leido['sin_encabezado'] ?? false),
            'modo_columnas' => $leido['modo_columnas'] ?? SolicitudpagoCuotasImportColumnasSupport::MODO_COLUMNAS_TITULO,
            'hojas' => $leido['hojas'],
            'hoja_seleccionada' => $leido['hoja_seleccionada'],
            'hoja_nombre' => $leido['hoja_nombre'],
            'hay_mas_filas' => $totalDatos > self::MAX_PREVIEW,
            'advertencias' => $advertencias,
            'csv_separador' => AsientoImportColumnasSupport::valorFormularioSeparadorCsv(
                (string) ($leido['csv_separador_form'] ?? 'auto')
            ),
            'csv_separador_detectado' => AsientoImportColumnasSupport::valorFormularioSeparadorCsv(
                (string) ($leido['csv_delimiter'] ?? ';')
            ),
            'csv_separador_automatico' => (bool) ($leido['csv_separador_automatico'] ?? false),
            'csv_separador_etiqueta' => AsientoImportColumnasSupport::etiquetaSeparadorCsv(
                (string) ($leido['csv_delimiter'] ?? ';')
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $mappingOverride
     * @return array<string, mixed>
     */
    private function leerHoja(
        UploadedFile $archivo,
        ?int $hoja1Based,
        ?int $filaEncabezadoManual,
        array $mappingOverride,
        ?string $modoColumnas = null,
        ?string $csvSeparador = null
    ): array {
        $modo = SolicitudpagoCuotasImportColumnasSupport::normalizarModoColumnas($modoColumnas);
        $porNumero = SolicitudpagoCuotasImportColumnasSupport::esModoNumero($modo);

        $separadorResuelto = AsientoImportColumnasSupport::resolverSeparadorCsv($archivo, $csvSeparador);
        $delimiter = $separadorResuelto['delimiter'];
        $separadorAutomatico = (bool) $separadorResuelto['automatico'];

        $hojas = PrecioImportColumnasSupport::hojasParaSelector($archivo);
        $hojaIndice0 = PrecioImportColumnasSupport::indiceHojaDesdeRequest($hoja1Based, count($hojas));
        $hojaSeleccionada = $hojas[$hojaIndice0] ?? $hojas[0] ?? ['indice' => 1, 'nombre' => 'Hoja1'];

        $hoja = Excel::toArray(new SolicitudpagoCuotasImportLecturaCruda($delimiter), $archivo)[$hojaIndice0] ?? [];
        if ($hoja === []) {
            return [
                'error' => [
                    'ok' => false,
                    'mensaje' => 'La hoja seleccionada no tiene filas legibles. Si es CSV, probá otro separador.',
                    'hojas' => $hojas,
                    'hoja_seleccionada' => (int) ($hojaSeleccionada['indice'] ?? 1),
                    'hoja_nombre' => (string) ($hojaSeleccionada['nombre'] ?? ''),
                    'modo_columnas' => $modo,
                    'csv_delimiter' => $delimiter,
                    'csv_separador_form' => AsientoImportColumnasSupport::valorFormularioSeparadorCsv(
                        $separadorAutomatico ? 'auto' : $delimiter
                    ),
                    'csv_separador_automatico' => $separadorAutomatico,
                    'csv_separador_etiqueta' => AsientoImportColumnasSupport::etiquetaSeparadorCsv($delimiter),
                ],
            ];
        }

        $advertencias = [];
        $sinEncabezado = false;
        $filaEncabezadoAutomatica = false;

        if ($porNumero) {
            $filaInicioDatos = ($filaEncabezadoManual !== null && $filaEncabezadoManual >= 1)
                ? $filaEncabezadoManual
                : 1;
            $indiceInicioDatos = $filaInicioDatos - 1;
            $filaEncabezado = 0;
            $sinEncabezado = true;
            $advertencias[] = 'Modo sin título: datos desde la fila '.$filaInicioDatos
                .'. En el mapeo tipá el número de columna (1 = primera) o letra Excel (A, B…).';

            $filas = [];
            for ($i = $indiceInicioDatos; $i < count($hoja); $i++) {
                $fila = $hoja[$i] ?? null;
                if (! is_array($fila) || $this->filaVacia($fila)) {
                    continue;
                }
                $filas[$i + 1] = $fila;
            }
            $headers = SolicitudpagoCuotasImportColumnasSupport::headersSinteticosDesdeFilas(array_values($filas));
            $mapaAuto = array_fill_keys(SolicitudpagoCuotasImportColumnasSupport::campos(), null);
            $mapa = SolicitudpagoCuotasImportColumnasSupport::fusionarMapa($mapaAuto, $mappingOverride, true, []);
        } else {
            $filaEncabezado = SolicitudpagoCuotasImportColumnasSupport::detectarFilaEncabezado(
                $archivo,
                $filaEncabezadoManual,
                $hojaIndice0,
                $delimiter
            );
            $filaEncabezadoAutomatica = $filaEncabezadoManual === null || $filaEncabezadoManual < 1;
            $indiceEncabezado = $filaEncabezado - 1;
            $headersRaw = $hoja[$indiceEncabezado] ?? [];
            if (! is_array($headersRaw)) {
                $headersRaw = [];
            }

            if (! SolicitudpagoCuotasImportColumnasSupport::pareceFilaEncabezado($headersRaw)
                && empty(array_filter($mappingOverride, static fn ($v) => trim((string) $v) !== ''))
            ) {
                return [
                    'error' => [
                        'ok' => false,
                        'mensaje' => 'No se detectó fila de títulos en la fila '.$filaEncabezado
                            .' de «'.($hojaSeleccionada['nombre'] ?? '').'». Indicá la fila, usá «Por número de columna (sin título)», o mapeá Vencimiento y Monto.',
                        'fila_encabezado' => $filaEncabezado,
                        'modo_columnas' => $modo,
                        'hojas' => $hojas,
                        'hoja_seleccionada' => (int) ($hojaSeleccionada['indice'] ?? 1),
                        'hoja_nombre' => (string) ($hojaSeleccionada['nombre'] ?? ''),
                        'csv_delimiter' => $delimiter,
                        'csv_separador_form' => AsientoImportColumnasSupport::valorFormularioSeparadorCsv(
                            $separadorAutomatico ? 'auto' : $delimiter
                        ),
                        'csv_separador_automatico' => $separadorAutomatico,
                        'csv_separador_etiqueta' => AsientoImportColumnasSupport::etiquetaSeparadorCsv($delimiter),
                    ],
                ];
            }

            $headers = [];
            foreach ($headersRaw as $i => $celda) {
                $titulo = trim((string) $celda);
                $headers[] = [
                    'indice' => (int) $i,
                    'titulo' => $titulo !== '' ? $titulo : ('Columna '.((int) $i + 1)),
                ];
            }

            $mapa = SolicitudpagoCuotasImportColumnasSupport::fusionarMapa(
                SolicitudpagoCuotasImportColumnasSupport::resolverMapaDesdeEncabezados($headersRaw),
                $mappingOverride,
                false,
                $headersRaw
            );

            $filas = [];
            for ($i = $indiceEncabezado + 1; $i < count($hoja); $i++) {
                $fila = $hoja[$i] ?? null;
                if (! is_array($fila) || $this->filaVacia($fila)) {
                    continue;
                }
                $filas[$i + 1] = $fila;
            }
        }

        return [
            'hojas' => $hojas,
            'hoja_seleccionada' => (int) ($hojaSeleccionada['indice'] ?? 1),
            'hoja_nombre' => (string) ($hojaSeleccionada['nombre'] ?? ''),
            'fila_encabezado' => $filaEncabezado,
            'fila_encabezado_automatica' => $filaEncabezadoAutomatica,
            'sin_encabezado' => $sinEncabezado,
            'modo_columnas' => $modo,
            'headers' => $headers,
            'mapa' => $mapa,
            'filas' => $filas,
            'advertencias' => $advertencias,
            'csv_delimiter' => $delimiter,
            'csv_separador_form' => AsientoImportColumnasSupport::valorFormularioSeparadorCsv(
                $separadorAutomatico ? 'auto' : $delimiter
            ),
            'csv_separador_automatico' => $separadorAutomatico,
        ];
    }

    /**
     * @param  array<int, mixed>  $fila
     * @param  array<string, int|null>  $mapa
     * @return array<string, mixed>
     */
    private function validarFila(array $fila, array $mapa, int $nroAuto, int $numeroFila): array
    {
        $vtoRaw = SolicitudpagoCuotasImportColumnasSupport::valorCampo($fila, $mapa, 'fecha_vencimiento');
        $montoRaw = SolicitudpagoCuotasImportColumnasSupport::valorCampo($fila, $mapa, 'monto');
        $nroRaw = SolicitudpagoCuotasImportColumnasSupport::valorCampo($fila, $mapa, 'nro_cuota');

        $vtoTxt = trim((string) ($vtoRaw ?? ''));
        $montoTxt = trim((string) ($montoRaw ?? ''));

        if ($vtoTxt === '' && $montoTxt === '' && trim((string) ($nroRaw ?? '')) === '') {
            return [
                'estado' => 'omitida',
                'mensaje' => 'Fila vacía',
                'nro_cuota' => '',
                'fecha_vencimiento' => '',
                'monto' => '',
                'monto_num' => 0,
            ];
        }

        $errores = [];
        if (($mapa['fecha_vencimiento'] ?? null) === null) {
            $errores[] = 'Falta mapear vencimiento';
        }
        if (($mapa['monto'] ?? null) === null) {
            $errores[] = 'Falta mapear monto';
        }

        $fecha = SolicitudpagoCuotasImportColumnasSupport::parseFecha($vtoRaw);
        if ($fecha === null) {
            $errores[] = $vtoTxt === '' ? 'Falta vencimiento' : 'Vencimiento inválido';
        }

        $monto = SolicitudpagoCuotasImportColumnasSupport::parseMonto($montoRaw);
        if ($monto == 0.0) {
            $errores[] = $montoTxt === '' ? 'Falta monto' : 'Monto inválido o cero';
        }

        $nro = (int) $nroRaw;
        if ($nro <= 0) {
            $nro = $nroAuto;
        }

        if ($errores !== []) {
            return [
                'estado' => 'error',
                'mensaje' => implode('; ', $errores),
                'nro_cuota' => $nro > 0 ? (string) $nro : '',
                'fecha_vencimiento' => $fecha ?? $vtoTxt,
                'monto' => $montoTxt,
                'monto_num' => $monto,
            ];
        }

        return [
            'estado' => 'ok',
            'mensaje' => 'OK',
            'nro_cuota' => $nro,
            'fecha_vencimiento' => $fecha,
            'monto' => number_format($monto, 2, ',', '.'),
            'monto_num' => $monto,
        ];
    }

    /**
     * @param  array<int, mixed>  $fila
     */
    private function filaVacia(array $fila): bool
    {
        foreach ($fila as $celda) {
            if (trim((string) ($celda ?? '')) !== '') {
                return false;
            }
        }

        return true;
    }
}
