<?php

namespace App\Services\Sueldos;

use App\Imports\Sueldos\NovedadSueldosImportLecturaCruda;
use App\Models\Configuracion\Empresa;
use App\Models\Sueldos\Concepto_Sueldos;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Liquidacion_Sueldos;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Sueldos\Novedad_SueldosRepositoryInterface;
use App\Support\Archivo\TextoUtf8Support;
use App\Support\Stock\PrecioImportColumnasSupport;
use App\Support\Sueldos\NovedadSueldosCatalogo;
use App\Support\Sueldos\NovedadSueldosImportColumnasSupport;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class NovedadSueldosImportService
{
    private const MAX_PREVIEW = 25;

    private const MAX_ERRORES_SAMPLE = 15;

    public function __construct(
        private Novedad_SueldosRepositoryInterface $novedadRepository,
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
        ?int $empresaIdForm,
        ?int $hoja1Based = null,
        ?int $filaEncabezadoManual = null
    ): array {
        $archivo = TextoUtf8Support::asegurarCsvUtf8($archivo);
        $leido = $this->leerHoja($archivo, $hoja1Based, $filaEncabezadoManual, $mappingOverride);
        if (isset($leido['error'])) {
            return $leido['error'];
        }

        $ok = 0;
        $error = 0;
        $omitidas = 0;
        $sumaValor1 = 0.0;
        $filasPreview = [];
        $erroresSample = [];
        $totalDatos = 0;
        $cache = $this->cacheVacio();
        $firmas = [];

        foreach ($leido['filas'] as $filaExcel => $fila) {
            $totalDatos++;
            $resultado = $this->validarFila($fila, $leido['mapa'], $empresaIdForm, $cache, $firmas, $filaExcel);

            if ($resultado['estado'] === 'ok') {
                $ok++;
                $sumaValor1 += (float) ($resultado['valor1_num'] ?? 0);
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

        $requeridosOk = ($leido['mapa']['legajo'] ?? null) !== null
            && ($leido['mapa']['concepto_codigo'] ?? null) !== null;

        return [
            'ok' => $requeridosOk && $ok > 0,
            'headers' => $leido['headers'],
            'mapping' => $leido['mapa'],
            'campos' => NovedadSueldosImportColumnasSupport::metaCampos(),
            'filas_preview' => $filasPreview,
            'totales' => [
                'ok' => $ok,
                'error' => $error,
                'omitidas' => $omitidas,
                'total' => $totalDatos,
                'suma_valor1' => round($sumaValor1, 4),
            ],
            'errores' => $erroresSample,
            'fila_encabezado' => $leido['fila_encabezado'],
            'fila_encabezado_automatica' => $filaEncabezadoManual === null,
            'hojas' => $leido['hojas'],
            'hoja_seleccionada' => $leido['hoja_seleccionada'],
            'hoja_nombre' => $leido['hoja_nombre'],
            'hay_mas_filas' => $totalDatos > self::MAX_PREVIEW,
            'advertencias' => $requeridosOk ? [] : ['Faltan columnas obligatorias: legajo y código de concepto.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $mappingOverride
     * @return array{importados: int, omitidos: int, errores: int, detalle_errores: list<array{fila: int, mensaje: string}>}
     */
    public function import(
        UploadedFile $archivo,
        array $mappingOverride,
        ?int $empresaIdForm,
        ?int $hoja1Based = null,
        ?int $filaEncabezadoManual = null
    ): array {
        $archivo = TextoUtf8Support::asegurarCsvUtf8($archivo);
        $leido = $this->leerHoja($archivo, $hoja1Based, $filaEncabezadoManual, $mappingOverride);
        if (isset($leido['error'])) {
            return [
                'importados' => 0,
                'omitidos' => 0,
                'errores' => 1,
                'detalle_errores' => [[
                    'fila' => 0,
                    'mensaje' => (string) ($leido['error']['mensaje'] ?? 'No se pudo leer el archivo.'),
                ]],
            ];
        }

        $importados = 0;
        $omitidos = 0;
        $errores = 0;
        $detalleErrores = [];
        $cache = $this->cacheVacio();
        $firmas = [];
        $usuarioId = optional(auth()->user())->id;

        foreach ($leido['filas'] as $filaExcel => $fila) {
            $resultado = $this->validarFila($fila, $leido['mapa'], $empresaIdForm, $cache, $firmas, $filaExcel);
            if ($resultado['estado'] === 'omitida') {
                $omitidos++;

                continue;
            }
            if ($resultado['estado'] !== 'ok' || empty($resultado['payload'])) {
                $errores++;
                if (count($detalleErrores) < self::MAX_ERRORES_SAMPLE) {
                    $detalleErrores[] = [
                        'fila' => $filaExcel,
                        'mensaje' => (string) ($resultado['mensaje'] ?? 'Fila inválida'),
                    ];
                }

                continue;
            }

            $payload = $resultado['payload'];
            $payload['origen'] = NovedadSueldosCatalogo::ORIGEN_IMPORT;
            $payload['usuario_id'] = $usuarioId;
            try {
                $this->novedadRepository->create($payload);
                $importados++;
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
            'importados' => $importados,
            'omitidos' => $omitidos,
            'errores' => $errores,
            'detalle_errores' => $detalleErrores,
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
        array $mappingOverride
    ): array {
        $hojas = PrecioImportColumnasSupport::hojasParaSelector($archivo);
        $hojaIndice0 = PrecioImportColumnasSupport::indiceHojaDesdeRequest($hoja1Based, count($hojas));
        $hojaSeleccionada = $hojas[$hojaIndice0] ?? $hojas[0] ?? ['indice' => 1, 'nombre' => 'Hoja1'];

        $hoja = Excel::toArray(new NovedadSueldosImportLecturaCruda(), $archivo)[$hojaIndice0] ?? [];
        if ($hoja === []) {
            return [
                'error' => [
                    'ok' => false,
                    'mensaje' => 'La hoja seleccionada no tiene filas legibles.',
                    'hojas' => $hojas,
                    'hoja_seleccionada' => (int) ($hojaSeleccionada['indice'] ?? 1),
                    'hoja_nombre' => (string) ($hojaSeleccionada['nombre'] ?? ''),
                ],
            ];
        }

        $filaEncabezado = NovedadSueldosImportColumnasSupport::detectarFilaEncabezado(
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

        $mapa = NovedadSueldosImportColumnasSupport::fusionarMapa(
            NovedadSueldosImportColumnasSupport::resolverMapaDesdeEncabezados($headersRaw),
            $mappingOverride
        );

        $filas = [];
        for ($i = $indiceEncabezado + 1; $i < count($hoja); $i++) {
            $fila = $hoja[$i] ?? null;
            if (! is_array($fila) || $this->filaVacia($fila)) {
                continue;
            }
            $filas[$i + 1] = $fila;
        }

        return [
            'hojas' => $hojas,
            'hoja_seleccionada' => (int) ($hojaSeleccionada['indice'] ?? 1),
            'hoja_nombre' => (string) ($hojaSeleccionada['nombre'] ?? ''),
            'fila_encabezado' => $filaEncabezado,
            'headers' => $headers,
            'mapa' => $mapa,
            'filas' => $filas,
        ];
    }

    /**
     * @return array{empleado: array<string, Empleado_Sueldos|null>, concepto: array<int, Concepto_Sueldos|null>, liquidacion: array<string, Liquidacion_Sueldos|null|false>, empresa: array<string, Empresa|null>}
     */
    private function cacheVacio(): array
    {
        return [
            'empleado' => [],
            'concepto' => [],
            'liquidacion' => [],
            'empresa' => [],
        ];
    }

    /**
     * @param  array<int, mixed>  $fila
     * @param  array<string, int|null>  $mapa
     * @param  array{empleado: array<string, Empleado_Sueldos|null>, concepto: array<int, Concepto_Sueldos|null>, liquidacion: array<string, Liquidacion_Sueldos|null|false>, empresa: array<string, Empresa|null>}  $cache
     * @param  array<string, int>  $firmas
     * @return array<string, mixed>
     */
    private function validarFila(array $fila, array $mapa, ?int $empresaIdForm, array &$cache, array &$firmas, int $numeroFila): array
    {
        $legajoRaw = NovedadSueldosImportColumnasSupport::valorCampo($fila, $mapa, 'legajo');
        $conceptoRaw = NovedadSueldosImportColumnasSupport::valorCampo($fila, $mapa, 'concepto_codigo');
        $valor1Raw = NovedadSueldosImportColumnasSupport::valorCampo($fila, $mapa, 'valor1');
        $valor2Raw = NovedadSueldosImportColumnasSupport::valorCampo($fila, $mapa, 'valor2');

        $legajoTxt = trim((string) $legajoRaw);
        $conceptoTxt = trim((string) $conceptoRaw);

        if ($legajoTxt === '' && $conceptoTxt === '' && $this->celdaVacia($valor1Raw) && $this->celdaVacia($valor2Raw)) {
            return $this->filaResultado('omitida', 'Fila sin legajo ni concepto', '', '', '', '', '', '', null, null);
        }

        $errores = [];
        if (($mapa['legajo'] ?? null) === null) {
            $errores[] = 'Falta mapear la columna legajo';
        }
        if (($mapa['concepto_codigo'] ?? null) === null) {
            $errores[] = 'Falta mapear la columna de concepto';
        }

        $empresa = $this->resolverEmpresa($fila, $mapa, $empresaIdForm, $cache, $errores);
        $empresaId = $empresa ? (int) $empresa->id : 0;

        $legajo = $this->enteroCelda($legajoRaw);
        if ($legajo === null || $legajo <= 0) {
            $errores[] = $legajoTxt === '' ? 'Falta legajo' : 'Legajo inválido';
        }

        $conceptoCodigo = $this->enteroCelda($conceptoRaw);
        if ($conceptoCodigo === null || $conceptoCodigo <= 0) {
            $errores[] = $conceptoTxt === '' ? 'Falta código de concepto' : 'Código de concepto inválido';
        }

        $empleado = null;
        if ($empresaId > 0 && $legajo !== null && $legajo > 0) {
            $empleado = $this->empleadoCache($cache, $empresaId, $legajo);
            if ($empleado === null) {
                $errores[] = 'Legajo '.$legajo.' no encontrado en la empresa';
            }
        }

        $concepto = null;
        if ($conceptoCodigo !== null && $conceptoCodigo > 0) {
            $concepto = $this->conceptoCache($cache, $conceptoCodigo);
            if ($concepto === null) {
                $errores[] = 'Concepto '.$conceptoCodigo.' no encontrado';
            }
        }

        $liqNumero = $this->enteroOpcional(
            NovedadSueldosImportColumnasSupport::valorCampo($fila, $mapa, 'liquidacion_numero'),
            'N° de liquidación',
            $errores
        );
        $liquidacion = null;
        if ($liqNumero !== null && $liqNumero > 0 && $empresaId > 0) {
            $liquidacion = $this->liquidacionCache($cache, $empresaId, $liqNumero);
            if ($liquidacion === false) {
                $errores[] = 'Hay más de una liquidación N° '.$liqNumero.' en la empresa';
                $liquidacion = null;
            } elseif ($liquidacion === null) {
                $errores[] = 'Liquidación N° '.$liqNumero.' no encontrada';
            }
        }

        $valor1 = $this->importeOpcional($valor1Raw, 'Valor 1', $errores);
        $valor2 = $this->importeOpcional($valor2Raw, 'Valor 2', $errores);

        $estadoRaw = NovedadSueldosImportColumnasSupport::valorCampo($fila, $mapa, 'estado');
        $estado = NovedadSueldosCatalogo::interpretarEstadoImport($estadoRaw);
        if ($estado === null) {
            $errores[] = 'Estado no reconocido ('.trim((string) $estadoRaw).')';
            $estado = '';
        }

        $fechaVto = $this->fechaOpcional(
            NovedadSueldosImportColumnasSupport::valorCampo($fila, $mapa, 'fecha_vto'),
            'Fecha vto.',
            $errores
        );
        $fechaDesde = $this->fechaOpcional(
            NovedadSueldosImportColumnasSupport::valorCampo($fila, $mapa, 'fecha_desde'),
            'Fecha desde',
            $errores
        );
        $fechaHasta = $this->fechaOpcional(
            NovedadSueldosImportColumnasSupport::valorCampo($fila, $mapa, 'fecha_hasta'),
            'Fecha hasta',
            $errores
        );
        if ($fechaDesde !== null && $fechaHasta !== null && $fechaHasta < $fechaDesde) {
            $errores[] = 'Fecha hasta es anterior a fecha desde';
        }

        $nroInterno = $this->enteroOpcional(
            NovedadSueldosImportColumnasSupport::valorCampo($fila, $mapa, 'nro_interno'),
            'Nro. interno',
            $errores
        ) ?? 0;

        $periodoCelda = NovedadSueldosImportColumnasSupport::valorCampo($fila, $mapa, 'periodo');
        $periodo = $this->periodoOpcional($periodoCelda, $errores);
        if ($liquidacion !== null && $liquidacion->periodo) {
            $periodo = (int) $liquidacion->periodo;
        }

        $observacion = trim((string) NovedadSueldosImportColumnasSupport::valorCampo($fila, $mapa, 'observacion'));

        $base = $this->filaResultado(
            'error',
            '',
            $legajoTxt,
            $empleado ? (string) $empleado->nombre : '',
            $concepto ? ((int) $concepto->codigo.' — '.$concepto->descripcion) : $conceptoTxt,
            $liquidacion ? ('N° '.$liquidacion->numero) : ($liqNumero ? ('N° '.$liqNumero) : ''),
            $this->textoVigencia($fechaDesde, $fechaHasta, $fechaVto),
            $estado !== '' ? NovedadSueldosCatalogo::etiquetaEstado($estado) : trim((string) $estadoRaw),
            $valor1,
            $valor2
        );

        if ($errores !== []) {
            $base['mensaje'] = implode('; ', $errores);

            return $base;
        }

        $firma = implode('|', [
            $empresaId,
            (int) $empleado->id,
            (int) $concepto->id,
            $liquidacion ? (int) $liquidacion->id : 0,
            $nroInterno,
            $periodo ?? 0,
            $fechaDesde ?? '',
            $fechaHasta ?? '',
            $fechaVto ?? '',
            number_format((float) $valor1, 4, '.', ''),
            number_format((float) $valor2, 4, '.', ''),
        ]);
        if (isset($firmas[$firma])) {
            $base['estado'] = 'error';
            $base['mensaje'] = 'Repetida en el archivo (igual a la fila '.$firmas[$firma].')';

            return $base;
        }
        $firmas[$firma] = $numeroFila;

        $base['estado'] = 'ok';
        $base['mensaje'] = 'OK';
        $base['payload'] = [
            'empresa_id' => $empresaId,
            'liquidacion_id' => $liquidacion ? (int) $liquidacion->id : null,
            'empleado_id' => (int) $empleado->id,
            'concepto_id' => (int) $concepto->id,
            'valor1' => (float) $valor1,
            'valor2' => (float) $valor2,
            'estado' => $estado,
            'fecha_vto' => $fechaVto,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'nro_interno' => $nroInterno,
            'periodo' => $periodo,
            'observacion' => $observacion !== '' ? $observacion : null,
        ];

        return $base;
    }

    /**
     * @param  array<int, mixed>  $fila
     * @param  array<string, int|null>  $mapa
     * @param  array{empresa: array<string, Empresa|null>}  $cache
     * @param  list<string>  $errores
     */
    private function resolverEmpresa(array $fila, array $mapa, ?int $empresaIdForm, array &$cache, array &$errores): ?Empresa
    {
        $raw = NovedadSueldosImportColumnasSupport::valorCampo($fila, $mapa, 'empresa');
        $texto = trim((string) $raw);
        $empresa = null;

        if ($texto !== '') {
            $clave = strtolower($texto);
            if (! array_key_exists($clave, $cache['empresa'])) {
                $cache['empresa'][$clave] = $this->buscarEmpresa($texto);
            }
            $empresa = $cache['empresa'][$clave];
            if ($empresa === null) {
                $errores[] = 'Empresa no encontrada ('.$texto.')';

                return null;
            }
        } elseif ($empresaIdForm !== null && $empresaIdForm > 0) {
            $clave = 'id:'.$empresaIdForm;
            if (! array_key_exists($clave, $cache['empresa'])) {
                $cache['empresa'][$clave] = Empresa::query()->find($empresaIdForm);
            }
            $empresa = $cache['empresa'][$clave];
            if ($empresa === null) {
                $errores[] = 'La empresa del formulario no existe';

                return null;
            }
        } else {
            $errores[] = 'Falta empresa (formulario o columna)';

            return null;
        }

        if (! $this->empresaRepository->empresaIdPermitida((int) $empresa->id)) {
            $errores[] = 'Empresa no autorizada para el usuario';

            return null;
        }

        return $empresa;
    }

    private function buscarEmpresa(string $texto): ?Empresa
    {
        if (ctype_digit($texto)) {
            $porId = Empresa::query()->find((int) $texto);
            if ($porId !== null) {
                return $porId;
            }
        }

        $porCodigo = $this->empresaRepository->findPorCodigo($texto);

        return $porCodigo instanceof Empresa ? $porCodigo : null;
    }

    /**
     * @param  array{empleado: array<string, Empleado_Sueldos|null>}  $cache
     */
    private function empleadoCache(array &$cache, int $empresaId, int $legajo): ?Empleado_Sueldos
    {
        $clave = $empresaId.'|'.$legajo;
        if (! array_key_exists($clave, $cache['empleado'])) {
            $cache['empleado'][$clave] = Empleado_Sueldos::query()
                ->where('empresa_id', $empresaId)
                ->where('legajo', $legajo)
                ->first();
        }

        return $cache['empleado'][$clave];
    }

    /**
     * @param  array{concepto: array<int, Concepto_Sueldos|null>}  $cache
     */
    private function conceptoCache(array &$cache, int $codigo): ?Concepto_Sueldos
    {
        if (! array_key_exists($codigo, $cache['concepto'])) {
            $cache['concepto'][$codigo] = Concepto_Sueldos::query()->where('codigo', $codigo)->first();
        }

        return $cache['concepto'][$codigo];
    }

    /**
     * false = más de una liquidación con ese número.
     *
     * @param  array{liquidacion: array<string, Liquidacion_Sueldos|null|false>}  $cache
     */
    private function liquidacionCache(array &$cache, int $empresaId, int $numero): Liquidacion_Sueldos|false|null
    {
        $clave = $empresaId.'|'.$numero;
        if (! array_key_exists($clave, $cache['liquidacion'])) {
            $filas = Liquidacion_Sueldos::query()
                ->where('empresa_id', $empresaId)
                ->where('numero', $numero)
                ->limit(2)
                ->get();
            if ($filas->count() > 1) {
                $cache['liquidacion'][$clave] = false;
            } else {
                $cache['liquidacion'][$clave] = $filas->first();
            }
        }

        return $cache['liquidacion'][$clave];
    }

    /**
     * @param  list<string>  $errores
     */
    private function importeOpcional(mixed $valor, string $etiqueta, array &$errores): float
    {
        if ($this->celdaVacia($valor)) {
            return 0.0;
        }
        $numero = $this->parseImporte($valor);
        if ($numero === null) {
            $errores[] = $etiqueta.' inválido';

            return 0.0;
        }

        return $numero;
    }

    /**
     * @param  list<string>  $errores
     */
    private function enteroOpcional(mixed $valor, string $etiqueta, array &$errores): ?int
    {
        if ($this->celdaVacia($valor)) {
            return null;
        }
        $numero = $this->enteroCelda($valor);
        if ($numero === null) {
            $errores[] = $etiqueta.' inválido';

            return null;
        }

        return $numero;
    }

    /**
     * @param  list<string>  $errores
     */
    private function fechaOpcional(mixed $valor, string $etiqueta, array &$errores): ?string
    {
        if ($this->celdaVacia($valor)) {
            return null;
        }
        $fecha = $this->parseFecha($valor);
        if ($fecha === null) {
            $errores[] = $etiqueta.' inválida';

            return null;
        }

        return $fecha;
    }

    /**
     * @param  list<string>  $errores
     */
    private function periodoOpcional(mixed $valor, array &$errores): ?int
    {
        if ($this->celdaVacia($valor)) {
            return null;
        }
        if (is_numeric($valor) && ! is_string($valor)) {
            $n = (int) $valor;
            if ($n >= 190001 && $n <= 210012 && ($n % 100) >= 1 && ($n % 100) <= 12) {
                return $n;
            }
        }

        $texto = trim((string) $valor);
        if (preg_match('/^(\d{4})[-\/](\d{1,2})$/', $texto, $m)) {
            $mes = (int) $m[2];
            if ($mes >= 1 && $mes <= 12) {
                return ((int) $m[1]) * 100 + $mes;
            }
        }
        if (preg_match('/^(\d{1,2})[-\/](\d{4})$/', $texto, $m)) {
            $mes = (int) $m[1];
            if ($mes >= 1 && $mes <= 12) {
                return ((int) $m[2]) * 100 + $mes;
            }
        }
        if (preg_match('/^\d{6}$/', $texto)) {
            $n = (int) $texto;
            $mes = $n % 100;
            if ($mes >= 1 && $mes <= 12) {
                return $n;
            }
        }

        $errores[] = 'Período inválido (usar AAAAMM)';

        return null;
    }

    private function parseImporte(mixed $valor): ?float
    {
        if (is_int($valor) || is_float($valor)) {
            return round((float) $valor, 4);
        }

        $texto = trim((string) $valor);
        $texto = str_replace([' ', '$'], '', $texto);
        if ($texto === '') {
            return null;
        }

        $tieneComa = str_contains($texto, ',');
        $tienePunto = str_contains($texto, '.');
        if ($tieneComa && $tienePunto) {
            if (strrpos($texto, ',') > strrpos($texto, '.')) {
                $texto = str_replace('.', '', $texto);
                $texto = str_replace(',', '.', $texto);
            } else {
                $texto = str_replace(',', '', $texto);
            }
        } elseif ($tieneComa) {
            $texto = str_replace(',', '.', $texto);
        } elseif ($tienePunto && preg_match('/^\d{1,3}(\.\d{3})+$/', $texto)) {
            $texto = str_replace('.', '', $texto);
        }

        if (! is_numeric($texto)) {
            return null;
        }

        return round((float) $texto, 4);
    }

    private function parseFecha(mixed $valor): ?string
    {
        if (is_numeric($valor) && ! is_string($valor)) {
            $n = (float) $valor;
            if ($n >= 19000101 && $n <= 21001231 && floor($n) == $n) {
                $s = (string) (int) $n;
                if (strlen($s) === 8) {
                    $fecha = substr($s, 0, 4).'-'.substr($s, 4, 2).'-'.substr($s, 6, 2);
                    return $this->fechaValida($fecha) ? $fecha : null;
                }
            }
            if ($n > 0 && $n < 80000) {
                try {
                    return ExcelDate::excelToDateTimeObject($n)->format('Y-m-d');
                } catch (\Throwable $e) {
                    return null;
                }
            }
        }

        $texto = trim((string) $valor);
        if ($texto === '') {
            return null;
        }
        if (preg_match('/^\d{8}$/', $texto)) {
            $fecha = substr($texto, 0, 4).'-'.substr($texto, 4, 2).'-'.substr($texto, 6, 2);

            return $this->fechaValida($fecha) ? $fecha : null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd/m/y', 'Y/m/d'] as $fmt) {
            $dt = \DateTime::createFromFormat($fmt, $texto);
            $avisos = \DateTime::getLastErrors();
            if (! $dt instanceof \DateTime) {
                continue;
            }
            if (is_array($avisos) && (($avisos['warning_count'] ?? 0) > 0 || ($avisos['error_count'] ?? 0) > 0)) {
                continue;
            }
            if ($dt->format($fmt) !== $texto && $fmt !== 'd/m/y') {
                continue;
            }

            return $dt->format('Y-m-d');
        }

        return null;
    }

    private function fechaValida(string $ymd): bool
    {
        $dt = \DateTime::createFromFormat('Y-m-d', $ymd);

        return $dt !== false && $dt->format('Y-m-d') === $ymd;
    }

    private function enteroCelda(mixed $valor): ?int
    {
        if (is_int($valor)) {
            return $valor;
        }
        if (is_float($valor)) {
            return (int) round($valor);
        }
        $texto = trim((string) $valor);
        if ($texto === '') {
            return null;
        }
        if (preg_match('/^-?\d+$/', $texto)) {
            return (int) $texto;
        }
        if (is_numeric($texto)) {
            return (int) round((float) $texto);
        }

        return null;
    }

    private function textoVigencia(?string $desde, ?string $hasta, ?string $vto): string
    {
        if ($desde === null && $hasta === null && $vto === null) {
            return '';
        }
        $partes = [];
        if ($desde !== null || $hasta !== null) {
            $partes[] = ($desde ? $this->mostrarFecha($desde) : '…').' – '.($hasta ? $this->mostrarFecha($hasta) : '…');
        }
        if ($vto !== null) {
            $partes[] = 'vto '.$this->mostrarFecha($vto);
        }

        return implode(' · ', $partes);
    }

    private function mostrarFecha(string $ymd): string
    {
        $dt = \DateTime::createFromFormat('Y-m-d', $ymd);

        return $dt ? $dt->format('d/m/Y') : $ymd;
    }

    /**
     * @return array<string, mixed>
     */
    private function filaResultado(
        string $estado,
        string $mensaje,
        string $legajo,
        string $empleado,
        string $concepto,
        string $liquidacion,
        string $vigencia,
        string $estadoNovedad,
        ?float $valor1,
        ?float $valor2
    ): array {
        return [
            'estado' => $estado,
            'mensaje' => $mensaje,
            'legajo' => $legajo,
            'empleado' => $empleado,
            'concepto' => $concepto,
            'liquidacion' => $liquidacion,
            'vigencia' => $vigencia,
            'estado_novedad' => $estadoNovedad,
            'valor1' => $valor1 === null ? '' : number_format($valor1, 2, ',', '.'),
            'valor2' => $valor2 === null ? '' : number_format($valor2, 2, ',', '.'),
            'valor1_num' => $valor1 ?? 0,
        ];
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
