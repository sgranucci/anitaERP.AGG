<?php

namespace App\Services\Ventas\Gastronomia;

use App\ApiAnita;
use App\Models\Compras\Proveedor;
use App\Models\Configuracion\Empresa;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Recepciones de mercadería (recepmae / recepmov) vía bridge Anita (acc=list).
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
     *   sistema_anita?:string,
     *   empresa_anita?:int,
     *   centro_costo_codigo?:?int,
     *   dia:array{cantidad_comprobantes:int,importe_total:float,filas:list<array<string,mixed>>},
     *   mes:array{cantidad_comprobantes:int,importe_total:float,filas:list<array<string,mixed>>}
     * }
     */
    public function resumen(int $empresaId, string $fechaJornadaYmd): array
    {
        $vacio = [
            'disponible' => false,
            'error' => null,
            'dia' => ['cantidad_comprobantes' => 0, 'importe_total' => 0.0, 'filas' => []],
            'mes' => ['cantidad_comprobantes' => 0, 'importe_total' => 0.0, 'filas' => []],
        ];

        $empresaAnita = $this->codigoEmpresaAnita($empresaId);
        if ($empresaAnita <= 0) {
            $vacio['error'] = 'Empresa sin código Anita configurado.';

            return $vacio;
        }

        try {
            $fecha = Carbon::parse($fechaJornadaYmd);
        } catch (\Throwable) {
            $vacio['error'] = 'Fecha de jornada inválida.';

            return $vacio;
        }

        $fechaDia = (int) $fecha->format('Ymd');
        $fechaMesDesde = (int) $fecha->copy()->startOfMonth()->format('Ymd');
        $fechaMesHasta = (int) $fecha->copy()->endOfMonth()->format('Ymd');
        $sistema = trim((string) config('gastronomia.recepciones_anita_sistema', 'compras')) ?: 'compras';
        $centroCostoCodigo = $this->codigoCentroCostoRecepciones();

        try {
            $filasDia = $this->consultarAgregado($empresaAnita, $fechaDia, $fechaDia, $sistema, $centroCostoCodigo);
            $filasMes = $this->consultarAgregado($empresaAnita, $fechaMesDesde, $fechaMesHasta, $sistema, $centroCostoCodigo);
        } catch (\Throwable $e) {
            Log::warning('gastronomia.informe_gerente.recepciones_anita', [
                'empresa_id' => $empresaId,
                'mensaje' => $e->getMessage(),
            ]);
            $vacio['error'] = $e->getMessage();

            return $vacio;
        }

        $mapaNombres = $this->mapaNombresProveedor(
            $this->codigosProveedorDesdeAgregado($filasDia, $filasMes),
            $sistema,
        );

        return [
            'disponible' => true,
            'error' => null,
            'sistema_anita' => $sistema,
            'empresa_anita' => $empresaAnita,
            'centro_costo_codigo' => $centroCostoCodigo,
            'dia' => $this->armarBloque($filasDia, $mapaNombres),
            'mes' => $this->armarBloque($filasMes, $mapaNombres),
        ];
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
    private function consultarAgregado(
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

        $filas = array_values($porClave);
        usort($filas, function (array $a, array $b): int {
            $cmp = ($b['recm_fecha'] <=> $a['recm_fecha']);
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp((string) $a['recm_proveedor'], (string) $b['recm_proveedor']);
        });

        return $filas;
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
     * @return array<string, string> código Anita → nombre
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

        $pendientes = array_values(array_filter(
            $codigosAnita,
            fn (string $c) => ! isset($mapa[$c]),
        ));

        if ($pendientes !== []) {
            $mapa = array_merge($mapa, $this->mapaNombresProveedorAnita($pendientes, $sistema));
        }

        return $mapa;
    }

    /**
     * @param  list<string>  $codigosAnita
     * @return array<string, string>
     */
    private function mapaNombresProveedorAnita(array $codigosAnita, string $sistema): array
    {
        if ($codigosAnita === []) {
            return [];
        }

        $quoted = array_map(
            fn (string $c) => "'".addslashes(trim($c))."'",
            $codigosAnita,
        );
        $where = ' WHERE prom_proveedor IN ('.implode(', ', $quoted).') ';

        try {
            $rows = $this->listarAnita([
                'tabla' => 'promae',
                'campos' => 'prom_proveedor, prom_nombre, prom_fantasia',
                'whereArmado' => $where,
                'orderBy' => 'prom_proveedor',
                'sistema' => $sistema,
            ]);
        } catch (\Throwable $e) {
            Log::debug('gastronomia.recepciones_anita.promae_nombres', [
                'mensaje' => $e->getMessage(),
            ]);

            return [];
        }

        /** @var array<string, string> $mapa */
        $mapa = [];
        foreach ($rows as $row) {
            $arr = $row instanceof \stdClass ? get_object_vars($row) : (array) $row;
            $codigo = trim((string) ($this->campo($arr, 'prom_proveedor') ?? ''));
            if ($codigo === '') {
                continue;
            }
            $nombre = trim((string) ($this->campo($arr, 'prom_nombre') ?? ''));
            if ($nombre === '') {
                $nombre = trim((string) ($this->campo($arr, 'prom_fantasia') ?? ''));
            }
            if ($nombre !== '') {
                $mapa[$codigo] = $nombre;
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
     * @param  array<string, string>  $mapaNombres
     * @return array{cantidad_comprobantes:int,importe_total:float,filas:list<array<string,mixed>>}
     */
    private function armarBloque(array $filas, array $mapaNombres = []): array
    {
        $out = [];
        $importeTotal = 0.0;

        foreach ($filas as $row) {
            $importe = round((float) ($row['importe'] ?? 0), 2);
            $importeTotal = round($importeTotal + $importe, 2);
            $fechaInt = (int) ($row['recm_fecha'] ?? 0);
            $codigoProveedor = (string) ($row['recm_proveedor'] ?? '');
            $out[] = [
                'proveedor' => $codigoProveedor,
                'proveedor_nombre' => $this->nombreProveedor($codigoProveedor, $mapaNombres),
                'comprobante' => trim(sprintf(
                    '%s %s %d-%d',
                    (string) ($row['recm_tipo'] ?? ''),
                    (string) ($row['recm_letra'] ?? ''),
                    (int) ($row['recm_sucursal'] ?? 0),
                    (int) ($row['recm_nro'] ?? 0),
                )),
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
