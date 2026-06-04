<?php

namespace App\Support\Ventas\Gastronomia;

use App\Models\Ventas\Puntoventa;
use InvalidArgumentException;

/**
 * Punto de venta fijo del proceso de cierre Waitry (facturación batch futura: una factura por permiso).
 *
 * Resolución: BD gastronomia_cierre_jornada_config.puntoventa_id → config por empresa (código PV).
 */
final class CierreJornadaProcesoPuntoventaSupport
{
    /**
     * @return array{id:int,codigo:string,nombre:string,modofacturacion:?string,origen:string}|null
     */
    public static function resolverParaEmpresa(int $empresaId): ?array
    {
        if ($empresaId <= 0) {
            return null;
        }

        $cfg = CierreJornadaProcesoConfigSupport::paraEmpresa($empresaId);
        $pvId = (int) ($cfg['puntoventa_id'] ?? 0);
        if ($pvId > 0) {
            $pv = self::puntoventaValido($pvId, $empresaId);
            if ($pv !== null) {
                return [...$pv, 'origen' => 'bd'];
            }
        }

        $codigo = self::codigoConfigurado($empresaId);
        if ($codigo === null) {
            return null;
        }

        $pv = self::puntoventaPorCodigo($codigo, $empresaId);
        if ($pv === null) {
            return null;
        }

        return [...$pv, 'origen' => 'config'];
    }

    /**
     * @return array{id:int,codigo:string,nombre:string,modofacturacion:?string,origen:string}
     */
    public static function resolverOError(int $empresaId): array
    {
        $pv = self::resolverParaEmpresa($empresaId);
        if ($pv === null) {
            throw new InvalidArgumentException(self::mensajeFaltante($empresaId));
        }

        return $pv;
    }

    public static function mensajeFaltante(int $empresaId): string
    {
        $codigo = self::codigoConfigurado($empresaId);

        return 'No está configurado el punto de venta del proceso de cierre Waitry para la empresa '
            .$empresaId
            .($codigo !== null
                ? ' (código «'.$codigo.'» no existe o no es válido).'
                : '. Defina puntoventa_id en gastronomia_cierre_jornada_config o GASTRONOMIA_CIERRE_JORNADA_PUNTOVENTA_CODIGO_POR_EMPRESA.');
    }

    public static function codigoConfigurado(int $empresaId): ?string
    {
        $mapa = config('gastronomia.cierre_jornada_puntoventa_codigo_por_empresa', []);
        if (! is_array($mapa)) {
            return null;
        }

        $codigo = trim((string) ($mapa[$empresaId] ?? $mapa[(string) $empresaId] ?? ''));
        if ($codigo === '') {
            return null;
        }

        return self::normalizarCodigo($codigo);
    }

    /**
     * @return array{id:int,codigo:string,nombre:string,modofacturacion:?string}|null
     */
    private static function puntoventaPorCodigo(string $codigo, int $empresaId): ?array
    {
        $codigoNorm = self::normalizarCodigo($codigo);

        $candidatos = Puntoventa::query()
            ->where('empresa_id', $empresaId)
            ->where(function ($q) use ($codigo, $codigoNorm) {
                $q->where('codigo', $codigo)
                    ->orWhere('codigo', $codigoNorm);
                if (ctype_digit($codigoNorm)) {
                    $q->orWhere('codigo', str_pad($codigoNorm, 5, '0', STR_PAD_LEFT));
                }
            })
            ->get(['id', 'codigo', 'nombre', 'modofacturacion', 'empresa_id']);

        foreach ($candidatos as $pv) {
            if (self::normalizarCodigo((string) $pv->codigo) === $codigoNorm) {
                return self::formatearPuntoventa($pv);
            }
        }

        return null;
    }

    /**
     * @return array{id:int,codigo:string,nombre:string,modofacturacion:?string}|null
     */
    private static function puntoventaValido(int $puntoventaId, int $empresaId): ?array
    {
        $pv = Puntoventa::query()
            ->whereKey($puntoventaId)
            ->where('empresa_id', $empresaId)
            ->first(['id', 'codigo', 'nombre', 'modofacturacion', 'empresa_id']);

        if ($pv === null || ($pv->modofacturacion ?? '') === 'M') {
            return null;
        }

        return self::formatearPuntoventa($pv);
    }

    /**
     * @return array{id:int,codigo:string,nombre:string,modofacturacion:?string}
     */
    private static function formatearPuntoventa(Puntoventa $pv): array
    {
        return [
            'id' => (int) $pv->id,
            'codigo' => (string) $pv->codigo,
            'nombre' => (string) $pv->nombre,
            'modofacturacion' => $pv->modofacturacion !== null ? (string) $pv->modofacturacion : null,
        ];
    }

    private static function normalizarCodigo(string $codigo): string
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return '';
        }

        if (ctype_digit($codigo)) {
            return str_pad(ltrim($codigo, '0') !== '' ? ltrim($codigo, '0') : '0', 5, '0', STR_PAD_LEFT);
        }

        return $codigo;
    }
}
