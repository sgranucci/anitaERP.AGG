<?php

namespace App\Support\Ventas;

use App\ApiAnita;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Venta;
use App\Support\Ventas\IvaVentas\IvaVentasDesgloseSupport;
use Illuminate\Support\Collection;

/**
 * Estado de archivos Informix de una factura de pedido (El Bierzo).
 * Distingue Anita vacía vs. cabecera/detalle/CAE incompletos.
 * En compaux/stkmov no basta con “hay filas”: tiene que cerrar renglones y kilos del payload.
 */
final class PedidoFacturaAnitaArchivosSupport
{
    public const PATH_VILLAFRANCA = '/usr2/villafranca';

    /** Tolerancia de kilos al comparar Anita vs payload (Bierzo redondea a 1 decimal). */
    public const TOLERANCIA_KILOS_DETALLE = 0.05;

    /**
     * @param  array<string, mixed>|null  $anitaPendiente
     * @param  array<string, mixed>|null  $vencaePendiente
     * @return list<string>
     */
    public static function esperados(?array $anitaPendiente, ?array $vencaePendiente): array
    {
        if (! is_array($anitaPendiente)) {
            return self::debeEsperarVencae($anitaPendiente, $vencaePendiente) ? ['vencae'] : [];
        }

        $esperados = ['venta', 'comprob'];

        $conceptos = is_array($anitaPendiente['conceptos_totales'] ?? null)
            ? $anitaPendiente['conceptos_totales']
            : [];
        foreach ($conceptos as $concepto) {
            if (! is_array($concepto)) {
                continue;
            }
            $nombre = (string) ($concepto['concepto'] ?? '');
            // Solo IVA liquidado: stripos('Iva') también matchea «Percepcion IVA».
            if ($nombre !== '' && IvaVentasDesgloseSupport::esConceptoIvaLiquidado($nombre)) {
                $esperados[] = 'vengrav';
                break;
            }
        }
        foreach ($conceptos as $concepto) {
            if (! is_array($concepto)) {
                continue;
            }
            if ((int) ($concepto['jurisdiccion'] ?? 0) > 0) {
                $esperados[] = 'venibr';
                break;
            }
        }

        $cuotas = is_array($anitaPendiente['cuentacorriente'] ?? null)
            ? $anitaPendiente['cuentacorriente']
            : [];
        if ($cuotas !== []) {
            $esperados[] = 'climov';
        }

        $lineas = is_array($anitaPendiente['data_factura'] ?? null)
            ? $anitaPendiente['data_factura']
            : [];
        if ($lineas !== []) {
            $esperados[] = 'compaux';
            foreach ($lineas as $linea) {
                if (is_array($linea) && ! empty($linea['articulo_id'])) {
                    $esperados[] = 'stkmov';
                    break;
                }
            }
        }

        if (self::debeEsperarVencae($anitaPendiente, $vencaePendiente)) {
            $esperados[] = 'vencae';
        }

        $esperados[] = 'ctamov';

        return array_values(array_unique($esperados));
    }

