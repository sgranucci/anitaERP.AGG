<?php

namespace App\Services\Contable;

use App\Models\Configuracion\Moneda;
use App\Models\Contable\Centrocosto;
use App\Models\Contable\Cuentacontable;
use App\Support\Archivo\TextoUtf8Support;
use App\Support\Contable\AsientoCentrocostoObligatorioSupport;
use App\Support\Contable\CuentaCentrocostoHabilitadoSupport;
use App\Support\Contable\AsientoCuentaUsuarioSupport;
use App\Support\Contable\AsientoImportColumnasSupport;
use Illuminate\Http\UploadedFile;

class AsientoImportPreviewService
{
    private const MAX_FILAS_MUESTRA = 40;

    private const TOLERANCIA_BALANCE = 0.009;

    /**
     * @return array<string, mixed>
     */
    public function previsualizar(
        UploadedFile $archivo,
        int $empresaId,
        ?int $monedaDefaultId,
        ?string $colCuenta,
        ?string $colDebe,
        ?string $colHaber,
        ?string $colCentrocosto,
        ?string $colMoneda,
        ?string $colCotizacion,
        ?string $colDetalle,
        ?int $filaEncabezadoManual,
        ?int $hojaIndice1Based = null,
        ?string $modoColumnas = null,
        ?string $modoImporte = null,
        ?string $colMonto = null,
        ?string $colDh = null,
        ?string $csvSeparador = null
    ): array {
        $archivo = TextoUtf8Support::asegurarCsvUtf8($archivo);

        $modoColumnas = AsientoImportColumnasSupport::normalizarModoColumnas($modoColumnas);
        $modoImporte = AsientoImportColumnasSupport::normalizarModoImporte($modoImporte);
        $separadorResuelto = AsientoImportColumnasSupport::resolverSeparadorCsv($archivo, $csvSeparador);
        $separadorCsv = $separadorResuelto['delimiter'];
        $separadorAutomatico = (bool) $separadorResuelto['automatico'];
        $porNumero = $modoColumnas === AsientoImportColumnasSupport::MODO_COLUMNAS_NUMERO;
        $montoDh = $modoImporte === AsientoImportColumnasSupport::MODO_IMPORTE_MONTO_DH;

        $cols = $this->nombresColumnas(
            $colCuenta,
            $colDebe,
            $colHaber,
            $colCentrocosto,
            $colMoneda,
            $colCotizacion,
            $colDetalle,
            $colMonto,
            $colDh,
            $porNumero
        );

        $hojas = AsientoImportColumnasSupport::hojasParaSelector($archivo);
        $hojaIndice0 = AsientoImportColumnasSupport::indiceHojaDesdeRequest($hojaIndice1Based, count($hojas));
        $hojaSeleccionada = $hojas[$hojaIndice0] ?? $hojas[0];

        $hoja = AsientoImportColumnasSupport::leerHojas($archivo, $separadorCsv)[$hojaIndice0] ?? [];
        if ($hoja === []) {
            return $this->anexarMetaHojas([
                'ok' => false,
                'mensaje' => 'La hoja seleccionada ('.($hojaSeleccionada['nombre'] ?? ('#'.$hojaIndice0)).') no tiene filas legibles.',
            ], $hojas, $hojaSeleccionada);
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
            $encabezados = [];
            $advertencias[] = 'Modo sin título: las columnas se leen por número (1 = primera columna) o letra Excel (A, B…). Datos desde la fila '.$filaInicioDatos.'.';
        } else {
            $filaEncabezado = AsientoImportColumnasSupport::detectarFilaEncabezado(
                $archivo,
                $filaEncabezadoManual,
                $hojaIndice0,
                $separadorCsv
            );
            $filaEncabezadoAutomatica = $filaEncabezadoManual === null || $filaEncabezadoManual < 1;
            $indiceEncabezado = $filaEncabezado - 1;
            $encabezados = $hoja[$indiceEncabezado] ?? [];

            if (! is_array($encabezados) || ! AsientoImportColumnasSupport::pareceFilaEncabezado($encabezados)) {
                return $this->anexarMetaHojas([
                    'ok' => false,
                    'mensaje' => 'No se detectó fila de encabezados en la fila '.$filaEncabezado
                        .' de «'.($hojaSeleccionada['nombre'] ?? '').'». Indique la fila, o use «Por número de columna» si el archivo no tiene títulos.',
                    'fila_encabezado' => $filaEncabezado,
                    'modo_columnas' => $modoColumnas,
                    'modo_importe' => $modoImporte,
                ], $hojas, $hojaSeleccionada);
            }
            $indiceInicioDatos = $indiceEncabezado + 1;
        }

        $colCuentaInfo = AsientoImportColumnasSupport::resolverColumnaModo(
            $modoColumnas,
            $encabezados,
            $cols['cuenta'],
            $porNumero
                ? AsientoImportColumnasSupport::COL_CUENTA_NUMERO_DEFAULT
                : AsientoImportColumnasSupport::COL_CUENTA_DEFAULT,
            AsientoImportColumnasSupport::ALIAS_ENCABEZADO_CUENTA
        );
        $colDebeInfo = null;
        $colHaberInfo = null;
        $colMontoInfo = null;
        $colDhInfo = null;

        if ($montoDh) {
            $colMontoInfo = AsientoImportColumnasSupport::resolverColumnaModo(
                $modoColumnas,
                $encabezados,
                $cols['monto'],
                $porNumero
                    ? AsientoImportColumnasSupport::COL_MONTO_NUMERO_DEFAULT
                    : AsientoImportColumnasSupport::COL_MONTO_DEFAULT,
                AsientoImportColumnasSupport::ALIAS_ENCABEZADO_MONTO
            );
            $colDhInfo = AsientoImportColumnasSupport::resolverColumnaModo(
                $modoColumnas,
                $encabezados,
                $cols['dh'],
                $porNumero
                    ? AsientoImportColumnasSupport::COL_DH_NUMERO_DEFAULT
                    : AsientoImportColumnasSupport::COL_DH_DEFAULT,
                AsientoImportColumnasSupport::ALIAS_ENCABEZADO_DH
            );
        } else {
            $colDebeInfo = AsientoImportColumnasSupport::resolverColumnaModo(
                $modoColumnas,
                $encabezados,
                $cols['debe'],
                $porNumero
                    ? AsientoImportColumnasSupport::COL_DEBE_NUMERO_DEFAULT
                    : AsientoImportColumnasSupport::COL_DEBE_DEFAULT,
                AsientoImportColumnasSupport::ALIAS_ENCABEZADO_DEBE
            );
            $colHaberInfo = AsientoImportColumnasSupport::resolverColumnaModo(
                $modoColumnas,
                $encabezados,
                $cols['haber'],
                $porNumero
                    ? AsientoImportColumnasSupport::COL_HABER_NUMERO_DEFAULT
                    : AsientoImportColumnasSupport::COL_HABER_DEFAULT,
                AsientoImportColumnasSupport::ALIAS_ENCABEZADO_HABER
            );
        }

        $colCcInfo = AsientoImportColumnasSupport::resolverColumnaModo(
            $modoColumnas,
            $encabezados,
            $cols['centrocosto'],
            $porNumero
                ? AsientoImportColumnasSupport::COL_CENTROCOSTO_NUMERO_DEFAULT
                : AsientoImportColumnasSupport::COL_CENTROCOSTO_DEFAULT,
            AsientoImportColumnasSupport::ALIAS_ENCABEZADO_CENTROCOSTO
        );
        $colMonedaInfo = AsientoImportColumnasSupport::resolverColumnaModo(
            $modoColumnas,
            $encabezados,
            $cols['moneda'],
            $porNumero
                ? AsientoImportColumnasSupport::COL_MONEDA_NUMERO_DEFAULT
                : AsientoImportColumnasSupport::COL_MONEDA_DEFAULT,
            AsientoImportColumnasSupport::ALIAS_ENCABEZADO_MONEDA
        );
        $colCotizacionInfo = AsientoImportColumnasSupport::resolverColumnaModo(
            $modoColumnas,
            $encabezados,
            $cols['cotizacion'],
            $porNumero
                ? AsientoImportColumnasSupport::COL_COTIZACION_NUMERO_DEFAULT
                : AsientoImportColumnasSupport::COL_COTIZACION_DEFAULT,
            AsientoImportColumnasSupport::ALIAS_ENCABEZADO_COTIZACION
        );
        $colDetalleInfo = AsientoImportColumnasSupport::resolverColumnaModo(
            $modoColumnas,
            $encabezados,
            $cols['detalle'],
            $porNumero
                ? AsientoImportColumnasSupport::COL_DETALLE_NUMERO_DEFAULT
                : AsientoImportColumnasSupport::COL_DETALLE_DEFAULT,
            AsientoImportColumnasSupport::ALIAS_ENCABEZADO_DETALLE
        );

        if ($empresaId <= 0) {
            $advertencias[] = 'Indique la empresa del asiento para resolver las cuentas contables.';
        }
        if ($colCuentaInfo === null) {
            $advertencias[] = 'No se encontró la columna de cuenta («'.$cols['cuenta'].'»), que es obligatoria.';
        }
        if ($montoDh) {
            if ($colMontoInfo === null) {
                $advertencias[] = 'No se encontró la columna de monto («'.$cols['monto'].'»).';
            }
            if ($colDhInfo === null) {
                $advertencias[] = 'No se encontró la columna D/H («'.$cols['dh'].'»).';
            }
        } elseif ($colDebeInfo === null && $colHaberInfo === null) {
            $advertencias[] = 'No se encontró columna Debe ni Haber. Configure al menos una.';
        }
        $monedaDefault = $monedaDefaultId && $monedaDefaultId > 0
            ? Moneda::query()->select('id', 'codigo', 'abreviatura', 'nombre')->find($monedaDefaultId)
            : Moneda::query()->select('id', 'codigo', 'abreviatura', 'nombre')->orderBy('id')->first();

        if ($monedaDefault === null) {
            $advertencias[] = 'No hay moneda por defecto disponible.';
        }

        $importesOk = $montoDh
            ? ($colMontoInfo !== null && $colDhInfo !== null)
            : ($colDebeInfo !== null || $colHaberInfo !== null);
        $columnasOk = $colCuentaInfo !== null
            && $importesOk
            && $empresaId > 0
            && $monedaDefault !== null;

        $resumen = [
            'total_filas_datos' => 0,
            'importables' => 0,
            'sin_importe' => 0,
            'omitidas' => 0,
            'total_debe' => 0.0,
            'total_haber' => 0.0,
            'balanceado' => false,
            'diferencia' => 0.0,
            'cuentas_no_autorizadas' => 0,
        ];
        $filasMuestra = [];
        $filasOmitidas = [];
        $cuentasIdsImportables = [];

        $cacheCuentas = [];
        $cacheCc = [];
        $cacheMonedas = [];

        if ($columnasOk) {
            for ($i = $indiceInicioDatos; $i < count($hoja); $i++) {
                $fila = $hoja[$i] ?? [];
                if (! is_array($fila)) {
                    continue;
                }

                $evaluacion = $this->evaluarFila(
                    $fila,
                    $i + 1,
                    $empresaId,
                    $monedaDefault,
                    $colCuentaInfo,
                    $colDebeInfo,
                    $colHaberInfo,
                    $colCcInfo,
                    $colMonedaInfo,
                    $colCotizacionInfo,
                    $colDetalleInfo,
                    $cacheCuentas,
                    $cacheCc,
                    $cacheMonedas,
                    $modoImporte,
                    $colMontoInfo,
                    $colDhInfo
                );

                if ($evaluacion === null) {
                    continue;
                }

                $resumen['total_filas_datos']++;

                if ($evaluacion['estado'] === 'ok') {
                    $resumen['importables']++;
                    if (($evaluacion['motivo'] ?? '') === 'sin_importe') {
                        $resumen['sin_importe']++;
                    }
                    $resumen['total_debe'] += (float) $evaluacion['debe'];
                    $resumen['total_haber'] += (float) $evaluacion['haber'];
                    $cuentasIdsImportables[] = (int) $evaluacion['cuentacontable_id'];
                    if (count($filasMuestra) < self::MAX_FILAS_MUESTRA) {
                        $filasMuestra[] = $evaluacion;
                    }
                } else {
                    $resumen['omitidas']++;
                    $filasOmitidas[] = $evaluacion;
                }
            }
        }

        $resumen['diferencia'] = round($resumen['total_debe'] - $resumen['total_haber'], 4);
        $resumen['balanceado'] = abs($resumen['diferencia']) <= self::TOLERANCIA_BALANCE;

        $usuarioId = (int) (auth()->id() ?? 0);
        $noAutorizadas = AsientoCuentaUsuarioSupport::cuentasNoAutorizadas($usuarioId, $cuentasIdsImportables);
        $resumen['cuentas_no_autorizadas'] = count($noAutorizadas);
        $requiereAprobacion = $noAutorizadas !== [];

        if ($requiereAprobacion) {
            $advertencias[] = 'Hay '.count($noAutorizadas)
                .' cuenta(s) fuera de su lista autorizada. El asiento quedará pendiente de aprobación.';
        }

        $resumenOmitidas = $this->resumirFilasOmitidas($filasOmitidas);
        $desbalancePorOmitidas = $columnasOk
            && ! $resumen['balanceado']
            && $this->desbalanceExplicadoPorOmitidas($resumen['diferencia'], $resumenOmitidas);
        $sinCentrocostoObligatorio = 0;
        foreach ($filasOmitidas as $filaOmitida) {
            if (($filaOmitida['motivo'] ?? '') === 'centrocosto_obligatorio') {
                $sinCentrocostoObligatorio++;
            }
        }

        if ($sinCentrocostoObligatorio > 0) {
            $advertencias[] = 'Hay '.$sinCentrocostoObligatorio
                .' fila(s) de cuentas que manejan centro de costo y el Excel no lo informa. Esas imputaciones no se cargan.';
        }
        $centroNoHabilitado = 0;
        foreach ($filasOmitidas as $filaOmitida) {
            if (($filaOmitida['motivo'] ?? '') === 'centrocosto_no_habilitado') {
                $centroNoHabilitado++;
            }
        }
        if ($centroNoHabilitado > 0) {
            $advertencias[] = 'Hay '.$centroNoHabilitado
                .' fila(s) con un centro de costo que la cuenta no tiene habilitado. Esas imputaciones no se cargan.';
        }

        if ($filasOmitidas !== []) {
            $advertencias[] = $resumenOmitidas['resumen'];
        }

        if ($resumen['sin_importe'] > 0) {
            $advertencias[] = 'Hay '.$resumen['sin_importe']
                .' fila(s) sin importe. Se cargan en el asiento para completarlas en la edición.';
        }

        if ($columnasOk && $resumen['importables'] > 0 && ! $resumen['balanceado']) {
            if ($desbalancePorOmitidas) {
                $advertencias[] = 'El Excel puede estar balanceado: la diferencia '
                    .AsientoImportColumnasSupport::formatearImporte(abs($resumen['diferencia']))
                    .' coincide con el neto de las filas que no entran (Debe '
                    .$resumenOmitidas['debe_texto']
                    .' menos Haber '
                    .$resumenOmitidas['haber_texto']
                    .'). Se graba igual y se abre la edición. No se envía a Anita hasta que cierre.';
            } else {
                $advertencias[] = 'El asiento no balancea: Debe '
                    .AsientoImportColumnasSupport::formatearImporte($resumen['total_debe'])
                    .' vs Haber '
                    .AsientoImportColumnasSupport::formatearImporte($resumen['total_haber'])
                    .' (diferencia '
                    .AsientoImportColumnasSupport::formatearImporte(abs($resumen['diferencia']))
                    .'). Se graba igual y se abre la edición. No se envía a Anita hasta que cierre.';
            }
        }

        if ($columnasOk && $resumen['importables'] < 2 && $resumen['importables'] > 0) {
            $advertencias[] = 'Un asiento necesita al menos dos movimientos importables.';
        }

        $ok = $columnasOk
            && $sinCentrocostoObligatorio === 0
            && $centroNoHabilitado === 0
            && $resumen['importables'] >= 2;

        $metaColumnas = [
            'cuenta' => $this->metaColumna($cols['cuenta'], $colCuentaInfo, true),
            'centrocosto' => $this->metaColumna($cols['centrocosto'], $colCcInfo, false),
            'moneda' => $this->metaColumna($cols['moneda'], $colMonedaInfo, false),
            'cotizacion' => $this->metaColumna($cols['cotizacion'], $colCotizacionInfo, false),
            'detalle' => $this->metaColumna($cols['detalle'], $colDetalleInfo, false),
        ];
        if ($montoDh) {
            $metaColumnas['monto'] = $this->metaColumna($cols['monto'], $colMontoInfo, true);
            $metaColumnas['dh'] = $this->metaColumna($cols['dh'], $colDhInfo, true);
            $metaColumnas['debe'] = $this->metaColumna($cols['debe'], null, false);
            $metaColumnas['haber'] = $this->metaColumna($cols['haber'], null, false);
        } else {
            $metaColumnas['debe'] = $this->metaColumna($cols['debe'], $colDebeInfo, true);
            $metaColumnas['haber'] = $this->metaColumna($cols['haber'], $colHaberInfo, true);
            $metaColumnas['monto'] = $this->metaColumna($cols['monto'], null, false);
            $metaColumnas['dh'] = $this->metaColumna($cols['dh'], null, false);
        }

        return $this->anexarMetaHojas([
            'ok' => $ok,
            'fila_encabezado' => $filaEncabezado,
            'fila_inicio_datos' => $indiceInicioDatos + 1,
            'fila_encabezado_automatica' => $filaEncabezadoAutomatica,
            'sin_encabezado' => $sinEncabezado,
            'modo_columnas' => $modoColumnas,
            'modo_importe' => $modoImporte,
            'csv_separador' => AsientoImportColumnasSupport::valorFormularioSeparadorCsv(
                $separadorAutomatico
                    ? AsientoImportColumnasSupport::SEPARADOR_CSV_AUTO
                    : $separadorCsv
            ),
            'csv_separador_detectado' => AsientoImportColumnasSupport::valorFormularioSeparadorCsv($separadorCsv),
            'csv_separador_automatico' => $separadorAutomatico,
            'csv_separador_etiqueta' => $separadorAutomatico
                ? ('Automático → '.AsientoImportColumnasSupport::etiquetaSeparadorCsv($separadorCsv))
                : AsientoImportColumnasSupport::etiquetaSeparadorCsv($separadorCsv),
            'empresa_id' => $empresaId,
            'moneda_default' => $monedaDefault ? [
                'id' => (int) $monedaDefault->id,
                'codigo' => (string) $monedaDefault->codigo,
                'abreviatura' => (string) $monedaDefault->abreviatura,
                'nombre' => (string) $monedaDefault->nombre,
            ] : null,
            'columnas' => $metaColumnas,
            'resumen' => [
                'total_filas_datos' => $resumen['total_filas_datos'],
                'importables' => $resumen['importables'],
                'sin_importe' => $resumen['sin_importe'],
                'omitidas' => $resumen['omitidas'],
                'total_debe' => round($resumen['total_debe'], 4),
                'total_haber' => round($resumen['total_haber'], 4),
                'total_debe_texto' => AsientoImportColumnasSupport::formatearImporte($resumen['total_debe']),
                'total_haber_texto' => AsientoImportColumnasSupport::formatearImporte($resumen['total_haber']),
                'diferencia' => $resumen['diferencia'],
                'diferencia_texto' => AsientoImportColumnasSupport::formatearImporte(abs($resumen['diferencia'])),
                'balanceado' => $resumen['balanceado'],
                'desbalance_por_filas_omitidas' => $desbalancePorOmitidas,
                'omitido_debe_texto' => $resumenOmitidas['debe_texto'],
                'omitido_haber_texto' => $resumenOmitidas['haber_texto'],
                'cuentas_no_autorizadas' => $resumen['cuentas_no_autorizadas'],
            ],
            'requiere_aprobacion' => $requiereAprobacion,
            'cuentas_no_autorizadas_detalle' => AsientoCuentaUsuarioSupport::detalleCuentas($noAutorizadas),
            'filas' => $filasMuestra,
            'filas_error' => $filasOmitidas,
            'filas_omitidas' => count($filasOmitidas),
            'hay_mas_filas' => $resumen['importables'] > count($filasMuestra),
            'advertencias' => $advertencias,
            'mensaje' => $ok
                ? null
                : ($sinCentrocostoObligatorio > 0
                    ? 'Complete el centro de costo en las cuentas que lo manejan antes de importar.'
                    : ($columnasOk
                        ? ($resumen['importables'] < 2
                            ? 'Se necesitan al menos dos movimientos válidos para armar el asiento.'
                            : 'No hay filas importables con la configuración actual.')
                        : 'Configure empresa, columna de cuenta e importes (Debe/Haber o monto + D/H) antes de importar.')),
        ], $hojas, $hojaSeleccionada);
    }

