<?php

namespace App\Support\Ventas\Gastronomia;

use App\Models\Caja\Cuentacaja;
use App\Support\Ventas\GastronomiaCuentacajaEfectivo;
use App\Support\Ventas\Waitry\WaitryMedioPagoCuentacajaSupport;

/**
 * Columnas del cuadro de cierre por cuenta de caja (alineado al debe del asiento 2 Anita).
 */
final class CierreJornadaCuadroColumnasSupport
{
    public const MEDIO_DIF_CAJA = 'diferencia_caja';

    public const MEDIO_OTROS_AGREGADOS = 'otros_agregados';

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array{
     *   columnas: list<array{id:string,cuentacaja_id:int,codigo:string,nombre:string,etiqueta:string}>,
     *   filas: list<array<string, mixed>>
     * }
     */
    public static function enriquecerFilas(array $filas, int $empresaId): array
    {
        if ($empresaId <= 0) {
            return ['columnas' => [], 'filas' => $filas];
        }

        $filasOut = [];
        $montosPorCuenta = [];

        foreach ($filas as $fila) {
            $f = self::enriquecerFilaPorCuenta($fila, $empresaId);
            foreach ($f['por_cuenta'] ?? [] as $ccId => $monto) {
                if (abs((float) $monto) <= 0.0001) {
                    continue;
                }
                $montosPorCuenta[(int) $ccId] = true;
            }
            $filasOut[] = $f;
        }

        $columnas = self::columnasDesdeCuentas(array_keys($montosPorCuenta), $empresaId);

        return [
            'columnas' => $columnas,
            'filas' => $filasOut,
        ];
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array<string, mixed>
     */
    private static function enriquecerFilaPorCuenta(array $fila, int $empresaId): array
    {
        if (isset($fila['por_cuenta']) && is_array($fila['por_cuenta']) && $fila['por_cuenta'] !== []) {
            return $fila;
        }

        $porCuenta = [];
        if (isset($fila['por_cuenta']) && is_array($fila['por_cuenta'])) {
            foreach ($fila['por_cuenta'] as $ccId => $monto) {
                $id = (int) $ccId;
                if ($id > 0) {
                    $porCuenta[$id] = round((float) $monto, 2);
                }
            }
        }

        if ($porCuenta === []) {
            $porCuenta = self::porCuentaDesdeAgregados($fila, $empresaId);
        }

        $fila['por_cuenta'] = $porCuenta;

        return $fila;
    }

    /**
     * Fallback: reparte columnas agregadas qr/mp/efectivo/otros a cuentas configuradas.
     *
     * @param  array<string, mixed>  $fila
     * @return array<int, float>
     */
    private static function porCuentaDesdeAgregados(array $fila, int $empresaId): array
    {
        $mapaClaveCuenta = self::mapaClaveMedioACuentacaja($empresaId);
        $porCuenta = [];

        foreach (['qr', 'mp', 'efectivo', 'otros'] as $col) {
            $monto = round((float) ($fila[$col] ?? 0), 2);
            if (abs($monto) <= 0.0001) {
                continue;
            }
            $clave = match ($col) {
                'qr' => CierreJornadaProcesoMedioSupport::CLAVE_QR,
                'mp' => CierreJornadaProcesoMedioSupport::CLAVE_MP,
                'efectivo' => CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO,
                default => CierreJornadaProcesoMedioSupport::CLAVE_OTRO,
            };
            $ccId = (int) ($mapaClaveCuenta[$clave] ?? 0);
            if ($ccId > 0) {
                $porCuenta[$ccId] = round(($porCuenta[$ccId] ?? 0) + $monto, 2);
            }
        }

        return $porCuenta;
    }

    /**
     * @return array<string, int> clave_medio => cuentacaja_id
     */
    private static function mapaClaveMedioACuentacaja(int $empresaId): array
    {
        $out = [];
        $efectivoId = GastronomiaCuentacajaEfectivo::idParaEmpresa($empresaId);
        if ($efectivoId !== null && $efectivoId > 0) {
            $out[CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO] = $efectivoId;
        }

        foreach (WaitryMedioPagoCuentacajaSupport::mediosConfiguradosParaEmpresa($empresaId) as $tipo => $cuenta) {
            $ccId = (int) ($cuenta['id'] ?? 0);
            if ($ccId <= 0) {
                continue;
            }
            $clave = match ($tipo) {
                WaitryMedioPagoCuentacajaSupport::TIPO_TOTALCOIN => CierreJornadaProcesoMedioSupport::CLAVE_QR,
                WaitryMedioPagoCuentacajaSupport::TIPO_MERCADOPAGO => CierreJornadaProcesoMedioSupport::CLAVE_MP,
                default => null,
            };
            if ($clave !== null) {
                $out[$clave] = $ccId;
            }
        }

        return $out;
    }

    /**
     * @param  list<int>  $cuentacajaIds
     * @return list<array{id:string,cuentacaja_id:int,codigo:string,nombre:string,etiqueta:string}>
     */
    private static function columnasDesdeCuentas(array $cuentacajaIds, int $empresaId): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $cuentacajaIds), fn (int $id) => $id > 0)));
        if ($ids === []) {
            return [];
        }

        $cuentas = Cuentacaja::query()
            ->whereIn('id', $ids)
            ->paraEmpresa($empresaId)
            ->get(['id', 'codigo', 'nombre']);

        $porId = [];
        foreach ($cuentas as $cuenta) {
            $porId[(int) $cuenta->id] = $cuenta;
        }

        $efectivoId = GastronomiaCuentacajaEfectivo::idParaEmpresa($empresaId);
        $prioridad = [];
        foreach (WaitryMedioPagoCuentacajaSupport::mediosConfiguradosParaEmpresa($empresaId) as $cuenta) {
            $prioridad[] = (int) ($cuenta['id'] ?? 0);
        }
        if ($efectivoId !== null) {
            array_unshift($prioridad, $efectivoId);
        }

        usort($ids, function (int $a, int $b) use ($prioridad, $porId): int {
            $pa = array_search($a, $prioridad, true);
            $pb = array_search($b, $prioridad, true);
            if ($pa !== false && $pb !== false) {
                return $pa <=> $pb;
            }
            if ($pa !== false) {
                return -1;
            }
            if ($pb !== false) {
                return 1;
            }
            $na = mb_strtolower((string) ($porId[$a]->nombre ?? $porId[$a]->codigo ?? ''));
            $nb = mb_strtolower((string) ($porId[$b]->nombre ?? $porId[$b]->codigo ?? ''));

            return $na <=> $nb;
        });

        $out = [];
        foreach ($ids as $id) {
            $cuenta = $porId[$id] ?? null;
            $codigo = trim((string) ($cuenta->codigo ?? ''));
            $nombre = trim((string) ($cuenta->nombre ?? ''));
            $etiqueta = $codigo !== '' && $nombre !== ''
                ? $codigo.' — '.$nombre
                : ($codigo !== '' ? $codigo : ($nombre !== '' ? $nombre : '#'.$id));
            $out[] = [
                'id' => 'cc:'.$id,
                'cuentacaja_id' => $id,
                'codigo' => $codigo,
                'nombre' => $nombre,
                'etiqueta' => $etiqueta,
            ];
        }

        return $out;
    }

    public static function idColumnaDesdeCuentacaja(int $cuentacajaId): string
    {
        return 'cc:'.$cuentacajaId;
    }

    /**
     * Resuelve medio del cuadro (cc:ID o agregado legacy) a columna qr/mp/efectivo/otros para Waitry.
     * Si varios medios Waitry comparten la misma cuenta, devuelve el primero del mapa (compat. legacy).
     */
    public static function columnaAgregadaDesdeMedio(string $medio, int $empresaId): string
    {
        $columnas = self::columnasAgregadasDesdeMedio($medio, $empresaId);

        return $columnas[0] ?? 'otros';
    }

    /**
     * Columnas lógicas del cuadro para un medio cc:{id} (p. ej. QR + MP cuando comparten cuenta Anita).
     *
     * @return list<string>
     */
    public static function columnasAgregadasDesdeMedio(string $medio, int $empresaId): array
    {
        if (! preg_match('/^cc:(\d+)$/', $medio, $matches)) {
            return [$medio];
        }

        $ccId = (int) $matches[1];
        $columnas = [];
        foreach (self::mapaClaveMedioACuentacaja($empresaId) as $clave => $idCuenta) {
            if ($idCuenta !== $ccId) {
                continue;
            }
            $columnas[] = match ($clave) {
                CierreJornadaProcesoMedioSupport::CLAVE_QR => 'qr',
                CierreJornadaProcesoMedioSupport::CLAVE_MP => 'mp',
                CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO => 'efectivo',
                default => 'otros',
            };
        }

        if ($columnas === []) {
            return ['otros'];
        }

        $orden = ['qr' => 0, 'mp' => 1, 'efectivo' => 2, 'otros' => 3];
        usort($columnas, fn (string $a, string $b): int => ($orden[$a] ?? 99) <=> ($orden[$b] ?? 99));

        return array_values(array_unique($columnas));
    }

    public static function cuentacajaIdDesdeMedio(string $medio): ?int
    {
        if (! preg_match('/^cc:(\d+)$/', $medio, $matches)) {
            return null;
        }

        $id = (int) $matches[1];

        return $id > 0 ? $id : null;
    }
}
