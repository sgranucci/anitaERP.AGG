<?php

namespace App\Services\Contable;

use App\Imports\Contable\AsientoImportLecturaCruda;
use App\Models\Configuracion\Moneda;
use App\Models\Contable\Centrocosto;
use App\Models\Contable\Cuentacontable;
use App\Support\Contable\AsientoCuentaUsuarioSupport;
use App\Support\Contable\AsientoImportColumnasSupport;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;

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
        ?int $hojaIndice1Based = null
    ): array {
        $cols = $this->nombresColumnas(
            $colCuenta,
            $colDebe,
            $colHaber,
            $colCentrocosto,
            $colMoneda,
            $colCotizacion,
            $colDetalle
        );

        $hojas = AsientoImportColumnasSupport::hojasParaSelector($archivo);
        $hojaIndice0 = AsientoImportColumnasSupport::indiceHojaDesdeRequest($hojaIndice1Based, count($hojas));
        $hojaSeleccionada = $hojas[$hojaIndice0] ?? $hojas[0];

        $hoja = Excel::toArray(new AsientoImportLecturaCruda(), $archivo)[$hojaIndice0] ?? [];
        if ($hoja === []) {
            return $this->anexarMetaHojas([
                'ok' => false,
                'mensaje' => 'La hoja seleccionada ('.($hojaSeleccionada['nombre'] ?? ('#'.$hojaIndice0)).') no tiene filas legibles.',
            ], $hojas, $hojaSeleccionada);
        }

        $filaEncabezado = AsientoImportColumnasSupport::detectarFilaEncabezado(
            $archivo,
            $filaEncabezadoManual,
            $hojaIndice0
        );
        $indiceEncabezado = $filaEncabezado - 1;
        $encabezados = $hoja[$indiceEncabezado] ?? [];

        if (! is_array($encabezados) || ! AsientoImportColumnasSupport::pareceFilaEncabezado($encabezados)) {
            return $this->anexarMetaHojas([
                'ok' => false,
                'mensaje' => 'No se detectó fila de encabezados en la fila '.$filaEncabezado
                    .' de «'.($hojaSeleccionada['nombre'] ?? '').'». Revise el archivo o indique la fila manualmente.',
                'fila_encabezado' => $filaEncabezado,
            ], $hojas, $hojaSeleccionada);
        }

        $colCuentaInfo = AsientoImportColumnasSupport::resolverColumna(
            $encabezados,
            $cols['cuenta'],
            AsientoImportColumnasSupport::COL_CUENTA_DEFAULT,
            AsientoImportColumnasSupport::ALIAS_ENCABEZADO_CUENTA
        );
        $colDebeInfo = AsientoImportColumnasSupport::resolverColumna(
            $encabezados,
            $cols['debe'],
            AsientoImportColumnasSupport::COL_DEBE_DEFAULT,
            AsientoImportColumnasSupport::ALIAS_ENCABEZADO_DEBE
        );
        $colHaberInfo = AsientoImportColumnasSupport::resolverColumna(
            $encabezados,
            $cols['haber'],
            AsientoImportColumnasSupport::COL_HABER_DEFAULT,
            AsientoImportColumnasSupport::ALIAS_ENCABEZADO_HABER
        );
        $colCcInfo = AsientoImportColumnasSupport::resolverColumna(
            $encabezados,
            $cols['centrocosto'],
            AsientoImportColumnasSupport::COL_CENTROCOSTO_DEFAULT,
            AsientoImportColumnasSupport::ALIAS_ENCABEZADO_CENTROCOSTO
        );
        $colMonedaInfo = AsientoImportColumnasSupport::resolverColumna(
            $encabezados,
            $cols['moneda'],
            AsientoImportColumnasSupport::COL_MONEDA_DEFAULT,
            AsientoImportColumnasSupport::ALIAS_ENCABEZADO_MONEDA
        );
        $colCotizacionInfo = AsientoImportColumnasSupport::resolverColumna(
            $encabezados,
            $cols['cotizacion'],
            AsientoImportColumnasSupport::COL_COTIZACION_DEFAULT,
            AsientoImportColumnasSupport::ALIAS_ENCABEZADO_COTIZACION
        );
        $colDetalleInfo = AsientoImportColumnasSupport::resolverColumna(
            $encabezados,
            $cols['detalle'],
            AsientoImportColumnasSupport::COL_DETALLE_DEFAULT,
            AsientoImportColumnasSupport::ALIAS_ENCABEZADO_DETALLE
        );

        $advertencias = [];
        if ($empresaId <= 0) {
            $advertencias[] = 'Indique la empresa del asiento para resolver las cuentas contables.';
        }
        if ($colCuentaInfo === null) {
            $advertencias[] = 'No se encontró la columna de cuenta («'.$cols['cuenta'].'»), que es obligatoria.';
        }
        if ($colDebeInfo === null && $colHaberInfo === null) {
            $advertencias[] = 'No se encontró columna Debe ni Haber. Configure al menos una.';
        }

        $monedaDefault = $monedaDefaultId && $monedaDefaultId > 0
            ? Moneda::query()->select('id', 'codigo', 'abreviatura', 'nombre')->find($monedaDefaultId)
            : Moneda::query()->select('id', 'codigo', 'abreviatura', 'nombre')->orderBy('id')->first();

        if ($monedaDefault === null) {
            $advertencias[] = 'No hay moneda por defecto disponible.';
        }

        $columnasOk = $colCuentaInfo !== null
            && ($colDebeInfo !== null || $colHaberInfo !== null)
            && $empresaId > 0
            && $monedaDefault !== null;

        $resumen = [
            'total_filas_datos' => 0,
            'importables' => 0,
            'omitidas' => 0,
            'total_debe' => 0.0,
            'total_haber' => 0.0,
            'balanceado' => false,
            'diferencia' => 0.0,
            'cuentas_no_autorizadas' => 0,
        ];
        $filasMuestra = [];
        $cuentasIdsImportables = [];

        $cacheCuentas = [];
        $cacheCc = [];
        $cacheMonedas = [];

        if ($columnasOk) {
            for ($i = $indiceEncabezado + 1; $i < count($hoja); $i++) {
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
                    $cacheMonedas
                );

                if ($evaluacion === null) {
                    continue;
                }

                $resumen['total_filas_datos']++;

                if ($evaluacion['estado'] === 'ok') {
                    $resumen['importables']++;
                    $resumen['total_debe'] += (float) $evaluacion['debe'];
                    $resumen['total_haber'] += (float) $evaluacion['haber'];
                    $cuentasIdsImportables[] = (int) $evaluacion['cuentacontable_id'];
                } else {
                    $resumen['omitidas']++;
                }

                if (count($filasMuestra) < self::MAX_FILAS_MUESTRA) {
                    $filasMuestra[] = $evaluacion;
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

        if ($columnasOk && $resumen['importables'] > 0 && ! $resumen['balanceado']) {
            $advertencias[] = 'El asiento no balancea: Debe '
                .AsientoImportColumnasSupport::formatearImporte($resumen['total_debe'])
                .' vs Haber '
                .AsientoImportColumnasSupport::formatearImporte($resumen['total_haber'])
                .' (diferencia '
                .AsientoImportColumnasSupport::formatearImporte(abs($resumen['diferencia']))
                .').';
        }

        if ($columnasOk && $resumen['importables'] < 2 && $resumen['importables'] > 0) {
            $advertencias[] = 'Un asiento necesita al menos dos movimientos importables.';
        }

        $ok = $columnasOk
            && $resumen['importables'] >= 2
            && $resumen['balanceado'];

        return $this->anexarMetaHojas([
            'ok' => $ok,
            'fila_encabezado' => $filaEncabezado,
            'fila_encabezado_automatica' => $filaEncabezadoManual === null || $filaEncabezadoManual < 1,
            'empresa_id' => $empresaId,
            'moneda_default' => $monedaDefault ? [
                'id' => (int) $monedaDefault->id,
                'codigo' => (string) $monedaDefault->codigo,
                'abreviatura' => (string) $monedaDefault->abreviatura,
                'nombre' => (string) $monedaDefault->nombre,
            ] : null,
            'columnas' => [
                'cuenta' => $this->metaColumna($cols['cuenta'], $colCuentaInfo, true),
                'debe' => $this->metaColumna($cols['debe'], $colDebeInfo, true),
                'haber' => $this->metaColumna($cols['haber'], $colHaberInfo, true),
                'centrocosto' => $this->metaColumna($cols['centrocosto'], $colCcInfo, false),
                'moneda' => $this->metaColumna($cols['moneda'], $colMonedaInfo, false),
                'cotizacion' => $this->metaColumna($cols['cotizacion'], $colCotizacionInfo, false),
                'detalle' => $this->metaColumna($cols['detalle'], $colDetalleInfo, false),
            ],
            'resumen' => [
                'total_filas_datos' => $resumen['total_filas_datos'],
                'importables' => $resumen['importables'],
                'omitidas' => $resumen['omitidas'],
                'total_debe' => round($resumen['total_debe'], 4),
                'total_haber' => round($resumen['total_haber'], 4),
                'total_debe_texto' => AsientoImportColumnasSupport::formatearImporte($resumen['total_debe']),
                'total_haber_texto' => AsientoImportColumnasSupport::formatearImporte($resumen['total_haber']),
                'diferencia' => $resumen['diferencia'],
                'diferencia_texto' => AsientoImportColumnasSupport::formatearImporte(abs($resumen['diferencia'])),
                'balanceado' => $resumen['balanceado'],
                'cuentas_no_autorizadas' => $resumen['cuentas_no_autorizadas'],
            ],
            'requiere_aprobacion' => $requiereAprobacion,
            'cuentas_no_autorizadas_detalle' => AsientoCuentaUsuarioSupport::detalleCuentas($noAutorizadas),
            'filas' => $filasMuestra,
            'hay_mas_filas' => $resumen['total_filas_datos'] > count($filasMuestra),
            'advertencias' => $advertencias,
            'mensaje' => $ok
                ? null
                : ($columnasOk
                    ? ($resumen['importables'] < 2
                        ? 'Se necesitan al menos dos movimientos válidos para armar el asiento.'
                        : (! $resumen['balanceado']
                            ? 'Corrija el desbalance Debe/Haber antes de importar.'
                            : 'No hay filas importables con la configuración actual.'))
                    : 'Configure empresa, columna de cuenta y Debe/Haber antes de importar.'),
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
        array &$cacheMonedas
    ): ?array {
        $codigoCuenta = AsientoImportColumnasSupport::normalizarCodigoCuenta(
            AsientoImportColumnasSupport::valorCeldaFila($fila, $colCuentaInfo)
        );
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

        $debe = $debe !== null && $debe > 0 ? $debe : 0.0;
        $haber = $haber !== null && $haber > 0 ? $haber : 0.0;

        if ($codigoCuenta === '' && $debe <= 0 && $haber <= 0 && $detalle === '' && $codigoCc === '') {
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
            'cotizacion' => $cotizacion ?? 0.0,
            'detalle' => $detalle,
            'estado' => 'omitido',
            'mensaje' => '',
        ];

        if ($codigoCuenta === '') {
            $base['mensaje'] = 'Sin código de cuenta';

            return $base;
        }

        if ($debe <= 0 && $haber <= 0) {
            $base['mensaje'] = 'Sin importe en Debe ni Haber';

            return $base;
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
                $base['mensaje'] = 'Centro de costo «'.$codigoCc.'» no existe';

                return $base;
            }
            $base['centrocosto_id'] = (int) $cc->id;
            $base['centrocosto_nombre'] = (string) $cc->nombre;
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
        $base['mensaje'] = 'Listo para cargar';

        return $base;
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
     * @return array{cuenta: string, debe: string, haber: string, centrocosto: string, moneda: string, cotizacion: string, detalle: string}
     */
    private function nombresColumnas(
        ?string $colCuenta,
        ?string $colDebe,
        ?string $colHaber,
        ?string $colCentrocosto,
        ?string $colMoneda,
        ?string $colCotizacion,
        ?string $colDetalle
    ): array {
        return [
            'cuenta' => $this->nombreColumna($colCuenta, AsientoImportColumnasSupport::COL_CUENTA_DEFAULT),
            'debe' => $this->nombreColumna($colDebe, AsientoImportColumnasSupport::COL_DEBE_DEFAULT),
            'haber' => $this->nombreColumna($colHaber, AsientoImportColumnasSupport::COL_HABER_DEFAULT),
            'centrocosto' => $this->nombreColumna($colCentrocosto, AsientoImportColumnasSupport::COL_CENTROCOSTO_DEFAULT),
            'moneda' => $this->nombreColumna($colMoneda, AsientoImportColumnasSupport::COL_MONEDA_DEFAULT),
            'cotizacion' => $this->nombreColumna($colCotizacion, AsientoImportColumnasSupport::COL_COTIZACION_DEFAULT),
            'detalle' => $this->nombreColumna($colDetalle, AsientoImportColumnasSupport::COL_DETALLE_DEFAULT),
        ];
    }

    private function nombreColumna(?string $valor, string $default): string
    {
        $valor = trim((string) $valor);

        return $valor !== '' ? $valor : $default;
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
