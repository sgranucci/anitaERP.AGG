<?php

namespace App\Services\Ventas\Gastronomia;

use App\ApiAnita;
use App\Models\Compras\Proveedor;
use App\Models\Configuracion\Empresa;
use App\Models\Contable\Centrocosto;
use App\Models\Stock\Recepcion_Proveedor;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Recepciones del informe gerente: prioriza ERP (recepcion_proveedor).
 * Anita (recepmae/recepmov) solo como fallback para fechas anteriores al corte ERP.
 */
final class GastronomiaRecepcionesAnitaService
{
    private const CLAVE_SEP = "\x1e";

    public function __construct(
        private readonly ApiAnita $apiAnita,
    ) {}

    /**
     * @return array{
     *   disponible:bool,
     *   error:?string,
     *   fuente?:string,
     *   sistema_anita?:string,
     *   empresa_anita?:int,
     *   centro_costo_codigo?:?int,
     *   dia:array{cantidad_comprobantes:int,importe_total:float,filas:list<array<string,mixed>>},
     *   mes:array{cantidad_comprobantes:int,importe_total:float,filas:list<array<string,mixed>>}
     * }
     */
    public function resumen(int $empresaId, string $fechaDesdeYmd, ?string $fechaHastaYmd = null): array
    {
        $vacio = [
            'disponible' => false,
            'error' => null,
            'fuente' => 'erp',
            'dia' => ['cantidad_comprobantes' => 0, 'importe_total' => 0.0, 'filas' => []],
            'mes' => ['cantidad_comprobantes' => 0, 'importe_total' => 0.0, 'filas' => []],
        ];

        $fechaHastaYmd = $fechaHastaYmd ?? $fechaDesdeYmd;

        try {
            $fechaDesde = Carbon::parse($fechaDesdeYmd)->startOfDay();
            $fechaHasta = Carbon::parse($fechaHastaYmd)->startOfDay();
        } catch (\Throwable) {
            $vacio['error'] = 'Fecha de jornada inválida.';

            return $vacio;
        }

        if ($fechaDesde->gt($fechaHasta)) {
            [$fechaDesde, $fechaHasta] = [$fechaHasta, $fechaDesde];
        }

        $mesDesde = $fechaHasta->copy()->startOfMonth();
        $mesHasta = $fechaHasta->copy()->endOfMonth();
        $centroCostoCodigo = $this->codigoCentroCostoRecepciones();
        $centroCostoId = $this->resolverCentroCostoId($centroCostoCodigo);
        $erpDesde = $this->fechaCorteErp();

        $periodoUsaAnita = $fechaDesde->lt($erpDesde);
        $mesUsaAnita = $mesDesde->lt($erpDesde);

        try {
            if (! $periodoUsaAnita && ! $mesUsaAnita) {
                return [
                    'disponible' => true,
                    'error' => null,
                    'fuente' => 'erp',
                    'centro_costo_codigo' => $centroCostoCodigo,
                    'dia' => $this->consultarErp(
                        $empresaId,
                        $fechaDesde->toDateString(),
                        $fechaHasta->toDateString(),
                        $centroCostoId,
                    ),
                    'mes' => $this->consultarErp(
                        $empresaId,
                        $mesDesde->toDateString(),
                        $mesHasta->toDateString(),
                        $centroCostoId,
                    ),
                ];
            }

            // Período y/o mes cruzan fechas sin datos ERP → Anita en el tramo viejo + ERP en el nuevo.
            $dia = $this->resumenRangoHibrido(
                $empresaId,
                $fechaDesde,
                $fechaHasta,
                $erpDesde,
                $centroCostoId,
                $centroCostoCodigo,
            );
            $mes = $this->resumenRangoHibrido(
                $empresaId,
                $mesDesde,
                $mesHasta,
                $erpDesde,
                $centroCostoId,
                $centroCostoCodigo,
            );

            $empresaAnita = $this->codigoEmpresaAnita($empresaId);

            return [
                'disponible' => true,
                'error' => null,
                'fuente' => 'hibrido',
                'sistema_anita' => trim((string) config('gastronomia.recepciones_anita_sistema', 'compras')) ?: 'compras',
                'empresa_anita' => $empresaAnita > 0 ? $empresaAnita : null,
                'centro_costo_codigo' => $centroCostoCodigo,
                'dia' => $dia,
                'mes' => $mes,
            ];
        } catch (\Throwable $e) {
            Log::warning('gastronomia.informe_gerente.recepciones', [
                'empresa_id' => $empresaId,
                'mensaje' => $e->getMessage(),
            ]);
            $vacio['error'] = $e->getMessage();

            return $vacio;
        }
    }

