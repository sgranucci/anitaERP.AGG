<?php

namespace App\Services\Contable;

use App\Models\Configuracion\Moneda;
use App\Models\Contable\Asiento;
use App\Repositories\Contable\Asiento_MovimientoRepositoryInterface;
use App\Repositories\Contable\AsientoRepositoryInterface;
use App\Repositories\Contable\TipoasientoRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Archivo\TextoUtf8Support;
use App\Support\Contable\AsientoImportColumnasSupport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

class AsientoImportService
{
    private const TOLERANCIA_BALANCE = 0.009;

    public function __construct(
        private AsientoImportPreviewService $previewService,
        private AsientoRepositoryInterface $asientoRepository,
        private Asiento_MovimientoRepositoryInterface $asientoMovimientoRepository,
        private TipoasientoRepositoryInterface $tipoasientoRepository,
        private EmpresaRepositoryInterface $empresaRepository,
        private AsientoAprobacionService $asientoAprobacionService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function importar(
        UploadedFile $archivo,
        int $empresaId,
        int $tipoasientoId,
        string $fecha,
        ?string $observacion,
        ?int $monedaDefaultId,
        ?string $colCuenta,
        ?string $colDebe,
        ?string $colHaber,
        ?string $colCentrocosto,
        ?string $colMoneda,
        ?string $colCotizacion,
        ?string $colDetalle,
        ?int $filaEncabezadoManual,
        ?int $hojaIndice1Based,
        bool $confirmarPendienteAprobacion = false,
        ?string $modoColumnas = null,
        ?string $modoImporte = null,
        ?string $colMonto = null,
        ?string $colDh = null,
        ?string $csvSeparador = null
    ): array {
        if ($empresaId <= 0 || ! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            throw new \InvalidArgumentException('Empresa no válida o no asignada al usuario.');
        }
        if ($tipoasientoId <= 0 || ! $this->tipoasientoRepository->findPorId($tipoasientoId)) {
            throw new \InvalidArgumentException('Tipo de asiento no válido.');
        }
        if ($fecha === '') {
            throw new \InvalidArgumentException('Indique la fecha del asiento.');
        }

        $archivo = TextoUtf8Support::asegurarCsvUtf8($archivo);

        $monedaDefault = $monedaDefaultId && $monedaDefaultId > 0
            ? Moneda::query()->find($monedaDefaultId)
            : Moneda::query()->orderBy('id')->first();
        if ($monedaDefault === null) {
            throw new \InvalidArgumentException('No hay moneda por defecto disponible.');
        }

        $modoColumnas = AsientoImportColumnasSupport::normalizarModoColumnas($modoColumnas);
        $modoImporte = AsientoImportColumnasSupport::normalizarModoImporte($modoImporte);
        $separadorCsv = AsientoImportColumnasSupport::resolverSeparadorCsv($archivo, $csvSeparador)['delimiter'];
        $porNumero = $modoColumnas === AsientoImportColumnasSupport::MODO_COLUMNAS_NUMERO;
        $montoDh = $modoImporte === AsientoImportColumnasSupport::MODO_IMPORTE_MONTO_DH;

        $cols = [
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

        $hojas = AsientoImportColumnasSupport::hojasParaSelector($archivo);
        $hojaIndice0 = AsientoImportColumnasSupport::indiceHojaDesdeRequest($hojaIndice1Based, count($hojas));
        $hojaSeleccionada = $hojas[$hojaIndice0] ?? ['indice' => 1, 'nombre' => 'Hoja1'];

        $hoja = AsientoImportColumnasSupport::leerHojas($archivo, $separadorCsv)[$hojaIndice0] ?? [];
        if ($hoja === []) {
            throw new \InvalidArgumentException('La hoja seleccionada no tiene filas legibles.');
        }

        if ($porNumero) {
            $filaInicioDatos = ($filaEncabezadoManual !== null && $filaEncabezadoManual >= 1)
                ? $filaEncabezadoManual
                : 1;
            $indiceInicioDatos = $filaInicioDatos - 1;
            $filaEncabezado = 0;
            $encabezados = [];
        } else {
            $filaEncabezado = AsientoImportColumnasSupport::detectarFilaEncabezado(
                $archivo,
                $filaEncabezadoManual,
                $hojaIndice0,
                $separadorCsv
            );
            $indiceEncabezado = $filaEncabezado - 1;
            $encabezados = $hoja[$indiceEncabezado] ?? [];

            if (! is_array($encabezados) || ! AsientoImportColumnasSupport::pareceFilaEncabezado($encabezados)) {
                throw new \InvalidArgumentException(
                    'No se detectó fila de encabezados en la fila '.$filaEncabezado
                    .'. Indique la fila manualmente, o use «Por número de columna» si el archivo no tiene títulos.'
                );
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

        if ($colCuentaInfo === null) {
            throw new \InvalidArgumentException('Falta la columna de cuenta (obligatoria).');
        }
        if ($montoDh) {
            if ($colMontoInfo === null) {
                throw new \InvalidArgumentException('Falta la columna de monto.');
            }
            if ($colDhInfo === null) {
                throw new \InvalidArgumentException('Falta la columna D/H.');
            }
        } elseif ($colDebeInfo === null && $colHaberInfo === null) {
            throw new \InvalidArgumentException('Falta columna Debe o Haber.');
        }

        $movimientos = [];
        $omitidas = 0;
        $totalDebe = 0.0;
        $totalHaber = 0.0;
        $cacheCuentas = [];
        $cacheCc = [];
        $cacheMonedas = [];
        $erroresFila = [];
        $filasOmitidas = [];

        for ($i = $indiceInicioDatos; $i < count($hoja); $i++) {
            $fila = $hoja[$i] ?? [];
            if (! is_array($fila)) {
                continue;
            }

            $evaluacion = $this->previewService->evaluarFila(
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

            if ($evaluacion['estado'] !== 'ok') {
                $omitidas++;
                $filasOmitidas[] = $evaluacion;
                if (count($erroresFila) < 30) {
                    $erroresFila[] = $this->previewService->lineaErrorFila($evaluacion);
                }

                continue;
            }

            $movimientos[] = $evaluacion;
            $totalDebe += (float) $evaluacion['debe'];
            $totalHaber += (float) $evaluacion['haber'];
        }

        $this->abortarSiFaltaCentrocostoObligatorio($filasOmitidas);
        $this->abortarSiCentrocostoNoHabilitado($filasOmitidas);

        if (count($movimientos) < 2) {
            $lineas = ['Se necesitan al menos dos movimientos válidos para armar el asiento.'];
            foreach ($erroresFila as $linea) {
                $lineas[] = $linea;
            }
            throw new \InvalidArgumentException(implode("\n", $lineas));
        }

        $sinImporte = 0;
        foreach ($movimientos as $mov) {
            if (($mov['motivo'] ?? '') === 'sin_importe') {
                $sinImporte++;
            }
        }

        $diferencia = round($totalDebe - $totalHaber, 4);
        $balanceado = abs($diferencia) <= self::TOLERANCIA_BALANCE;
        $incompleto = ! $balanceado || $sinImporte > 0;

        $cuentacontableIds = array_map(static fn ($m) => (int) $m['cuentacontable_id'], $movimientos);
        $evaluacionCuentas = $this->asientoAprobacionService->evaluarCuentas(
            (int) auth()->id(),
            $cuentacontableIds
        );

        if ($evaluacionCuentas['requiere_aprobacion'] && ! $confirmarPendienteAprobacion) {
            throw new \InvalidArgumentException(
                'Hay cuentas fuera de su lista autorizada. Marque la confirmación para dejar el asiento pendiente de aprobación e intente de nuevo.'
            );
        }

        $payloadCabecera = [
            'empresa_id' => $empresaId,
            'tipoasiento_id' => $tipoasientoId,
            'fecha' => $fecha,
            'observacion' => trim((string) $observacion),
            'numeroasiento' => '',
        ];

        if ($evaluacionCuentas['requiere_aprobacion']) {
            $payloadCabecera['estado_aprobacion'] = Asiento::ESTADO_APROBACION_PENDIENTE;
            $payloadCabecera['cuentas_no_autorizadas'] = json_encode($evaluacionCuentas['cuentas_no_autorizadas']);
        }

        $payloadMovimientos = [
            'cuentacontable_ids' => [],
            'centrocosto_ids' => [],
            'moneda_ids' => [],
            'debes' => [],
            'haberes' => [],
            'cotizaciones' => [],
            'observaciones' => [],
        ];

        foreach ($movimientos as $mov) {
            $detalle = (string) ($mov['detalle'] ?? '');
            if (($mov['motivo'] ?? '') === 'sin_importe') {
                $lado = ($mov['dh'] ?? '') === 'H' ? 'Haber' : (($mov['dh'] ?? '') === 'D' ? 'Debe' : '');
                if ($lado !== '' && ! str_starts_with($detalle, $lado.' · ')) {
                    $detalle = $lado.' · '.$detalle;
                }
            }
            $payloadMovimientos['cuentacontable_ids'][] = (int) $mov['cuentacontable_id'];
            $payloadMovimientos['centrocosto_ids'][] = $mov['centrocosto_id'] ?? null;
            $payloadMovimientos['moneda_ids'][] = (int) $mov['moneda_id'];
            $payloadMovimientos['debes'][] = (float) $mov['debe'] > 0 ? (float) $mov['debe'] : '';
            $payloadMovimientos['haberes'][] = (float) $mov['haber'] > 0 ? (float) $mov['haber'] : '';
            $payloadMovimientos['cotizaciones'][] = (float) ($mov['cotizacion'] ?? 0);
            $payloadMovimientos['observaciones'][] = $detalle;
        }
        if ($sinImporte > 0) {
            $payloadMovimientos['conservar_sin_importe'] = true;
        }

        // Anita espera códigos de moneda en el payload de cabecera al grabar ctamov.
        $payloadAnita = array_merge($payloadCabecera, $payloadMovimientos);
        $payloadAnita['moneda_ids'] = array_map(static function ($monedaId) {
            $moneda = Moneda::query()->find($monedaId);

            return $moneda ? (string) $moneda->codigo : (string) $monedaId;
        }, $payloadMovimientos['moneda_ids']);

        DB::beginTransaction();
        try {
            $payloadAnita['omitir_anita'] = true;
            $asiento = $this->asientoRepository->create($payloadAnita);
            if ($asiento === 'Error' || ! $asiento) {
                throw new \RuntimeException('Error al grabar el asiento.');
            }

            $this->asientoMovimientoRepository->create($payloadMovimientos, $asiento->id);

            if (! $evaluacionCuentas['requiere_aprobacion'] && ! $incompleto) {
                $fresh = $this->asientoRepository->find($asiento->id);
                $payloadSync = $this->asientoRepository->armarPayloadAnitaDesdeModelo($fresh);
                $this->asientoRepository->sincronizarCtamovAnita($payloadSync);
            }

            if ($evaluacionCuentas['requiere_aprobacion']) {
                $this->asientoAprobacionService->enviarMailAprobacion($asiento->fresh());
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $tipo = $this->tipoasientoRepository->find($tipoasientoId);
        $empresa = $this->empresaRepository->findPorId($empresaId);

        return [
            'asiento_id' => (int) $asiento->id,
            'numeroasiento' => (string) $asiento->numeroasiento,
            'fecha' => (string) $asiento->fecha,
            'empresa' => $empresa ? (string) $empresa->nombre : '',
            'tipoasiento' => $tipo ? (string) ($tipo->nombre ?? $tipo->abreviatura) : '',
            'movimientos' => count($movimientos),
            'filas_omitidas' => $omitidas,
            'sin_importe' => $sinImporte,
            'incompleto' => $incompleto,
            'balanceado' => $balanceado,
            'total_debe' => round($totalDebe, 4),
            'total_haber' => round($totalHaber, 4),
            'total_debe_texto' => AsientoImportColumnasSupport::formatearImporte($totalDebe),
            'total_haber_texto' => AsientoImportColumnasSupport::formatearImporte($totalHaber),
            'fila_encabezado' => $filaEncabezado,
            'hoja_indice' => $hojaIndice0 + 1,
            'hoja_nombre' => $hojaSeleccionada['nombre'] ?? null,
            'estado_aprobacion' => (string) ($asiento->estado_aprobacion ?? Asiento::ESTADO_APROBACION_CONFIRMADO),
            'pendiente_aprobacion' => $evaluacionCuentas['requiere_aprobacion'],
            'errores_muestra' => $erroresFila,
        ];
    }

    /**
     * No graba un asiento parcial si alguna línea exige centro de costo y el Excel no lo trae.
     *
     * @param  list<array<string, mixed>>  $filasOmitidas
     */
    private function abortarSiFaltaCentrocostoObligatorio(array $filasOmitidas): void
    {
        $sinCentro = [];
        foreach ($filasOmitidas as $fila) {
            if (($fila['motivo'] ?? '') === 'centrocosto_obligatorio') {
                $sinCentro[] = $fila;
            }
        }

        if ($sinCentro === []) {
            return;
        }

        $lineas = [
            'No se importó el asiento: hay cuentas que manejan centro de costo y el Excel no lo informa.',
        ];
        $muestra = array_slice($sinCentro, 0, 30);
        foreach ($muestra as $fila) {
            $lineas[] = $this->previewService->lineaErrorFila($fila);
        }

        $resto = count($sinCentro) - count($muestra);
        if ($resto > 0) {
            $lineas[] = 'Hay '.$resto.' fila(s) más sin centro de costo.';
        }

        throw new \InvalidArgumentException(implode("\n", $lineas));
    }

    /**
     * @param  list<array<string, mixed>>  $filasOmitidas
     */
    private function abortarSiCentrocostoNoHabilitado(array $filasOmitidas): void
    {
        $invalidos = [];
        foreach ($filasOmitidas as $fila) {
            if (($fila['motivo'] ?? '') === 'centrocosto_no_habilitado') {
                $invalidos[] = $fila;
            }
        }

        if ($invalidos === []) {
            return;
        }

        $lineas = [
            'No se importó el asiento: hay centros de costo que la cuenta contable no tiene habilitados.',
        ];
        $muestra = array_slice($invalidos, 0, 30);
        foreach ($muestra as $fila) {
            $lineas[] = $this->previewService->lineaErrorFila($fila);
        }

        $resto = count($invalidos) - count($muestra);
        if ($resto > 0) {
            $lineas[] = 'Hay '.$resto.' fila(s) más con un centro de costo no habilitado.';
        }

        throw new \InvalidArgumentException(implode("\n", $lineas));
    }

    /**
     * @param  list<array<string, mixed>>  $filasOmitidas
     * @param  list<string>  $erroresFila
     * @return list<string>
     */
    private function lineasDesbalance(
        float $totalDebe,
        float $totalHaber,
        float $diferencia,
        array $filasOmitidas,
        array $erroresFila
    ): array {
        $resumenOmitidas = $this->previewService->resumirFilasOmitidas($filasOmitidas);
        $explicado = $this->previewService->desbalanceExplicadoPorOmitidas($diferencia, $resumenOmitidas);

        if ($explicado) {
            $lineas = [
                'El Excel puede estar balanceado, pero '
                .$resumenOmitidas['cantidad']
                .' fila(s) no entran y el asiento queda desbalanceado en '
                .AsientoImportColumnasSupport::formatearImporte(abs($diferencia))
                .' (Debe importable '
                .AsientoImportColumnasSupport::formatearImporte($totalDebe)
                .' vs Haber '
                .AsientoImportColumnasSupport::formatearImporte($totalHaber)
                .').',
            ];
        } else {
            $lineas = [
                'El asiento no balancea: Debe '
                .AsientoImportColumnasSupport::formatearImporte($totalDebe)
                .' vs Haber '
                .AsientoImportColumnasSupport::formatearImporte($totalHaber)
                .' (diferencia '
                .AsientoImportColumnasSupport::formatearImporte(abs($diferencia))
                .').',
            ];
            if ($resumenOmitidas['resumen'] !== '') {
                $lineas[] = $resumenOmitidas['resumen'];
            }
        }

        foreach ($erroresFila as $linea) {
            $lineas[] = $linea;
        }

        $resto = count($filasOmitidas) - count($erroresFila);
        if ($resto > 0) {
            $lineas[] = 'Hay '.$resto.' fila(s) más con el mismo tipo de error.';
        }

        return $lineas;
    }

    private function nombreColumna(?string $valor, string $default): string
    {
        $valor = trim((string) $valor);

        return $valor !== '' ? $valor : $default;
    }
}
