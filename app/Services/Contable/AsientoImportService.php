<?php

namespace App\Services\Contable;

use App\Imports\Contable\AsientoImportLecturaCruda;
use App\Models\Configuracion\Moneda;
use App\Models\Contable\Asiento;
use App\Repositories\Contable\Asiento_MovimientoRepositoryInterface;
use App\Repositories\Contable\AsientoRepositoryInterface;
use App\Repositories\Contable\TipoasientoRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Contable\AsientoImportColumnasSupport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
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
        bool $confirmarPendienteAprobacion = false
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

        $monedaDefault = $monedaDefaultId && $monedaDefaultId > 0
            ? Moneda::query()->find($monedaDefaultId)
            : Moneda::query()->orderBy('id')->first();
        if ($monedaDefault === null) {
            throw new \InvalidArgumentException('No hay moneda por defecto disponible.');
        }

        $cols = [
            'cuenta' => $this->nombreColumna($colCuenta, AsientoImportColumnasSupport::COL_CUENTA_DEFAULT),
            'debe' => $this->nombreColumna($colDebe, AsientoImportColumnasSupport::COL_DEBE_DEFAULT),
            'haber' => $this->nombreColumna($colHaber, AsientoImportColumnasSupport::COL_HABER_DEFAULT),
            'centrocosto' => $this->nombreColumna($colCentrocosto, AsientoImportColumnasSupport::COL_CENTROCOSTO_DEFAULT),
            'moneda' => $this->nombreColumna($colMoneda, AsientoImportColumnasSupport::COL_MONEDA_DEFAULT),
            'cotizacion' => $this->nombreColumna($colCotizacion, AsientoImportColumnasSupport::COL_COTIZACION_DEFAULT),
            'detalle' => $this->nombreColumna($colDetalle, AsientoImportColumnasSupport::COL_DETALLE_DEFAULT),
        ];

        $hojas = AsientoImportColumnasSupport::hojasParaSelector($archivo);
        $hojaIndice0 = AsientoImportColumnasSupport::indiceHojaDesdeRequest($hojaIndice1Based, count($hojas));
        $hojaSeleccionada = $hojas[$hojaIndice0] ?? ['indice' => 1, 'nombre' => 'Hoja1'];

        $hoja = Excel::toArray(new AsientoImportLecturaCruda(), $archivo)[$hojaIndice0] ?? [];
        if ($hoja === []) {
            throw new \InvalidArgumentException('La hoja seleccionada no tiene filas legibles.');
        }

        $filaEncabezado = AsientoImportColumnasSupport::detectarFilaEncabezado(
            $archivo,
            $filaEncabezadoManual,
            $hojaIndice0
        );
        $indiceEncabezado = $filaEncabezado - 1;
        $encabezados = $hoja[$indiceEncabezado] ?? [];

        if (! is_array($encabezados) || ! AsientoImportColumnasSupport::pareceFilaEncabezado($encabezados)) {
            throw new \InvalidArgumentException(
                'No se detectó fila de encabezados en la fila '.$filaEncabezado.'. Indique la fila manualmente.'
            );
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

        if ($colCuentaInfo === null) {
            throw new \InvalidArgumentException('Falta la columna de cuenta en el Excel (obligatoria).');
        }
        if ($colDebeInfo === null && $colHaberInfo === null) {
            throw new \InvalidArgumentException('Falta columna Debe o Haber en el Excel.');
        }

        $movimientos = [];
        $omitidas = 0;
        $totalDebe = 0.0;
        $totalHaber = 0.0;
        $cacheCuentas = [];
        $cacheCc = [];
        $cacheMonedas = [];
        $erroresFila = [];

        for ($i = $indiceEncabezado + 1; $i < count($hoja); $i++) {
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
                $cacheMonedas
            );

            if ($evaluacion === null) {
                continue;
            }

            if ($evaluacion['estado'] !== 'ok') {
                $omitidas++;
                if (count($erroresFila) < 15) {
                    $erroresFila[] = 'Fila '.$evaluacion['fila_excel'].': '.$evaluacion['mensaje'];
                }

                continue;
            }

            $movimientos[] = $evaluacion;
            $totalDebe += (float) $evaluacion['debe'];
            $totalHaber += (float) $evaluacion['haber'];
        }

        if (count($movimientos) < 2) {
            $detalle = $erroresFila !== [] ? ' '.implode(' | ', $erroresFila) : '';
            throw new \InvalidArgumentException(
                'Se necesitan al menos dos movimientos válidos para armar el asiento.'.$detalle
            );
        }

        $diferencia = round($totalDebe - $totalHaber, 4);
        if (abs($diferencia) > self::TOLERANCIA_BALANCE) {
            throw new \InvalidArgumentException(
                'El asiento no balancea: Debe '
                .AsientoImportColumnasSupport::formatearImporte($totalDebe)
                .' vs Haber '
                .AsientoImportColumnasSupport::formatearImporte($totalHaber)
                .' (diferencia '
                .AsientoImportColumnasSupport::formatearImporte(abs($diferencia))
                .').'
            );
        }

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
            $payloadMovimientos['cuentacontable_ids'][] = (int) $mov['cuentacontable_id'];
            $payloadMovimientos['centrocosto_ids'][] = $mov['centrocosto_id'] ?? null;
            $payloadMovimientos['moneda_ids'][] = (int) $mov['moneda_id'];
            $payloadMovimientos['debes'][] = (float) $mov['debe'] > 0 ? (float) $mov['debe'] : '';
            $payloadMovimientos['haberes'][] = (float) $mov['haber'] > 0 ? (float) $mov['haber'] : '';
            $payloadMovimientos['cotizaciones'][] = (float) ($mov['cotizacion'] ?? 0);
            $payloadMovimientos['observaciones'][] = (string) ($mov['detalle'] ?? '');
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

            if (! $evaluacionCuentas['requiere_aprobacion']) {
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

    private function nombreColumna(?string $valor, string $default): string
    {
        $valor = trim((string) $valor);

        return $valor !== '' ? $valor : $default;
    }
}