    /**
     * @return array{cantidad_comprobantes:int,importe_total:float,filas:list<array<string,mixed>>}
     */
    private function resumenRangoHibrido(
        int $empresaId,
        Carbon $desde,
        Carbon $hasta,
        Carbon $erpDesde,
        ?int $centroCostoId,
        ?int $centroCostoCodigo,
    ): array {
        $filas = [];

        if ($desde->lt($erpDesde)) {
            $anitaHasta = $hasta->lt($erpDesde)
                ? $hasta
                : $erpDesde->copy()->subDay();
            if ($anitaHasta->gte($desde)) {
                $filas = array_merge(
                    $filas,
                    $this->consultarAnitaAgregado(
                        $empresaId,
                        (int) $desde->format('Ymd'),
                        (int) $anitaHasta->format('Ymd'),
                        $centroCostoCodigo,
                    ),
                );
            }
        }

        if ($hasta->gte($erpDesde)) {
            $erpRangoDesde = $desde->gte($erpDesde) ? $desde : $erpDesde;
            $bloqueErp = $this->consultarErp(
                $empresaId,
                $erpRangoDesde->toDateString(),
                $hasta->toDateString(),
                $centroCostoId,
            );
            foreach ($bloqueErp['filas'] as $fila) {
                $filas[] = $this->filaVistaAAgregado($fila);
            }
        }

        usort($filas, function (array $a, array $b): int {
            $cmp = ((int) ($b['recm_fecha'] ?? 0) <=> (int) ($a['recm_fecha'] ?? 0));
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp((string) ($a['recm_proveedor'] ?? ''), (string) ($b['recm_proveedor'] ?? ''));
        });

        return $this->armarBloque($filas);
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array<string, mixed>
     */
    private function filaVistaAAgregado(array $fila): array
    {
        $fechaLabel = (string) ($fila['fecha'] ?? '');
        $fechaInt = 0;
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $fechaLabel, $m)) {
            $fechaInt = (int) ($m[3].$m[2].$m[1]);
        }

