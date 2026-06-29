<?php

declare(strict_types=1);

namespace App\Support\Ventas\GastronomiaAnitaImport;

use stdClass;

/**
 * Lectura en memoria desde JSON local (sin bridge por comprobante).
 */
final class GastronomiaAnitaImportCacheReader
{
    /** @var array<string, object> clave tipo|letra|sucursal|nro */
    private array $cabeceras = [];

    /** @var array<string, list<object>> */
    private array $stkmov = [];

    /** @var array<string, list<object>> */
    private array $vengrav = [];

    /** @var array<string, object> */
    private array $vencae = [];

    /** @var array<string, object> */
    private array $resvta = [];

    /**
     * @param  list<object>  $venta
     * @param  list<object>  $stkmov
     * @param  list<object>  $vengrav
     * @param  list<object>  $vencae
     * @param  list<object>  $resvta
     */
    public function __construct(
        array $venta,
        array $stkmov,
        array $vengrav,
        array $vencae,
        array $resvta,
    ) {
        foreach ($venta as $fila) {
            $clave = self::clavePk($fila, 'ven');
            if ($clave !== null) {
                $this->cabeceras[$clave] = $fila;
            }
        }

        foreach ($stkmov as $fila) {
            $clave = self::clavePk($fila, 'stkv');
            if ($clave !== null) {
                $this->stkmov[$clave][] = $fila;
            }
        }

        foreach ($vengrav as $fila) {
            $clave = self::clavePk($fila, 'veng');
            if ($clave !== null) {
                $this->vengrav[$clave][] = $fila;
            }
        }

        foreach ($vencae as $fila) {
            $clave = self::clavePk($fila, 'venc');
            if ($clave !== null) {
                $this->vencae[$clave] = $fila;
            }
        }

        foreach ($resvta as $fila) {
            $clave = self::clavePk($fila, 'resv');
            if ($clave !== null) {
                $this->resvta[$clave] = $fila;
            }
        }
    }

    /**
     * @param  list<string>  $tiposPreferidos
     */
    public function cabecera(int $sucursal, int $nro, array $tiposPreferidos = ['FAC']): ?stdClass
    {
        foreach ($tiposPreferidos as $tipo) {
            $clave = self::armarClave($tipo, 'B', $sucursal, $nro);
            if (isset($this->cabeceras[$clave])) {
                return $this->cabeceras[$clave];
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $tipos
     * @return list<object>
     */
    public function stkmov(int $sucursal, int $nro, array $tipos): array
    {
        return $this->lineasDetalle($this->stkmov, $sucursal, $nro, $tipos);
    }

    /**
     * @param  list<string>  $tipos
     * @return list<object>
     */
    public function vengrav(int $sucursal, int $nro, array $tipos): array
    {
        return $this->lineasDetalle($this->vengrav, $sucursal, $nro, $tipos);
    }

    /**
     * @param  list<string>  $tipos
     */
    public function vencae(int $sucursal, int $nro, array $tipos): ?stdClass
    {
        foreach ($tipos as $tipo) {
            $clave = self::armarClave($tipo, 'B', $sucursal, $nro);
            if (isset($this->vencae[$clave])) {
                return $this->vencae[$clave];
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $tipos
     */
    public function resvta(int $sucursal, int $nro, array $tipos): ?stdClass
    {
        foreach ($tipos as $tipo) {
            $clave = self::armarClave($tipo, 'B', $sucursal, $nro);
            if (isset($this->resvta[$clave])) {
                return $this->resvta[$clave];
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $tipos
     * @return list<int>
     */
    public function numerosEnRango(int $sucursal, int $desde, int $hasta, array $tipos = []): array
    {
        $tiposUpper = array_map(static fn (string $t): string => strtoupper(trim($t)), $tipos);
        $numeros = [];
        foreach ($this->cabeceras as $clave => $cab) {
            $partes = explode('|', $clave);
            if (count($partes) !== 4) {
                continue;
            }
            $tipoCab = $partes[0];
            $suc = (int) $partes[2];
            $nro = (int) $partes[3];
            if ($suc !== $sucursal || $nro < $desde || $nro > $hasta) {
                continue;
            }
            if ($tiposUpper !== [] && ! in_array($tipoCab, $tiposUpper, true)) {
                continue;
            }
            $numeros[$nro] = $nro;
        }
        ksort($numeros);

        return array_values($numeros);
    }

    public function totalCabeceras(): int
    {
        return count($this->cabeceras);
    }

    /**
     * @param  array<string, list<object>>  $mapa
     * @param  list<string>  $tipos
     * @return list<object>
     */
    private function lineasDetalle(array $mapa, int $sucursal, int $nro, array $tipos): array
    {
        foreach ($tipos as $tipo) {
            $clave = self::armarClave($tipo, 'B', $sucursal, $nro);
            if (isset($mapa[$clave]) && $mapa[$clave] !== []) {
                return $mapa[$clave];
            }
        }

        return [];
    }

    private static function armarClave(string $tipo, string $letra, int $sucursal, int $nro): string
    {
        return strtoupper(trim($tipo)).'|'.strtoupper(trim($letra)).'|'.$sucursal.'|'.$nro;
    }

    private static function clavePk(object $fila, string $prefijo): ?string
    {
        $tipo = strtoupper(trim((string) ($fila->{$prefijo.'_tipo'} ?? '')));
        $letra = strtoupper(trim((string) ($fila->{$prefijo.'_letra'} ?? 'B')));
        $sucursal = (int) preg_replace('/\D+/', '', (string) ($fila->{$prefijo.'_sucursal'} ?? ''));
        $nro = (int) ($fila->{$prefijo.'_nro'} ?? 0);
        if ($tipo === '' || $sucursal <= 0 || $nro <= 0) {
            return null;
        }

        return self::armarClave($tipo, $letra !== '' ? $letra : 'B', $sucursal, $nro);
    }
}