    /**
     * @param  array<string, mixed>|null  $anitaPendiente
     * @param  array<string, mixed>|null  $vencaePendiente
     * @return array{
     *   ok: bool,
     *   error: ?string,
     *   vacio: bool,
     *   completo: bool,
     *   presentes: list<string>,
     *   faltantes: list<string>,
     *   esperados: list<string>,
     *   parciales: list<string>,
     *   detalle: array<string, mixed>
     * }
     */
    public static function inspeccionar(?array $anitaPendiente, ?array $vencaePendiente): array
    {
        $vacio = [
            'ok' => true,
            'error' => null,
            'vacio' => true,
            'completo' => false,
            'presentes' => [],
            'faltantes' => [],
            'esperados' => [],
            'parciales' => [],
            'detalle' => [],
        ];

        $clave = self::claveDesdePayload($anitaPendiente, $vencaePendiente);
        if ($clave === null) {
            return $vacio + ['ok' => false, 'error' => 'Sin clave de comprobante para inspeccionar Anita'];
        }

        $esperados = self::esperados($anitaPendiente, $vencaePendiente);
        $vacio['esperados'] = $esperados;
        $vacio['faltantes'] = $esperados;

        $presentes = [];
        foreach ($esperados as $tabla) {
            $consulta = self::existeTabla($tabla, $clave);
            if ($consulta['error'] !== null) {
                return [
                    'ok' => false,
                    'error' => $consulta['error'],
                    'vacio' => false,
                    'completo' => false,
                    'presentes' => $presentes,
                    'faltantes' => array_values(array_diff($esperados, $presentes)),
                    'esperados' => $esperados,
                    'parciales' => [],
                    'detalle' => [],
                ];
            }
            if ($consulta['existe']) {
                $presentes[] = $tabla;
            }
        }

        $faltantes = array_values(array_diff($esperados, $presentes));
        $parciales = [];
        $detalle = [];

        $verificacion = self::verificarDetalleContraPayload($anitaPendiente, $clave, $presentes);
        if ($verificacion['error'] !== null) {
            return [
                'ok' => false,
                'error' => $verificacion['error'],
                'vacio' => $presentes === [],
                'completo' => false,
                'presentes' => $presentes,
                'faltantes' => $faltantes,
                'esperados' => $esperados,
                'parciales' => [],
                'detalle' => $verificacion['detalle'],
            ];
        }

        foreach ($verificacion['parciales'] as $tablaParcial) {
            $parciales[] = $tablaParcial;
            $presentes = array_values(array_filter(
                $presentes,
                static fn (string $t): bool => $t !== $tablaParcial,
            ));
            if (! in_array($tablaParcial, $faltantes, true)) {
                $faltantes[] = $tablaParcial;
            }
        }
        $detalle = $verificacion['detalle'];

        return [
            'ok' => true,
            'error' => null,
            'vacio' => $presentes === [] && $parciales === [],
            'completo' => $faltantes === [],
            'presentes' => $presentes,
            'faltantes' => array_values($faltantes),
            'esperados' => $esperados,
            'parciales' => array_values($parciales),
            'detalle' => $detalle,
        ];
    }

    /**
     * Renglones y kilos que deberían estar en Anita según el payload del ERP.
     *
     * @param  array<string, mixed>|null  $anitaPendiente
     * @return array{
     *   compaux_lineas: int,
     *   compaux_kilos: float,
     *   stkmov_lineas: int,
     *   stkmov_kilos: float
     * }
     */
    public static function expectativaDetalleDesdePayload(?array $anitaPendiente): array
    {
        $out = [
            'compaux_lineas' => 0,
            'compaux_kilos' => 0.0,
            'stkmov_lineas' => 0,
            'stkmov_kilos' => 0.0,
        ];
        if (! is_array($anitaPendiente)) {
            return $out;
        }

        $lineas = is_array($anitaPendiente['data_factura'] ?? null)
            ? $anitaPendiente['data_factura']
            : [];

        foreach ($lineas as $linea) {
            if (! is_array($linea)) {
                continue;
            }
            $cantidad = (float) ($linea['cantidad'] ?? 0);
            $pieza = (float) ($linea['pieza'] ?? 0);
            $caja = (float) ($linea['caja'] ?? 0);
            if (abs($cantidad) < 0.00001 && abs($pieza) < 0.00001 && abs($caja) < 0.00001) {
                continue;
            }

            $out['compaux_lineas']++;
            $out['compaux_kilos'] += $cantidad;

            $sku = trim((string) ($linea['sku'] ?? ''));
            if ($sku !== '' && strcasecmp($sku, 'texto') !== 0 && ! empty($linea['articulo_id'])) {
                $out['stkmov_lineas']++;
                $out['stkmov_kilos'] += $cantidad;
            }
        }

        $out['compaux_kilos'] = round($out['compaux_kilos'], 4);
        $out['stkmov_kilos'] = round($out['stkmov_kilos'], 4);

        return $out;
    }