        return [
            'recm_proveedor' => (string) ($fila['proveedor'] ?? ''),
            'recm_tipo' => (string) ($fila['anita_tipo'] ?? ''),
            'recm_letra' => (string) ($fila['anita_letra'] ?? ''),
            'recm_sucursal' => (int) ($fila['anita_sucursal'] ?? 0),
            'recm_nro' => (int) ($fila['anita_nro'] ?? ($fila['numerorecepcion'] ?? 0)),
            'recm_fecha' => $fechaInt,
            'recm_estado' => (string) ($fila['estado'] ?? ''),
            'cantidad_lineas' => (int) ($fila['cantidad_lineas'] ?? 0),
            'importe' => (float) ($fila['importe'] ?? 0),
            'proveedor_nombre' => (string) ($fila['proveedor_nombre'] ?? ''),
            'comprobante_label' => (string) ($fila['comprobante'] ?? ''),
        ];
    }

    /**
     * @return array{cantidad_comprobantes:int,importe_total:float,filas:list<array<string,mixed>>}
     */
    private function consultarErp(
        int $empresaId,
        string $fechaDesde,
        string $fechaHasta,
        ?int $centroCostoId,
    ): array {
        if ($empresaId <= 0) {
            return ['cantidad_comprobantes' => 0, 'importe_total' => 0.0, 'filas' => []];
        }

        $query = Recepcion_Proveedor::query()
            ->with([
                'proveedores:id,codigo,nombre,fantasia',
                'recepcion_proveedor_articulos:id,recepcion_proveedor_id,cantidad,precio,centrocosto_id',
            ])
            ->where('empresa_id', $empresaId)
            ->where('estado', Recepcion_Proveedor::ESTADO_CONFIRMADA)
            ->whereDate('fecha', '>=', $fechaDesde)
            ->whereDate('fecha', '<=', $fechaHasta);

        if ($centroCostoId !== null && $centroCostoId > 0) {
            $query->where(function ($q) use ($centroCostoId) {
                $q->where('centrocosto_id', $centroCostoId)
                    ->orWhereHas(
                        'recepcion_proveedor_articulos',
                        fn ($a) => $a->where('centrocosto_id', $centroCostoId),
                    );
            });
        }

        $recepciones = $query
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get([
                'id',
                'tipo',
                'proveedor_id',
                'fecha',
                'numerorecepcion',
                'estado',
                'moneda_id',
                'cotizacion',
                'centrocosto_id',
                'anita_tipo',
                'anita_letra',
                'anita_sucursal',
                'anita_nro',
            ]);

        $filas = [];
        $importeTotal = 0.0;

        foreach ($recepciones as $rec) {
            $signo = ((string) $rec->tipo === Recepcion_Proveedor::TIPO_DEVOLUCION) ? -1.0 : 1.0;
            $importe = 0.0;
            $cantidadLineas = 0;
            $headerCcOk = $centroCostoId === null
                || $centroCostoId <= 0
                || (int) ($rec->centrocosto_id ?? 0) === $centroCostoId;

            foreach ($rec->recepcion_proveedor_articulos as $linea) {
                if (! $headerCcOk) {
                    if ((int) ($linea->centrocosto_id ?? 0) !== $centroCostoId) {
                        continue;
                    }
                }
                $cantidadLineas++;
                $importe += (float) $linea->cantidad * (float) $linea->precio;
            }

            if ($cantidadLineas <= 0) {
                continue;
            }

            $cotizacion = (float) ($rec->cotizacion ?? 0);
            $monedaId = (int) ($rec->moneda_id ?? 1);
            if ($monedaId > 1 && $cotizacion > 1.0001) {
                $importe *= $cotizacion;
            }

            $importe = round($signo * $importe, 2);
            $importeTotal = round($importeTotal + $importe, 2);

            $proveedor = $rec->proveedores;
            $codigoProv = trim((string) ($proveedor->codigo ?? ''));
            $nombreProv = trim((string) ($proveedor->nombre ?? ''));
            if ($nombreProv === '') {
                $nombreProv = trim((string) ($proveedor->fantasia ?? ''));
            }
            if ($nombreProv === '') {
                $nombreProv = $codigoProv !== '' ? $codigoProv : 'Sin proveedor';
            }

            $anitaTipo = trim((string) ($rec->anita_tipo ?? ''));
            $anitaLetra = trim((string) ($rec->anita_letra ?? ''));
            $anitaSuc = (int) ($rec->anita_sucursal ?? 0);
            $anitaNro = (int) ($rec->anita_nro ?? 0);
            $nroErp = (int) ($rec->numerorecepcion ?? 0);

            if ($anitaTipo !== '' || $anitaNro > 0) {
                $comprobante = trim(sprintf(
                    '%s %s %d-%d',
                    $anitaTipo,
                    $anitaLetra,
                    $anitaSuc,
                    $anitaNro > 0 ? $anitaNro : $nroErp,
                ));
            } else {
                $comprobante = 'COM ERP '.$nroErp;
            }

            $filas[] = [
                'proveedor' => $codigoProv,
                'proveedor_nombre' => $nombreProv,
                'comprobante' => $comprobante,
                'fecha' => $rec->fecha?->format('d/m/Y') ?? '—',
                'estado' => (string) ($rec->estado ?? ''),
                'cantidad_lineas' => $cantidadLineas,
                'importe' => $importe,
                'anita_tipo' => $anitaTipo,
                'anita_letra' => $anitaLetra,
                'anita_sucursal' => $anitaSuc,
                'anita_nro' => $anitaNro > 0 ? $anitaNro : $nroErp,
                'numerorecepcion' => $nroErp,
            ];
        }

        return [
            'cantidad_comprobantes' => count($filas),
            'importe_total' => $importeTotal,
            'filas' => $filas,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function consultarAnitaAgregado(
        int $empresaId,
        int $fechaDesde,
        int $fechaHasta,
        ?int $centroCostoCodigo,
    ): array {
        $empresaAnita = $this->codigoEmpresaAnita($empresaId);
        if ($empresaAnita <= 0) {
            throw new \RuntimeException('Empresa sin código Anita configurado (fallback recepciones antiguas).');
        }

        $sistema = trim((string) config('gastronomia.recepciones_anita_sistema', 'compras')) ?: 'compras';
        $filas = $this->consultarAgregadoAnita(
            $empresaAnita,
            $fechaDesde,
            $fechaHasta,
            $sistema,
            $centroCostoCodigo,
        );
        $mapaNombres = $this->mapaNombresProveedor(
            $this->codigosProveedorDesdeAgregado($filas),
            $sistema,
        );

        foreach ($filas as &$fila) {
            $codigo = (string) ($fila['recm_proveedor'] ?? '');
            $fila['proveedor_nombre'] = $this->nombreProveedor($codigo, $mapaNombres);
        }
        unset($fila);

        return $filas;
    }

    private function fechaCorteErp(): Carbon
    {
        $cfg = trim((string) config('gastronomia.recepciones_erp_desde', ''));
        if ($cfg !== '') {
            try {
                return Carbon::parse($cfg)->startOfDay();
            } catch (\Throwable) {
                // sigue al mínimo de tabla
            }
        }

        $min = DB::table('recepcion_proveedor')->min('fecha');
        if ($min) {
            try {
                return Carbon::parse((string) $min)->startOfDay();
            } catch (\Throwable) {
                // fallback fijo
            }
        }

        return Carbon::parse('2025-01-01')->startOfDay();
    }

    private function resolverCentroCostoId(?int $codigo): ?int
    {
        if ($codigo === null || $codigo <= 0) {
            return null;
        }

        $variantes = array_values(array_unique([
            (string) $codigo,
            str_pad((string) $codigo, 2, '0', STR_PAD_LEFT),
            ltrim((string) $codigo, '0') ?: '0',
        ]));

        $id = Centrocosto::query()
            ->whereIn('codigo', $variantes)
            ->value('id');

        return $id ? (int) $id : null;
    }

    private function codigoEmpresaAnita(int $empresaId): int
    {
        if ($empresaId <= 0) {
            return 0;
        }

        $empresa = Empresa::query()->find($empresaId);
        if ($empresa === null) {
            return 0;
        }

        $codigo = trim((string) ($empresa->codigo ?? ''));
        if ($codigo === '' || ! ctype_digit($codigo)) {
            return (int) $empresaId;
        }

        return (int) $codigo;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function consultarAgregadoAnita(
        int $empresaAnita,
        int $fechaDesde,
        int $fechaHasta,
        string $sistema,
        ?int $centroCostoCodigo = null,
    ): array {
        $lineas = $this->listarRecepmov($empresaAnita, $fechaDesde, $fechaHasta, $sistema, $centroCostoCodigo);
        $cabeceras = $this->listarRecepmae($empresaAnita, $fechaDesde, $fechaHasta, $sistema);

        /** @var array<string, array<string, mixed>> $porClave */
        $porClave = [];

        foreach ($lineas as $row) {
            $arr = $row instanceof \stdClass ? get_object_vars($row) : (array) $row;
            $clave = $this->claveComprobante($arr, 'recv_');
            if ($clave === '') {
                continue;
            }

            if (! isset($porClave[$clave])) {
                $porClave[$clave] = [
                    'recm_proveedor' => trim((string) ($this->campo($arr, 'recv_proveedor') ?? '')),
                    'recm_tipo' => trim((string) ($this->campo($arr, 'recv_tipo') ?? '')),
                    'recm_letra' => trim((string) ($this->campo($arr, 'recv_letra') ?? '')),
                    'recm_sucursal' => (int) ($this->campo($arr, 'recv_sucursal') ?? 0),
                    'recm_nro' => (int) ($this->campo($arr, 'recv_nro') ?? 0),
                    'recm_fecha' => (int) ($this->campo($arr, 'recv_fecha') ?? 0),
                    'recm_estado' => '',
                    'cantidad_lineas' => 0,
                    'importe' => 0.0,
                ];
            }

            $cant = (float) ($this->campo($arr, 'recv_cantidad') ?? 0);
            $precio = (float) ($this->campo($arr, 'recv_precio') ?? 0);
            $porClave[$clave]['cantidad_lineas']++;
            $porClave[$clave]['importe'] = round($porClave[$clave]['importe'] + ($cant * $precio), 2);
        }

        foreach ($cabeceras as $row) {
            $arr = $row instanceof \stdClass ? get_object_vars($row) : (array) $row;
            $clave = $this->claveComprobante($arr, 'recm_');
            if ($clave === '' || ! isset($porClave[$clave])) {
                continue;
            }

            $porClave[$clave]['recm_estado'] = trim((string) ($this->campo($arr, 'recm_estado') ?? ''));
        }

        return array_values($porClave);
    }

    /**
     * @return list<object>
     */
    private function listarRecepmae(int $empresaAnita, int $fechaDesde, int $fechaHasta, string $sistema): array
    {
        $where = ' WHERE recm_empresa = '.$empresaAnita
            .' AND recm_fecha >= '.$fechaDesde
            .' AND recm_fecha <= '.$fechaHasta.' ';

        return $this->listarAnita([
            'tabla' => 'recepmae',
            'campos' => 'recm_proveedor, recm_tipo, recm_letra, recm_sucursal, recm_nro, recm_fecha, recm_estado',
            'whereArmado' => $where,
            'orderBy' => 'recm_fecha DESC, recm_proveedor, recm_nro',
            'sistema' => $sistema,
        ]);
    }

    /**
     * @return list<object>
     */
    private function listarRecepmov(
        int $empresaAnita,
        int $fechaDesde,
        int $fechaHasta,
        string $sistema,
        ?int $centroCostoCodigo = null,
    ): array {
        $where = ' WHERE recv_empresa = '.$empresaAnita
            .' AND recv_fecha >= '.$fechaDesde
            .' AND recv_fecha <= '.$fechaHasta;
        if ($centroCostoCodigo !== null && $centroCostoCodigo > 0) {
            $where .= ' AND recv_ccosto = '.$centroCostoCodigo;
        }
        $where .= ' ';

        return $this->listarAnita([
            'tabla' => 'recepmov',
            'campos' => 'recv_proveedor, recv_tipo, recv_letra, recv_sucursal, recv_nro, '
                .'recv_cantidad, recv_precio, recv_fecha, recv_empresa',
            'whereArmado' => $where,
            'orderBy' => 'recv_fecha DESC, recv_proveedor, recv_nro',
            'sistema' => $sistema,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<object>
     */
    private function listarAnita(array $payload): array
    {
        $payload['acc'] = 'list';
        $respuesta = (string) $this->apiAnita->apiCall($payload);

        $err = ApiAnita::extraerMensajeError($respuesta === '' ? null : $respuesta);
        if ($err !== null) {
            throw new \RuntimeException($err);
        }

        return ApiAnita::decodificarListaFilas($respuesta);
    }

    private function codigoCentroCostoRecepciones(): ?int
    {
        $raw = config('gastronomia.recepciones_centro_costo_codigo');
        if ($raw === null || $raw === '') {
            return null;
        }

        $codigo = (int) $raw;

        return $codigo > 0 ? $codigo : null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function claveComprobante(array $row, string $prefijo): string
    {
        $prov = trim((string) ($this->campo($row, $prefijo.'proveedor') ?? ''));
        $tipo = trim((string) ($this->campo($row, $prefijo.'tipo') ?? ''));
        $letra = trim((string) ($this->campo($row, $prefijo.'letra') ?? ''));
        $suc = (int) ($this->campo($row, $prefijo.'sucursal') ?? 0);
        $nro = (int) ($this->campo($row, $prefijo.'nro') ?? 0);

        if ($prov === '' && $nro === 0) {
            return '';
        }

        return implode(self::CLAVE_SEP, [$prov, $tipo, $letra, (string) $suc, (string) $nro]);
    }

    /**
     * @param  list<array<string, mixed>>  ...$bloquesAgregados
     * @return list<string>
     */
    private function codigosProveedorDesdeAgregado(array ...$bloquesAgregados): array
    {
        $codigos = [];
        foreach ($bloquesAgregados as $bloque) {
            foreach ($bloque as $row) {
                $codigo = trim((string) ($row['recm_proveedor'] ?? ''));
                if ($codigo !== '') {
                    $codigos[$codigo] = true;
                }
            }
        }

        return array_keys($codigos);
    }

    /**
     * @param  list<string>  $codigosAnita
     * @return array<string, string>
     */
    private function mapaNombresProveedor(array $codigosAnita, string $sistema): array
    {
        if ($codigosAnita === []) {
            return [];
        }

        /** @var array<string, string> $mapa */
        $mapa = [];

        $codigosErp = [];
        foreach ($codigosAnita as $codigoAnita) {
            $sinCeros = ltrim(trim($codigoAnita), '0');
            if ($sinCeros !== '') {
                $codigosErp[$sinCeros] = true;
            }
        }

        if ($codigosErp !== []) {
            $proveedores = Proveedor::query()
                ->whereIn('codigo', array_keys($codigosErp))
                ->get(['codigo', 'nombre', 'fantasia']);

            /** @var array<string, string> $erpPorCodigo */
            $erpPorCodigo = [];
            foreach ($proveedores as $prov) {
                $nombre = trim((string) ($prov->nombre ?? ''));
                if ($nombre === '') {
                    $nombre = trim((string) ($prov->fantasia ?? ''));
                }
                if ($nombre !== '') {
                    $erpPorCodigo[(string) $prov->codigo] = $nombre;
                }
            }

            foreach ($codigosAnita as $codigoAnita) {
                $sinCeros = ltrim(trim($codigoAnita), '0');
                if ($sinCeros !== '' && isset($erpPorCodigo[$sinCeros])) {
                    $mapa[$codigoAnita] = $erpPorCodigo[$sinCeros];
                }
            }
        }

        return $mapa;
    }

    private function nombreProveedor(string $codigoAnita, array $mapaNombres): string
    {
        $codigoAnita = trim($codigoAnita);
        if ($codigoAnita === '') {
            return 'Sin proveedor';
        }

        $nombre = trim((string) ($mapaNombres[$codigoAnita] ?? ''));
        if ($nombre !== '') {
            return $nombre;
        }

        $sinCeros = ltrim($codigoAnita, '0');

        return $sinCeros !== '' ? $sinCeros : $codigoAnita;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array{cantidad_comprobantes:int,importe_total:float,filas:list<array<string,mixed>>}
     */
    private function armarBloque(array $filas): array
    {
        $out = [];
        $importeTotal = 0.0;

        foreach ($filas as $row) {
            $importe = round((float) ($row['importe'] ?? 0), 2);
            $importeTotal = round($importeTotal + $importe, 2);
            $fechaInt = (int) ($row['recm_fecha'] ?? 0);
            $codigoProveedor = (string) ($row['recm_proveedor'] ?? '');
            $comprobante = trim((string) ($row['comprobante_label'] ?? ''));
            if ($comprobante === '') {
                $comprobante = trim(sprintf(
                    '%s %s %d-%d',
                    (string) ($row['recm_tipo'] ?? ''),
                    (string) ($row['recm_letra'] ?? ''),
                    (int) ($row['recm_sucursal'] ?? 0),
                    (int) ($row['recm_nro'] ?? 0),
                ));
            }
            $nombre = trim((string) ($row['proveedor_nombre'] ?? ''));
            if ($nombre === '') {
                $nombre = $codigoProveedor !== '' ? $codigoProveedor : 'Sin proveedor';
            }

            $out[] = [
                'proveedor' => $codigoProveedor,
                'proveedor_nombre' => $nombre,
                'comprobante' => $comprobante,
                'fecha' => $this->fechaIntALabel($fechaInt),
                'estado' => (string) ($row['recm_estado'] ?? ''),
                'cantidad_lineas' => (int) ($row['cantidad_lineas'] ?? 0),
                'importe' => $importe,
            ];
        }

        return [
            'cantidad_comprobantes' => count($out),
            'importe_total' => $importeTotal,
            'filas' => $out,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function campo(array $row, string $nombre): mixed
    {
        if (array_key_exists($nombre, $row)) {
            return $row[$nombre];
        }
        $lower = strtolower($nombre);
        foreach ($row as $k => $v) {
            if (strtolower((string) $k) === $lower) {
                return $v;
            }
        }

        return null;
    }

    private function fechaIntALabel(int $yyyymmdd): string
    {
        if ($yyyymmdd < 19000101) {
            return '—';
        }
        $s = (string) $yyyymmdd;
        if (strlen($s) !== 8) {
            return $s;
        }

        return substr($s, 6, 2).'/'.substr($s, 4, 2).'/'.substr($s, 0, 4);
    }
}
