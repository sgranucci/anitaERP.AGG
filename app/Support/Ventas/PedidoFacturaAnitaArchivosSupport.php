<?php

namespace App\Support\Ventas;

use App\ApiAnita;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Venta;
use Illuminate\Support\Collection;

/**
 * Estado de archivos Informix de una factura de pedido (El Bierzo).
 * Distingue Anita vacía vs. cabecera/detalle/CAE incompletos.
 */
final class PedidoFacturaAnitaArchivosSupport
{
    public const PATH_VILLAFRANCA = '/usr2/villafranca';

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
            if ($nombre !== '' && stripos($nombre, 'Iva') !== false) {
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
     *   esperados: list<string>
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
                ];
            }
            if ($consulta['existe']) {
                $presentes[] = $tabla;
            }
        }

        $faltantes = array_values(array_diff($esperados, $presentes));

        return [
            'ok' => true,
            'error' => null,
            'vacio' => $presentes === [],
            'completo' => $faltantes === [],
            'presentes' => $presentes,
            'faltantes' => $faltantes,
            'esperados' => $esperados,
        ];
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

    public static function pathSistema(?array $anitaPendiente): ?string
    {
        $path = trim((string) ($anitaPendiente['path_sistema'] ?? ''));
        if ($path === self::PATH_VILLAFRANCA) {
            return self::PATH_VILLAFRANCA;
        }

        $sucursal = self::normalizarSucursal((string) ($anitaPendiente['puntoventa_codigo'] ?? ''));
        if ($sucursal === '') {
            return null;
        }

        $idsDivision = self::idsPuntoVentaDivision();
        if ($idsDivision === []) {
            return null;
        }

        $codigos = Puntoventa::query()
            ->whereIn('id', $idsDivision)
            ->pluck('codigo');
        foreach ($codigos as $codigo) {
            if (self::normalizarSucursal((string) $codigo) === $sucursal) {
                return self::PATH_VILLAFRANCA;
            }
        }

        return null;
    }

    private static function normalizarSucursal(string $codigo): string
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
     * @return array{existe: bool, error: ?string}
     */
    private static function existeTabla(string $tabla, array $clave): array
    {
        $where = match ($tabla) {
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

        $data = [
            'acc' => 'list',
            'tabla' => $tabla,
            'campos' => $campo,
            'whereArmado' => $where,
        ];
        if ($tabla === 'ctamov') {
            $data['sistema'] = 'contab';
        }
        if ($clave['path'] !== null) {
            $data['path_sistema'] = $clave['path'];
        }

        $parsed = ApiAnita::parsearRespuestaLista((new ApiAnita)->apiCall($data));
        if ($parsed['error_lectura'] !== null) {
            return ['existe' => false, 'error' => $tabla.': '.$parsed['error_lectura']];
        }

        return ['existe' => $parsed['filas'] !== [], 'error' => null];
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