    /**
     * Borra compaux/stkmov parciales antes de reinsertar (evita duplicar renglones).
     *
     * @param  array<string, mixed>  $anitaPendiente
     * @param  list<string>  $tablas
     */
    public static function borrarTablasDetalleParcial(array $anitaPendiente, array $tablas): void
    {
        $clave = self::claveDesdePayload($anitaPendiente, null);
        if ($clave === null) {
            return;
        }

        foreach ($tablas as $tabla) {
            if ($tabla !== 'compaux' && $tabla !== 'stkmov') {
                continue;
            }
            self::borrarTablaDetalle($tabla, $clave);
        }
    }

    /**
     * Puntos de venta de Villafranca (comprobante dividido). No se listan en el ABM de pedido.
     *
     * @return list<int>
     */
    public static function idsPuntoVentaDivision(): array
    {
        return array_values(array_filter([
            (int) config('facturacion.PUNTOVENTA_DIVISION_ID', 0),
            (int) config('facturacion.PUNTOVENTA_DIVISION_LOCAL_ID', 0),
            VillafrancaFacturacionSupport::idPuntoVentaReparto101(),
        ]));
    }

    public static function esPuntoVentaDivision(int $puntoventaId): bool
    {
        return $puntoventaId > 0 && in_array($puntoventaId, self::idsPuntoVentaDivision(), true);
    }

    public static function esVentaVisible($venta): bool
    {
        if (! is_object($venta)) {
            return false;
        }

        return ! self::esPuntoVentaDivision((int) ($venta->puntoventa_id ?? 0));
    }

    public static function esVentaIdVisible(int $ventaId): bool
    {
        if ($ventaId <= 0) {
            return false;
        }

        $puntoventaId = (int) (Venta::query()->whereKey($ventaId)->value('puntoventa_id') ?? 0);

        return ! self::esPuntoVentaDivision($puntoventaId);
    }

    /**
     * Facturas del pedido visibles en el ABM: solo El Bierzo, no Villafranca.
     *
     * @param  iterable<int, mixed>|null  $ventas
     */
    public static function ventasVisiblesEnPedido($ventas): Collection
    {
        $idsDivision = self::idsPuntoVentaDivision();

        return collect($ventas ?? [])
            ->reject(static function ($venta) use ($idsDivision) {
                return in_array((int) ($venta->puntoventa_id ?? 0), $idsDivision, true);
            })
            ->sortByDesc('id')
            ->values();
    }

    /**
     * Path Anita según sucursal. FAC A 8 / A 10 (Bierzo) nunca van a /usr2/villafranca,
     * aunque el payload traiga path_sistema (fuga del flag de comprobante dividido).
     */
    public static function pathSistema(?array $anitaPendiente): ?string
    {
        return self::resolverPathSistema(
            isset($anitaPendiente['path_sistema']) ? (string) $anitaPendiente['path_sistema'] : null,
            (string) ($anitaPendiente['puntoventa_codigo'] ?? ''),
            self::codigosPuntoVentaDivision(),
        );
    }

    public static function pathSistemaParaSucursal(int|string $sucursal): ?string
    {
        return self::resolverPathSistema(null, (string) $sucursal, self::codigosPuntoVentaDivision());
    }

    /**
     * @param  list<string|int>  $codigosDivision
     */
    public static function resolverPathSistema(?string $pathDeclarado, string $sucursal, array $codigosDivision): ?string
    {
        $norm = self::normalizarSucursal($sucursal);
        if ($norm !== '') {
            foreach ($codigosDivision as $codigo) {
                if (self::normalizarSucursal((string) $codigo) === $norm) {
                    return self::PATH_VILLAFRANCA;
                }
            }

            return null;
        }

        return trim((string) $pathDeclarado) === self::PATH_VILLAFRANCA
            ? self::PATH_VILLAFRANCA
            : null;
    }

    /**
     * @return list<string>
     */
    public static function codigosPuntoVentaDivision(): array
    {
        $idsDivision = self::idsPuntoVentaDivision();
        if ($idsDivision === []) {
            return [];
        }

        return Puntoventa::query()
            ->whereIn('id', $idsDivision)
            ->pluck('codigo')
            ->map(static fn ($codigo) => (string) $codigo)
            ->all();
    }

