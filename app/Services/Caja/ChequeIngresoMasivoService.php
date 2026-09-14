<?php

namespace App\Services\Caja;

use App\Imports\Stock\PrecioImportLecturaCruda;
use App\Models\Caja\Cheque;
use App\Models\Configuracion\Moneda;
use App\Repositories\Caja\BancoRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Repositories\Ventas\ClienteRepositoryInterface;
use App\Support\Caja\ChequeImportColumnasSupport;
use App\Support\Caja\ChequeTerceroCtermaeInsertAnitaSupport;
use App\Support\Stock\PrecioImportColumnasSupport;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class ChequeIngresoMasivoService
{
    private const MAX_PREVIEW = 20;

    private const MAX_ERRORES_SAMPLE = 15;

    public function __construct(
        private BancoRepositoryInterface $bancoRepository,
        private ClienteRepositoryInterface $clienteRepository,
        private MonedaRepositoryInterface $monedaRepository,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    /**
     * @param  array<string, mixed>  $mappingOverride
     * @return array<string, mixed>
     */
    public function preview(
        UploadedFile $archivo,
        array $mappingOverride,
        ?int $empresaId,
        ?int $hoja1Based = null,
        ?int $filaEncabezadoManual = null
    ): array {
        $hojas = PrecioImportColumnasSupport::hojasParaSelector($archivo);
        $hojaIndice0 = PrecioImportColumnasSupport::indiceHojaDesdeRequest($hoja1Based, count($hojas));
        $hojaSeleccionada = $hojas[$hojaIndice0] ?? $hojas[0] ?? ['indice' => 1, 'nombre' => 'Hoja1'];

        $hoja = Excel::toArray(new PrecioImportLecturaCruda(), $archivo)[$hojaIndice0] ?? [];
        if ($hoja === []) {
            return [
                'ok' => false,
                'mensaje' => 'La hoja seleccionada no tiene filas legibles.',
                'hojas' => $hojas,
                'hoja_seleccionada' => (int) ($hojaSeleccionada['indice'] ?? 1),
                'hoja_nombre' => (string) ($hojaSeleccionada['nombre'] ?? ''),
            ];
        }

        $filaEncabezado = ChequeImportColumnasSupport::detectarFilaEncabezado(
            $archivo,
            $filaEncabezadoManual,
            $hojaIndice0
        );
        $indiceEncabezado = $filaEncabezado - 1;
        $headersRaw = $hoja[$indiceEncabezado] ?? [];
        if (! is_array($headersRaw)) {
            $headersRaw = [];
        }

        $headers = [];
        foreach ($headersRaw as $i => $celda) {
            $titulo = trim((string) $celda);
            $headers[] = [
                'indice' => (int) $i,
                'titulo' => $titulo !== '' ? $titulo : ('Columna '.((int) $i + 1)),
            ];
        }

        $mapaAuto = ChequeImportColumnasSupport::resolverMapaDesdeEncabezados($headersRaw);
        $mapa = ChequeImportColumnasSupport::fusionarMapa($mapaAuto, $mappingOverride);

        $ok = 0;
        $error = 0;
        $omitidas = 0;
        $filasPreview = [];
        $erroresSample = [];
        $totalDatos = 0;

        for ($i = $indiceEncabezado + 1; $i < count($hoja); $i++) {
            $fila = $hoja[$i] ?? null;
            if (! is_array($fila) || $this->filaVacia($fila)) {
                continue;
            }
            $totalDatos++;
            $filaExcel = $i + 1;
            $resultado = $this->validarFila($fila, $mapa, $empresaId, false);

            if ($resultado['estado'] === 'ok') {
                $ok++;
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
                $filasPreview[] = array_merge(
                    ['fila_excel' => $filaExcel],
                    $resultado
                );
            }
        }

        $requeridosOk = true;
        foreach (ChequeImportColumnasSupport::metaCampos() as $campo => $meta) {
            if ($meta['requerido'] && ($mapa[$campo] ?? null) === null) {
                $requeridosOk = false;
                break;
            }
        }

        return [
            'ok' => $requeridosOk && $error === 0 && $ok > 0,
            'headers' => $headers,
            'mapping' => $mapa,
            'campos' => ChequeImportColumnasSupport::metaCampos(),
            'filas_preview' => $filasPreview,
            'totales' => [
                'ok' => $ok,
                'error' => $error,
                'omitidas' => $omitidas,
                'total' => $totalDatos,
            ],
            'errores' => $erroresSample,
            'fila_encabezado' => $filaEncabezado,
            'fila_encabezado_automatica' => $filaEncabezadoManual === null,
            'hojas' => $hojas,
            'hoja_seleccionada' => (int) ($hojaSeleccionada['indice'] ?? 1),
            'hoja_nombre' => (string) ($hojaSeleccionada['nombre'] ?? ''),
            'hay_mas_filas' => $totalDatos > self::MAX_PREVIEW,
        ];
    }

    /**
     * @param  array<string, mixed>  $mappingOverride
     * @return array{creados:int, omitidos:int, errores:int, detalle_errores:list<array{fila:int, mensaje:string}>}
     */
    public function import(
        UploadedFile $archivo,
        array $mappingOverride,
        ?int $empresaId,
        ?int $hoja1Based = null,
        ?int $filaEncabezadoManual = null
    ): array {
        $hojas = PrecioImportColumnasSupport::listarNombresHojas($archivo);
        $hojaIndice0 = PrecioImportColumnasSupport::indiceHojaDesdeRequest($hoja1Based, count($hojas));
        $hoja = Excel::toArray(new PrecioImportLecturaCruda(), $archivo)[$hojaIndice0] ?? [];

        $filaEncabezado = ChequeImportColumnasSupport::detectarFilaEncabezado(
            $archivo,
            $filaEncabezadoManual,
            $hojaIndice0
        );
        $indiceEncabezado = $filaEncabezado - 1;
        $headersRaw = $hoja[$indiceEncabezado] ?? [];
        if (! is_array($headersRaw)) {
            $headersRaw = [];
        }

        $mapa = ChequeImportColumnasSupport::fusionarMapa(
            ChequeImportColumnasSupport::resolverMapaDesdeEncabezados($headersRaw),
            $mappingOverride
        );

        $creados = 0;
        $omitidos = 0;
        $errores = 0;
        $anitaOk = 0;
        $anitaFail = 0;
        $detalleErrores = [];

        for ($i = $indiceEncabezado + 1; $i < count($hoja); $i++) {
            $fila = $hoja[$i] ?? null;
            if (! is_array($fila) || $this->filaVacia($fila)) {
                continue;
            }
            $filaExcel = $i + 1;
            $resultado = $this->validarFila($fila, $mapa, $empresaId, true);

            if ($resultado['estado'] === 'omitida') {
                $omitidos++;

                continue;
            }
            if ($resultado['estado'] !== 'ok' || empty($resultado['payload'])) {
                $errores++;
                if (count($detalleErrores) < self::MAX_ERRORES_SAMPLE) {
                    $detalleErrores[] = [
                        'fila' => $filaExcel,
                        'mensaje' => $resultado['mensaje'],
                    ];
                }

                continue;
            }

            try {
                $cheque = Cheque::query()->create($resultado['payload']);
                $creados++;
                if ((bool) config('cheque.import_push_anita', true)) {
                    $nroInterno = ChequeTerceroCtermaeInsertAnitaSupport::insertar($cheque);
                    if ($nroInterno !== null && $nroInterno > 0) {
                        $cheque->nro_interno_anita = $nroInterno;
                        $cheque->save();
                        $anitaOk++;
                    } else {
                        $anitaFail++;
                    }
                }
            } catch (\Throwable $e) {
                $errores++;
                if (count($detalleErrores) < self::MAX_ERRORES_SAMPLE) {
                    $detalleErrores[] = [
                        'fila' => $filaExcel,
                        'mensaje' => $e->getMessage(),
                    ];
                }
            }
        }

        return [
            'creados' => $creados,
            'omitidos' => $omitidos,
            'errores' => $errores,
            'anita_ok' => $anitaOk,
            'anita_fail' => $anitaFail,
            'detalle_errores' => $detalleErrores,
        ];
    }

    /**
     * @param  array<int, mixed>  $fila
     * @param  array<string, int|null>  $mapa
     * @return array<string, mixed>
     */
    private function validarFila(array $fila, array $mapa, ?int $empresaIdForm, bool $paraPersistir): array
    {
        $numero = trim((string) ChequeImportColumnasSupport::valorCampo($fila, $mapa, 'numerocheque'));
        $fechaPagoRaw = ChequeImportColumnasSupport::valorCampo($fila, $mapa, 'fechapago');
        $montoRaw = ChequeImportColumnasSupport::valorCampo($fila, $mapa, 'monto');
        $bancoCodigo = trim((string) ChequeImportColumnasSupport::valorCampo($fila, $mapa, 'banco_codigo'));

        if ($numero === '' && $bancoCodigo === '' && $this->celdaVacia($montoRaw) && $this->celdaVacia($fechaPagoRaw)) {
            return [
                'estado' => 'omitida',
                'mensaje' => 'Fila vacía',
                'numerocheque' => '',
                'monto' => null,
                'fechapago' => '',
                'banco' => '',
                'cliente' => '',
            ];
        }

        $errores = [];
        if ($numero === '') {
            $errores[] = 'Falta número de cheque';
        }

        $fechaPago = $this->parseFecha($fechaPagoRaw);
        if ($fechaPago === null) {
            $errores[] = 'Fecha de pago inválida';
        }

        $monto = PrecioImportColumnasSupport::normalizarValorPrecio($montoRaw);
        if ($monto === null || $monto <= 0) {
            $errores[] = 'Monto inválido';
        }

        $banco = $bancoCodigo !== '' ? $this->bancoRepository->findPorCodigo($bancoCodigo) : null;
        if ($banco === null) {
            $errores[] = $bancoCodigo !== ''
                ? 'Banco no encontrado ('.$bancoCodigo.')'
                : 'Falta código de banco';
        }

        $clienteCodigo = trim((string) ChequeImportColumnasSupport::valorCampo($fila, $mapa, 'cliente_codigo'));
        $cliente = null;
        if ($clienteCodigo !== '') {
            $cliente = $this->clienteRepository->findPorCodigo($clienteCodigo);
            if ($cliente === null) {
                $errores[] = 'Cliente no encontrado ('.$clienteCodigo.')';
            }
        }

        $monedaCodigo = trim((string) ChequeImportColumnasSupport::valorCampo($fila, $mapa, 'moneda_codigo'));
        $moneda = $this->resolverMoneda($monedaCodigo);
        if ($monedaCodigo !== '' && $moneda === null) {
            $errores[] = 'Moneda no encontrada ('.$monedaCodigo.')';
        }
        if ($moneda === null) {
            $moneda = Moneda::query()->find(1);
        }

        $empresaCodigo = trim((string) ChequeImportColumnasSupport::valorCampo($fila, $mapa, 'empresa_codigo'));
        $empresaId = $empresaIdForm && $empresaIdForm > 0 ? $empresaIdForm : null;
        if ($empresaCodigo !== '') {
            $empresa = $this->empresaRepository->findPorCodigo($empresaCodigo);
            if ($empresa === null) {
                $errores[] = 'Empresa no encontrada ('.$empresaCodigo.')';
            } else {
                $empresaId = (int) $empresa->id;
            }
        }
        if ($empresaId === null || $empresaId <= 0) {
            $errores[] = 'Falta empresa (formulario o columna)';
        }

        $fechaEmision = $this->parseFecha(
            ChequeImportColumnasSupport::valorCampo($fila, $mapa, 'fechaemision')
        );
        if ($fechaEmision === null) {
            $fechaEmision = $fechaPago;
        }

        $entregado = trim((string) ChequeImportColumnasSupport::valorCampo($fila, $mapa, 'entregado'));
        $anombrede = trim((string) ChequeImportColumnasSupport::valorCampo($fila, $mapa, 'anombrede'));
        $sucursalpago = trim((string) ChequeImportColumnasSupport::valorCampo($fila, $mapa, 'sucursalpago'));
        $cuentalibradora = trim((string) ChequeImportColumnasSupport::valorCampo($fila, $mapa, 'cuentalibradora'));

        $base = [
            'numerocheque' => $numero,
            'monto' => $monto,
            'fechapago' => $fechaPago ?? '',
            'banco' => $banco ? (string) ($banco->nombre ?? $bancoCodigo) : $bancoCodigo,
            'cliente' => $cliente ? (string) ($cliente->nombre ?? $clienteCodigo) : $clienteCodigo,
            'moneda' => $moneda ? (string) ($moneda->abreviatura ?? '') : '',
        ];

        if ($errores !== []) {
            return array_merge($base, [
                'estado' => 'error',
                'mensaje' => implode('; ', $errores),
            ]);
        }

        if ($this->existeDuplicado($numero, (int) $banco->id, (float) $monto, (string) $fechaPago, (int) $empresaId)) {
            return array_merge($base, [
                'estado' => 'omitida',
                'mensaje' => 'Duplicado (mismo nro + banco + monto + fecha pago en la empresa)',
            ]);
        }

        $payload = [
            'origen' => 'R',
            'estado' => ' ',
            'numerocheque' => $numero,
            'fechapago' => $fechaPago,
            'fechaemision' => $fechaEmision,
            'monto' => round((float) $monto, 2),
            'banco_id' => (int) $banco->id,
            'cliente_id' => $cliente ? (int) $cliente->id : null,
            'moneda_id' => $moneda ? (int) $moneda->id : 1,
            'empresa_id' => (int) $empresaId,
            'entregado' => $entregado !== '' ? $entregado : null,
            'anombrede' => $anombrede !== '' ? $anombrede : null,
            'sucursalpago' => $sucursalpago !== '' ? $sucursalpago : null,
            'cuentalibradora' => $cuentalibradora !== '' ? $cuentalibradora : null,
            'cotizacion' => 1,
        ];

        return array_merge($base, [
            'estado' => 'ok',
            'mensaje' => $paraPersistir ? 'Importable' : 'OK',
            'payload' => $payload,
        ]);
    }

    private function existeDuplicado(
        string $numerocheque,
        int $bancoId,
        float $monto,
        string $fechapago,
        int $empresaId
    ): bool {
        return Cheque::query()
            ->where('empresa_id', $empresaId)
            ->where('numerocheque', $numerocheque)
            ->where('banco_id', $bancoId)
            ->where('monto', round($monto, 2))
            ->whereDate('fechapago', $fechapago)
            ->exists();
    }

    private function resolverMoneda(string $codigo): ?Moneda
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return null;
        }

        $porCodigo = $this->monedaRepository->findPorCodigo($codigo);
        if ($porCodigo) {
            return $porCodigo;
        }

        $norm = strtoupper($codigo);

        return Moneda::query()
            ->where(function ($q) use ($norm) {
                $q->whereRaw('UPPER(abreviatura) = ?', [$norm])
                    ->orWhereRaw('UPPER(codigo) = ?', [$norm]);
            })
            ->first();
    }

    private function parseFecha(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (is_numeric($valor)) {
            try {
                $dt = ExcelDate::excelToDateTimeObject((float) $valor);

                return $dt->format('Y-m-d');
            } catch (\Throwable $e) {
                // seguir con string
            }
        }

        $texto = trim((string) $valor);
        if ($texto === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd/m/y', 'Y/m/d'] as $fmt) {
            try {
                $c = Carbon::createFromFormat($fmt, $texto);
                if ($c !== false) {
                    return $c->format('Y-m-d');
                }
            } catch (\Throwable $e) {
            }
        }

        try {
            return Carbon::parse($texto)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @param  array<int, mixed>  $fila
     */
    private function filaVacia(array $fila): bool
    {
        foreach ($fila as $celda) {
            if (! $this->celdaVacia($celda)) {
                return false;
            }
        }

        return true;
    }

    private function celdaVacia(mixed $valor): bool
    {
        return $valor === null || trim((string) $valor) === '';
    }
}