    /**
     * @param  array<int, mixed>  $fila
     * @param  array{indice: int, titulo: string, clave_normalizada: string}|null  $colCuentaInfo
     * @param  array{indice: int, titulo: string, clave_normalizada: string}|null  $colDebeInfo
     * @param  array{indice: int, titulo: string, clave_normalizada: string}|null  $colHaberInfo
     * @param  array{indice: int, titulo: string, clave_normalizada: string}|null  $colCcInfo
     * @param  array{indice: int, titulo: string, clave_normalizada: string}|null  $colMonedaInfo
     * @param  array{indice: int, titulo: string, clave_normalizada: string}|null  $colCotizacionInfo
     * @param  array{indice: int, titulo: string, clave_normalizada: string}|null  $colDetalleInfo
     * @param  array<string, ?Cuentacontable>  $cacheCuentas
     * @param  array<string, ?Centrocosto>  $cacheCc
     * @param  array<string, ?Moneda>  $cacheMonedas
     * @return array<string, mixed>|null
     */
    public function evaluarFila(
        array $fila,
        int $filaExcel,
        int $empresaId,
        Moneda $monedaDefault,
        ?array $colCuentaInfo,
        ?array $colDebeInfo,
        ?array $colHaberInfo,
        ?array $colCcInfo,
        ?array $colMonedaInfo,
        ?array $colCotizacionInfo,
        ?array $colDetalleInfo,
        array &$cacheCuentas,
        array &$cacheCc,
        array &$cacheMonedas,
        string $modoImporte = AsientoImportColumnasSupport::MODO_IMPORTE_DEBE_HABER,
        ?array $colMontoInfo = null,
        ?array $colDhInfo = null
    ): ?array {
        $modoImporte = AsientoImportColumnasSupport::normalizarModoImporte($modoImporte);
        $montoDh = $modoImporte === AsientoImportColumnasSupport::MODO_IMPORTE_MONTO_DH;

        $codigoCuenta = AsientoImportColumnasSupport::normalizarCodigoCuenta(
            AsientoImportColumnasSupport::valorCeldaFila($fila, $colCuentaInfo)
        );

        $dhTexto = '';
        $dh = null;
        $montoRaw = null;
        if ($montoDh) {
            $montoRaw = $colMontoInfo
                ? AsientoImportColumnasSupport::normalizarImporte(
                    AsientoImportColumnasSupport::valorCeldaFila($fila, $colMontoInfo)
                )
                : null;
            $dhTexto = $colDhInfo
                ? AsientoImportColumnasSupport::normalizarTextoCelda(
                    AsientoImportColumnasSupport::valorCeldaFila($fila, $colDhInfo)
                )
                : '';
            $dh = $dhTexto !== '' ? AsientoImportColumnasSupport::normalizarDh($dhTexto) : null;
            $importeAbs = $montoRaw !== null ? abs($montoRaw) : 0.0;
            $debe = ($dh === 'D' && $importeAbs > 0) ? $importeAbs : 0.0;
            $haber = ($dh === 'H' && $importeAbs > 0) ? $importeAbs : 0.0;
        } else {
            $debe = $colDebeInfo
                ? AsientoImportColumnasSupport::normalizarImporte(
                    AsientoImportColumnasSupport::valorCeldaFila($fila, $colDebeInfo)
                )
                : null;
            $haber = $colHaberInfo
                ? AsientoImportColumnasSupport::normalizarImporte(
                    AsientoImportColumnasSupport::valorCeldaFila($fila, $colHaberInfo)
                )
                : null;
            $debe = $debe !== null && $debe > 0 ? $debe : 0.0;
            $haber = $haber !== null && $haber > 0 ? $haber : 0.0;
        }

        $codigoCc = $colCcInfo
            ? AsientoImportColumnasSupport::normalizarCodigoCuenta(
                AsientoImportColumnasSupport::valorCeldaFila($fila, $colCcInfo)
            )
            : '';
        $monedaTexto = $colMonedaInfo
            ? AsientoImportColumnasSupport::normalizarTextoCelda(
                AsientoImportColumnasSupport::valorCeldaFila($fila, $colMonedaInfo)
            )
            : '';
        $cotizacion = $colCotizacionInfo
            ? AsientoImportColumnasSupport::normalizarImporte(
                AsientoImportColumnasSupport::valorCeldaFila($fila, $colCotizacionInfo)
            )
            : null;
        $detalle = $colDetalleInfo
            ? AsientoImportColumnasSupport::normalizarTextoCelda(
                AsientoImportColumnasSupport::valorCeldaFila($fila, $colDetalleInfo)
            )
            : '';

        if ($codigoCuenta === '' && $debe <= 0 && $haber <= 0 && $detalle === '' && $codigoCc === ''
            && (! $montoDh || (($montoRaw === null || $montoRaw == 0) && $dhTexto === ''))) {
            return null;
        }

        $base = [
            'fila_excel' => $filaExcel,
            'codigo_cuenta' => $codigoCuenta,
            'cuenta_nombre' => '',
            'cuentacontable_id' => null,
            'codigo_centrocosto' => $codigoCc,
            'centrocosto_id' => null,
            'centrocosto_nombre' => '',
            'moneda_texto' => $monedaTexto,
            'moneda_id' => (int) $monedaDefault->id,
            'moneda_abreviatura' => (string) $monedaDefault->abreviatura,
            'debe' => $debe,
            'haber' => $haber,
            'debe_texto' => $debe > 0 ? AsientoImportColumnasSupport::formatearImporte($debe) : '',
            'haber_texto' => $haber > 0 ? AsientoImportColumnasSupport::formatearImporte($haber) : '',
            'dh' => $dh,
            'dh_texto' => $dhTexto,
            'cotizacion' => $cotizacion ?? 0.0,
            'detalle' => $detalle,
            'estado' => 'omitido',
            'mensaje' => '',
        ];

        if ($codigoCuenta === '') {
            $base['mensaje'] = 'Sin código de cuenta';

            return $base;
        }

        $sinImporte = false;
        if ($montoDh) {
            if ($montoRaw === null || abs($montoRaw) <= 0) {
                $sinImporte = true;
                if ($dhTexto !== '' && $dh === null) {
                    $base['mensaje'] = 'D/H inválido («'.$dhTexto.'»). Use D o H';

                    return $base;
                }
            } elseif ($dhTexto === '') {
                $base['mensaje'] = 'Sin D/H en la fila';

                return $base;
            } elseif ($dh === null) {
                $base['mensaje'] = 'D/H inválido («'.$dhTexto.'»). Use D o H';

                return $base;
            }
        } elseif ($debe <= 0 && $haber <= 0) {
            $sinImporte = true;
        }

        if ($debe > 0 && $haber > 0) {
            $base['mensaje'] = 'La fila tiene Debe y Haber a la vez';

            return $base;
        }

        $cuenta = $this->resolverCuenta($empresaId, $codigoCuenta, $cacheCuentas);
        if ($cuenta === null) {
            $base['mensaje'] = 'Cuenta no encontrada en la empresa';

            return $base;
        }

        $base['cuentacontable_id'] = (int) $cuenta->id;
        $base['cuenta_nombre'] = (string) $cuenta->nombre;
        $base['codigo_cuenta'] = (string) $cuenta->codigo;

        if ($codigoCc !== '') {
            $cc = $this->resolverCentrocosto($codigoCc, $cacheCc);
            if ($cc === null) {
                if (! $sinImporte) {
                    $base['mensaje'] = 'Centro de costo «'.$codigoCc.'» no existe';

                    return $base;
                }
            } else {
                $base['centrocosto_id'] = (int) $cc->id;
                $base['centrocosto_nombre'] = (string) $cc->nombre;
                $noHabilitado = CuentaCentrocostoHabilitadoSupport::mensaje((int) $cuenta->id, (int) $cc->id);
                if ($noHabilitado !== null && ! $sinImporte) {
                    $base['mensaje'] = $noHabilitado;
                    $base['motivo'] = 'centrocosto_no_habilitado';

                    return $base;
                }
            }
        } elseif (! $sinImporte && AsientoCentrocostoObligatorioSupport::maneja($cuenta->manejaccosto ?? null)) {
            $base['mensaje'] = 'La cuenta maneja centro de costo y el Excel no lo informa';
            $base['motivo'] = 'centrocosto_obligatorio';

            return $base;
        }

        if ($monedaTexto !== '') {
            $moneda = $this->resolverMoneda($monedaTexto, $cacheMonedas);
            if ($moneda === null) {
                $base['mensaje'] = 'Moneda «'.$monedaTexto.'» no existe';

                return $base;
            }
            $base['moneda_id'] = (int) $moneda->id;
            $base['moneda_abreviatura'] = (string) $moneda->abreviatura;
        }

        $base['estado'] = 'ok';
        if ($sinImporte) {
            $base['motivo'] = 'sin_importe';
            $base['mensaje'] = 'Sin importe: se carga para completar';
        } else {
            $base['mensaje'] = 'Listo para cargar';
        }

        return $base;
    }