    public static function normalizarSucursal(string $codigo): string
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return '';
        }

        return ctype_digit($codigo) ? str_pad($codigo, 5, '0', STR_PAD_LEFT) : $codigo;
    }

    /**
     * @param  array<string, mixed>|null  $anitaPendiente
     * @param  array<string, mixed>|null  $vencaePendiente
     * @return array{tipo: string, tipo_venta: string, letra: string, sucursal: string, numero: string, path: ?string}|null
     */
    public static function claveDesdePayload(?array $anitaPendiente, ?array $vencaePendiente): ?array
    {
        $venta = is_array($anitaPendiente['venta'] ?? null) ? $anitaPendiente['venta'] : [];
        $tipo = strtoupper(substr((string) ($venta['codigo'] ?? $vencaePendiente['tipo_anita'] ?? ''), 0, 3));
        $letra = (string) ($anitaPendiente['letra'] ?? $vencaePendiente['letra'] ?? '');
        $sucursal = (string) ($anitaPendiente['puntoventa_codigo'] ?? $vencaePendiente['puntoventa_codigo'] ?? '');
        $numero = (string) ($venta['numerocomprobante'] ?? $vencaePendiente['numero_comprobante'] ?? '');
        if ($tipo === '' || $letra === '' || $sucursal === '' || $numero === '' || $numero === '0') {
            return null;
        }

        $empresa = $anitaPendiente['empresa_codigo'] ?? null;
        $modo = $anitaPendiente['modo_facturacion_puntoventa'] ?? null;

        return [
            'tipo' => $tipo,
            'tipo_venta' => KandikoAnitaVentaTipoSupport::tipoVentaAnitaBridge($tipo, $sucursal, $empresa, $modo),
            'letra' => $letra,
            'sucursal' => $sucursal,
            'numero' => $numero,
            'path' => self::pathSistema($anitaPendiente),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $anitaPendiente
     * @param  array<string, mixed>|null  $vencaePendiente
     */
    private static function debeEsperarVencae(?array $anitaPendiente, ?array $vencaePendiente): bool
    {
        if (is_array($vencaePendiente) && trim((string) ($vencaePendiente['cae'] ?? '')) !== '') {
            return true;
        }

        $venta = is_array($anitaPendiente['venta'] ?? null) ? $anitaPendiente['venta'] : [];

        return trim((string) ($venta['cae'] ?? '')) !== '';
    }

    /**
     * @param  array{tipo: string, tipo_venta: string, letra: string, sucursal: string, numero: string, path: ?string}  $clave
     * @param  list<string>  $presentes
     * @return array{error: ?string, parciales: list<string>, detalle: array<string, mixed>}
     */
    private static function verificarDetalleContraPayload(?array $anitaPendiente, array $clave, array $presentes): array
    {
        $esperado = self::expectativaDetalleDesdePayload($anitaPendiente);
        $detalle = ['esperado' => $esperado, 'anita' => []];
        $parciales = [];

        $checks = [];
        if (in_array('compaux', $presentes, true) && $esperado['compaux_lineas'] > 0) {
            $checks['compaux'] = [
                'lineas' => $esperado['compaux_lineas'],
                'kilos' => $esperado['compaux_kilos'],
            ];
        }
        if (in_array('stkmov', $presentes, true) && $esperado['stkmov_lineas'] > 0) {
            $checks['stkmov'] = [
                'lineas' => $esperado['stkmov_lineas'],
                'kilos' => $esperado['stkmov_kilos'],
            ];
        }

        $skusEsperados = self::skusEsperadosDetalle($anitaPendiente);

        foreach ($checks as $tabla => $exp) {
            $lectura = self::leerDetalleTablaConfiable($tabla, $clave, $exp, $skusEsperados);
            if ($lectura['error'] !== null) {
                return [
                    'error' => $lectura['error'],
                    'parciales' => [],
                    'detalle' => $detalle,
                ];
            }

            $detalle['anita'][$tabla] = [
                'lineas' => $lectura['lineas'],
                'kilos' => $lectura['kilos'],
            ];

            $lineasOk = $lectura['lineas'] === $exp['lineas'];
            $kilosOk = abs($lectura['kilos'] - $exp['kilos']) <= self::TOLERANCIA_KILOS_DETALLE;
            if (! $lineasOk || ! $kilosOk) {
                $parciales[] = $tabla;
                $detalle['anita'][$tabla]['desfasaje'] = sprintf(
                    'esperaba %d renglones / %.2f kg; Anita tiene %d / %.2f',
                    $exp['lineas'],
                    $exp['kilos'],
                    $lectura['lineas'],
                    $lectura['kilos'],
                );
            }
        }

        return [
            'error' => null,
            'parciales' => $parciales,
            'detalle' => $detalle,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $anitaPendiente
     * @return list<string>
     */
    public static function skusEsperadosDetalle(?array $anitaPendiente): array
    {
        $skus = [];
        $lineas = is_array($anitaPendiente['data_factura'] ?? null)
            ? $anitaPendiente['data_factura']
            : [];
        foreach ($lineas as $linea) {
            if (! is_array($linea)) {
                continue;
            }
            $sku = ltrim(trim((string) ($linea['sku'] ?? '')), '0');
            if ($sku === '' || strcasecmp((string) ($linea['sku'] ?? ''), 'texto') === 0) {
                continue;
            }
            $skus[$sku] = $sku;
        }

        return array_values($skus);
    }

    /**
     * @param  array{tipo: string, tipo_venta: string, letra: string, sucursal: string, numero: string, path: ?string}  $clave
     * @param  array{lineas: int, kilos: float}  $esperado
     * @param  list<string>  $skusEsperados
     * @return array{lineas: int, kilos: float, error: ?string}
     */
    private static function leerDetalleTablaConfiable(
        string $tabla,
        array $clave,
        array $esperado,
        array $skusEsperados,
    ): array {
        $claves = self::clavesSucursalAlternativas($clave);
        $ultimoError = null;
        for ($intento = 1; $intento <= 3; $intento++) {
            foreach ($claves as $claveIntento) {
                $lectura = self::leerDetalleTabla($tabla, $claveIntento);
                if ($lectura['error'] !== null) {
                    $ultimoError = $lectura['error'];
                    continue;
                }
                if (self::lecturaDetalleCorrupta($lectura, $esperado, $skusEsperados)) {
                    $ultimoError = $tabla.': lectura Anita inconsistente (posible colisión del bridge)';
                    continue;
                }

                return $lectura;
            }
            usleep(250000 * $intento);
        }

        return [
            'lineas' => 0,
            'kilos' => 0.0,
            'error' => $ultimoError ?? ($tabla.': no se pudo verificar detalle'),
        ];
    }

    /**
     * @param  array{tipo: string, tipo_venta: string, letra: string, sucursal: string, numero: string, path: ?string}  $clave
     * @return list<array{tipo: string, tipo_venta: string, letra: string, sucursal: string, numero: string, path: ?string}>
     */
    private static function clavesSucursalAlternativas(array $clave): array
    {
        $sucursales = [];
        $raw = (string) ($clave['sucursal'] ?? '');
        if ($raw !== '') {
            $sucursales[$raw] = $raw;
            if (ctype_digit($raw)) {
                $sinPad = (string) (int) $raw;
                $conPad = str_pad($sinPad, 5, '0', STR_PAD_LEFT);
                $sucursales[$sinPad] = $sinPad;
                $sucursales[$conPad] = $conPad;
            }
        }

        $out = [];
        foreach ($sucursales as $sucursal) {
            $out[] = array_merge($clave, ['sucursal' => $sucursal]);
        }

        return $out !== [] ? $out : [$clave];
    }
    /**
     * @param  array{lineas: int, kilos: float, skus?: list<string>}  $lectura
     * @param  array{lineas: int, kilos: float}  $esperado
     * @param  list<string>  $skusEsperados
     */
    public static function lecturaDetalleCorrupta(array $lectura, array $esperado, array $skusEsperados): bool
    {
        $lineas = (int) ($lectura['lineas'] ?? 0);
        $kilos = (float) ($lectura['kilos'] ?? 0);
        $expLineas = (int) ($esperado['lineas'] ?? 0);
        $expKilos = (float) ($esperado['kilos'] ?? 0);

        if ($expLineas <= 0) {
            return false;
        }

        // Bridge devolvió otro CSV: muchas filas sin kilos, o volumen absurdo.
        if ($lineas > max(2, $expLineas * 2)) {
            return true;
        }
        if ($expKilos > 0.5 && $lineas > 0 && $kilos <= 0.0001) {
            return true;
        }

        $skusLeidos = is_array($lectura['skus'] ?? null) ? $lectura['skus'] : [];
        if ($skusEsperados !== [] && $skusLeidos !== []) {
            $match = 0;
            foreach ($skusEsperados as $sku) {
                if (in_array($sku, $skusLeidos, true)) {
                    $match++;
                }
            }
            if ($match === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{tipo: string, tipo_venta: string, letra: string, sucursal: string, numero: string, path: ?string}  $clave
     * @return array{lineas: int, kilos: float, skus: list<string>, error: ?string}
     */
    private static function leerDetalleTabla(string $tabla, array $clave): array
    {
        $where = self::whereTabla($tabla, $clave);
        if ($where === null) {
            return ['lineas' => 0, 'kilos' => 0.0, 'skus' => [], 'error' => 'Tabla Anita no contemplada: '.$tabla];
        }

        $campos = $tabla === 'compaux'
            ? 'compa_orden,compa_articulo,compa_cantidad'
            : 'stkv_nro_orden,stkv_articulo,stkv_cantidad';

        $parsed = self::listarAnita($tabla, $campos, $where, $clave['path']);
        if ($parsed['error_lectura'] !== null) {
            return ['lineas' => 0, 'kilos' => 0.0, 'skus' => [], 'error' => $tabla.': '.$parsed['error_lectura']];
        }

        $kilos = 0.0;
        $skus = [];
        foreach ($parsed['filas'] as $fila) {
            if (! is_object($fila)) {
                continue;
            }
            $cant = $tabla === 'compaux'
                ? (float) ($fila->compa_cantidad ?? 0)
                : (float) ($fila->stkv_cantidad ?? 0);
            $kilos += abs($cant);
            $art = $tabla === 'compaux'
                ? (string) ($fila->compa_articulo ?? '')
                : (string) ($fila->stkv_articulo ?? '');
            $sku = ltrim(trim($art), '0');
            if ($sku !== '') {
                $skus[$sku] = $sku;
            }
        }

        return [
            'lineas' => count($parsed['filas']),
            'kilos' => round($kilos, 4),
            'skus' => array_values($skus),
            'error' => null,
        ];
    }
    /**
     * @param  array{tipo: string, tipo_venta: string, letra: string, sucursal: string, numero: string, path: ?string}  $clave
     * @return array{existe: bool, error: ?string}
     */
    private static function existeTabla(string $tabla, array $clave): array
    {
        $where = self::whereTabla($tabla, $clave);
        if ($where === null) {
            return ['existe' => false, 'error' => 'Tabla Anita no contemplada: '.$tabla];
        }

        $campo = match ($tabla) {
            'venta' => 'ven_nro',
            'vengrav' => 'veng_tipo',
            'venibr' => 'veni_tipo',
            'climov' => 'cliv_nro',
            'comprob' => 'comp_nro_fact',
            'compaux' => 'compa_nro_fact',
            'stkmov' => 'stkv_nro',
            'vencae' => 'venc_nro',
            'ctamov' => 'ctav_nro',
            default => '*',
        };

        $parsed = self::listarAnita($tabla, $campo, $where, $clave['path']);
        if ($parsed['error_lectura'] !== null) {
            return ['existe' => false, 'error' => $tabla.': '.$parsed['error_lectura']];
        }

        return ['existe' => $parsed['filas'] !== [], 'error' => null];
    }

    /**
     * @param  array{tipo: string, tipo_venta: string, letra: string, sucursal: string, numero: string, path: ?string}  $clave
     */
    private static function borrarTablaDetalle(string $tabla, array $clave): void
    {
        $where = self::whereTabla($tabla, $clave);
        if ($where === null) {
            return;
        }

        $sucursales = array_values(array_unique(array_filter([
            (string) $clave['sucursal'],
            (string) (int) $clave['sucursal'],
        ], static fn (string $s): bool => $s !== '')));

        foreach ($sucursales as $sucursal) {
            $whereSuc = self::whereTabla($tabla, array_merge($clave, ['sucursal' => $sucursal]));
            if ($whereSuc === null) {
                continue;
            }
            $data = [
                'acc' => 'delete',
                'sistema' => 'ventas',
                'tabla' => $tabla,
                'whereArmado' => $whereSuc,
            ];
            if ($clave['path'] !== null) {
                $data['path_sistema'] = $clave['path'];
            }
            (new ApiAnita)->apiCall($data);
        }
    }

    /**
     * @param  array{tipo: string, tipo_venta: string, letra: string, sucursal: string, numero: string, path: ?string}  $clave
     */
    private static function whereTabla(string $tabla, array $clave): ?string
    {
        return match ($tabla) {
            'venta' => " WHERE ven_tipo = '".addslashes($clave['tipo_venta'])."' AND"
                ." ven_letra = '".addslashes($clave['letra'])."' AND"
                ." ven_sucursal = '".addslashes($clave['sucursal'])."' AND"
                ." ven_nro = '".addslashes($clave['numero'])."'",
            'vengrav' => self::whereTipoLetraSucursalNro('veng', $clave),
            'venibr' => self::whereTipoLetraSucursalNro('veni', $clave),
            'climov' => self::whereTipoLetraSucursalNro('cliv', $clave),
            'comprob' => " WHERE comp_tipo = '".addslashes($clave['tipo'])."' AND"
                ." comp_letra = '".addslashes($clave['letra'])."' AND"
                ." comp_sucursal = '".addslashes($clave['sucursal'])."' AND"
                ." comp_nro_fact = '".addslashes($clave['numero'])."'",
            'compaux' => " WHERE compa_tipo = '".addslashes($clave['tipo'])."' AND"
                ." compa_letra = '".addslashes($clave['letra'])."' AND"
                ." compa_sucursal = '".addslashes($clave['sucursal'])."' AND"
                ." compa_nro_fact = '".addslashes($clave['numero'])."'",
            'stkmov' => self::whereTipoLetraSucursalNro('stkv', $clave),
            'vencae' => self::whereTipoLetraSucursalNro('venc', $clave),
            'ctamov' => " WHERE ctav_tipo = '".addslashes($clave['tipo'])."' AND"
                ." ctav_letra = '".addslashes($clave['letra'])."' AND"
                .' ctav_sucursal = '.(int) $clave['sucursal']
                .' AND ctav_nro = '.(int) $clave['numero'],
            default => null,
        };
    }

    /**
     * @return array{filas: list<object>, error_lectura: ?string}
     */
    private static function listarAnita(string $tabla, string $campos, string $where, ?string $path): array
    {
        $data = [
            'acc' => 'list',
            'tabla' => $tabla,
            'campos' => $campos,
            'whereArmado' => $where,
        ];
        if ($tabla === 'ctamov') {
            $data['sistema'] = 'contab';
        }
        if ($path !== null) {
            $data['path_sistema'] = $path;
        }

        return ApiAnita::parsearRespuestaLista((new ApiAnita)->apiCall($data));
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: string, numero: string}  $clave
     */
    private static function whereTipoLetraSucursalNro(string $prefijo, array $clave): string
    {
        return " WHERE {$prefijo}_tipo = '".addslashes($clave['tipo'])."' AND"
            ." {$prefijo}_letra = '".addslashes($clave['letra'])."' AND"
            ." {$prefijo}_sucursal = '".addslashes($clave['sucursal'])."' AND"
            ." {$prefijo}_nro = '".addslashes($clave['numero'])."'";
    }
}