    /**
     * Texto de cada fila que no entra al asiento, con cuenta, centro de costo e importe.
     *
     * @param  list<array<string, mixed>>  $filasOmitidas
     * @return array{
     *     cantidad: int,
     *     debe: float,
     *     haber: float,
     *     debe_texto: string,
     *     haber_texto: string,
     *     resumen: string,
     *     lineas: list<string>
     * }
     */
    public function resumirFilasOmitidas(array $filasOmitidas): array
    {
        $sumaDebe = 0.0;
        $sumaHaber = 0.0;
        $lineas = [];
        foreach ($filasOmitidas as $fila) {
            $debe = (float) ($fila['debe'] ?? 0);
            $haber = (float) ($fila['haber'] ?? 0);
            $sumaDebe += $debe;
            $sumaHaber += $haber;
            $lineas[] = $this->lineaErrorFila($fila, $debe, $haber);
        }

        $debeTexto = AsientoImportColumnasSupport::formatearImporte($sumaDebe);
        $haberTexto = AsientoImportColumnasSupport::formatearImporte($sumaHaber);
        $cantidad = count($filasOmitidas);

        return [
            'cantidad' => $cantidad,
            'debe' => round($sumaDebe, 4),
            'haber' => round($sumaHaber, 4),
            'debe_texto' => $debeTexto,
            'haber_texto' => $haberTexto,
            'resumen' => $cantidad === 0
                ? ''
                : 'Hay '.$cantidad.' fila(s) que no entran al asiento'
                    .' (Debe omitido '.$debeTexto.', Haber omitido '.$haberTexto.').',
            'lineas' => $lineas,
        ];
    }

    /**
     * El Debe/Haber importable difiere justo en el neto de las filas omitidas:
     * el Excel cierra y el sistema deja afuera esos importes.
     *
     * @param  array{debe: float, haber: float, cantidad: int}  $resumenOmitidas
     */
    public function desbalanceExplicadoPorOmitidas(float $diferenciaImportable, array $resumenOmitidas): bool
    {
        if (($resumenOmitidas['cantidad'] ?? 0) < 1) {
            return false;
        }

        $netoOmitido = round((float) $resumenOmitidas['haber'] - (float) $resumenOmitidas['debe'], 4);

        return abs(round($diferenciaImportable, 4) - $netoOmitido) <= self::TOLERANCIA_BALANCE;
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    public function lineaErrorFila(array $fila, ?float $debe = null, ?float $haber = null): string
    {
        $debe = $debe ?? (float) ($fila['debe'] ?? 0);
        $haber = $haber ?? (float) ($fila['haber'] ?? 0);
        $texto = 'Fila '.(int) ($fila['fila_excel'] ?? 0);
        $cuenta = trim((string) ($fila['codigo_cuenta'] ?? ''));
        if ($cuenta !== '') {
            $texto .= ', cuenta '.$cuenta;
        }
        $cc = trim((string) ($fila['codigo_centrocosto'] ?? ''));
        if ($cc !== '') {
            $texto .= ', centro de costo '.$cc;
        }
        $texto .= ': '.((string) ($fila['mensaje'] ?? 'omitida'));
        if ($debe > 0) {
            $texto .= ' (Debe '.AsientoImportColumnasSupport::formatearImporte($debe).')';
        }
        if ($haber > 0) {
            $texto .= ' (Haber '.AsientoImportColumnasSupport::formatearImporte($haber).')';
        }

        return $texto;
    }

    /**
     * @param  array<string, ?Cuentacontable>  $cache
     */
    private function resolverCuenta(int $empresaId, string $codigo, array &$cache): ?Cuentacontable
    {
        $clave = $empresaId.'|'.$codigo;
        if (array_key_exists($clave, $cache)) {
            return $cache[$clave];
        }

        $cuenta = Cuentacontable::query()
            ->where('empresa_id', $empresaId)
            ->where('codigo', $codigo)
            ->first();

        if ($cuenta === null && ctype_digit($codigo)) {
            $cuenta = Cuentacontable::query()
                ->where('empresa_id', $empresaId)
                ->where('codigo', ltrim($codigo, '0') ?: '0')
                ->first();
        }

        $cache[$clave] = $cuenta;

        return $cuenta;
    }

    /**
     * @param  array<string, ?Centrocosto>  $cache
     */
    private function resolverCentrocosto(string $codigo, array &$cache): ?Centrocosto
    {
        if (array_key_exists($codigo, $cache)) {
            return $cache[$codigo];
        }

        $cc = Centrocosto::query()->where('codigo', $codigo)->first();
        if ($cc === null && ctype_digit($codigo)) {
            $cc = Centrocosto::query()->where('codigo', ltrim($codigo, '0') ?: '0')->first();
        }

        $cache[$codigo] = $cc;

        return $cc;
    }

    /**
     * @param  array<string, ?Moneda>  $cache
     */
    private function resolverMoneda(string $texto, array &$cache): ?Moneda
    {
        $clave = mb_strtolower(trim($texto));
        if (array_key_exists($clave, $cache)) {
            return $cache[$clave];
        }

        $moneda = Moneda::query()
            ->where(function ($q) use ($texto, $clave) {
                $q->where('codigo', $texto)
                    ->orWhere('abreviatura', $texto)
                    ->orWhereRaw('LOWER(abreviatura) = ?', [$clave])
                    ->orWhereRaw('LOWER(nombre) = ?', [$clave]);
            })
            ->first();

        $cache[$clave] = $moneda;

        return $moneda;
    }

    /**
     * @return array{cuenta: string, debe: string, haber: string, monto: string, dh: string, centrocosto: string, moneda: string, cotizacion: string, detalle: string}
     */
    private function nombresColumnas(
        ?string $colCuenta,
        ?string $colDebe,
        ?string $colHaber,
        ?string $colCentrocosto,
        ?string $colMoneda,
        ?string $colCotizacion,
        ?string $colDetalle,
        ?string $colMonto = null,
        ?string $colDh = null,
        bool $porNumero = false
    ): array {
        return [
            'cuenta' => $this->nombreColumna(
                $colCuenta,
                $porNumero
                    ? AsientoImportColumnasSupport::COL_CUENTA_NUMERO_DEFAULT
                    : AsientoImportColumnasSupport::COL_CUENTA_DEFAULT
            ),
            'debe' => $this->nombreColumna(
                $colDebe,
                $porNumero
                    ? AsientoImportColumnasSupport::COL_DEBE_NUMERO_DEFAULT
                    : AsientoImportColumnasSupport::COL_DEBE_DEFAULT
            ),
            'haber' => $this->nombreColumna(
                $colHaber,
                $porNumero
                    ? AsientoImportColumnasSupport::COL_HABER_NUMERO_DEFAULT
                    : AsientoImportColumnasSupport::COL_HABER_DEFAULT
            ),
            'monto' => $this->nombreColumna(
                $colMonto,
                $porNumero
                    ? AsientoImportColumnasSupport::COL_MONTO_NUMERO_DEFAULT
                    : AsientoImportColumnasSupport::COL_MONTO_DEFAULT
            ),
            'dh' => $this->nombreColumna(
                $colDh,
                $porNumero
                    ? AsientoImportColumnasSupport::COL_DH_NUMERO_DEFAULT
                    : AsientoImportColumnasSupport::COL_DH_DEFAULT
            ),
            'centrocosto' => $this->nombreColumna(
                $colCentrocosto,
                $porNumero
                    ? AsientoImportColumnasSupport::COL_CENTROCOSTO_NUMERO_DEFAULT
                    : AsientoImportColumnasSupport::COL_CENTROCOSTO_DEFAULT
            ),
            'moneda' => $this->nombreColumna(
                $colMoneda,
                $porNumero
                    ? AsientoImportColumnasSupport::COL_MONEDA_NUMERO_DEFAULT
                    : AsientoImportColumnasSupport::COL_MONEDA_DEFAULT
            ),
            'cotizacion' => $this->nombreColumna(
                $colCotizacion,
                $porNumero
                    ? AsientoImportColumnasSupport::COL_COTIZACION_NUMERO_DEFAULT
                    : AsientoImportColumnasSupport::COL_COTIZACION_DEFAULT
            ),
            'detalle' => $this->nombreColumna(
                $colDetalle,
                $porNumero
                    ? AsientoImportColumnasSupport::COL_DETALLE_NUMERO_DEFAULT
                    : AsientoImportColumnasSupport::COL_DETALLE_DEFAULT
            ),
        ];
    }

    private function nombreColumna(?string $valor, string $default): string
    {
        $valor = trim((string) $valor);
        if ($valor !== '') {
            return $valor;
        }

        return trim($default);
    }

    /**
     * @param  array{indice: int, titulo: string, clave_normalizada: string}|null  $info
     * @return array{configurado: string, encontrada: bool, requerida: bool, titulo: ?string}
     */
    private function metaColumna(string $configurado, ?array $info, bool $requerida): array
    {
        return [
            'configurado' => $configurado,
            'encontrada' => $info !== null,
            'requerida' => $requerida,
            'titulo' => $info['titulo'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $preview
     * @param  list<array{indice: int, nombre: string}>  $hojas
     * @param  array{indice: int, nombre: string}  $hojaSeleccionada
     * @return array<string, mixed>
     */
    private function anexarMetaHojas(array $preview, array $hojas, array $hojaSeleccionada): array
    {
        $preview['hojas'] = $hojas;
        $preview['multiple_hojas'] = count($hojas) > 1;
        $preview['hoja_seleccionada'] = (int) $hojaSeleccionada['indice'];
        $preview['hoja_nombre'] = (string) $hojaSeleccionada['nombre'];

        if ($preview['multiple_hojas']) {
            $preview['advertencias'] = array_values(array_merge(
                [
                    'El archivo tiene '.count($hojas).' hojas. Elija cuál importar (por defecto hoja 1).',
                ],
                $preview['advertencias'] ?? []
            ));
        }

        return $preview;
    }
}
