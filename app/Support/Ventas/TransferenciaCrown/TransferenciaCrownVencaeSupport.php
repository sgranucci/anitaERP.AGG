<?php

declare(strict_types=1);

namespace App\Support\Ventas\TransferenciaCrown;

/**
 * CAE (vencae) de Anita para las transferencias Crown de gastronomía.
 */
final class TransferenciaCrownVencaeSupport
{
    public function __construct(
        private readonly TransferenciaCrownAnitaConsulta $anita = new TransferenciaCrownAnitaConsulta(),
    ) {
    }

    /**
     * @param  list<object>  $ventasSala
     * @return array<string, array{cae: string, fechavencimientocae: string}>
     */
    public function mapaDesdeVentasSala(array $ventasSala, string $ifxServer): array
    {
        if ($ventasSala === [] || trim($ifxServer) === '') {
            return [];
        }

        $porSucursal = [];
        foreach ($ventasSala as $venta) {
            $suc = (int) ($venta->ven_sucursal ?? 0);
            $nro = (int) ($venta->ven_nro ?? 0);
            if ($suc <= 0 || $nro <= 0) {
                continue;
            }
            $porSucursal[$suc][$nro] = true;
        }
        if ($porSucursal === []) {
            return [];
        }

        $mapa = [];
        foreach ($porSucursal as $sucursal => $nrosSet) {
            $nros = array_keys($nrosSet);
            sort($nros);
            foreach (array_chunk($nros, 200) as $chunk) {
                $filas = $this->anita->listar(
                    'ventas',
                    'vencae',
                    'venc_tipo,venc_letra,venc_sucursal,venc_nro,venc_nro_cae,venc_fecha_vto',
                    ' WHERE venc_sucursal = '.(int) $sucursal
                        .' AND venc_nro IN ('.implode(',', $chunk).')',
                    $ifxServer,
                );
                $this->acumularMapa($mapa, $filas);
            }
        }

        return $mapa;
    }

    /**
     * @param  array<string, array{cae: string, fechavencimientocae: string}>  $mapa
     * @param  list<object>  $filas
     */
    private function acumularMapa(array &$mapa, array $filas): void
    {
        foreach ($filas as $fila) {
            $cae = trim((string) ($fila->venc_nro_cae ?? ''));
            if ($cae === '') {
                continue;
            }
            $dato = [
                'cae' => $cae,
                'fechavencimientocae' => self::fechaIsoDesdeAnita((string) ($fila->venc_fecha_vto ?? '')),
            ];
            $clave = self::clave(
                (string) ($fila->venc_tipo ?? ''),
                (string) ($fila->venc_letra ?? ''),
                (int) ($fila->venc_sucursal ?? 0),
                (int) ($fila->venc_nro ?? 0),
            );
            if ($clave !== '') {
                $mapa[$clave] = $dato;
            }
            $claveCorta = self::claveCorta(
                (string) ($fila->venc_letra ?? ''),
                (int) ($fila->venc_sucursal ?? 0),
                (int) ($fila->venc_nro ?? 0),
            );
            if ($claveCorta !== '' && ! isset($mapa[$claveCorta])) {
                $mapa[$claveCorta] = $dato;
            }
        }
    }

    /**
     * @param  array<string, array{cae: string, fechavencimientocae: string}>  $mapa
     * @return array{cae: string, fechavencimientocae: string}|null
     */
    public static function buscar(
        array $mapa,
        string $tipoAnita,
        string $letra,
        int $sucursal,
        int $numero,
    ): ?array {
        $clave = self::clave($tipoAnita, $letra, $sucursal, $numero);
        if ($clave !== '' && isset($mapa[$clave])) {
            return $mapa[$clave];
        }
        $corta = self::claveCorta($letra, $sucursal, $numero);
        if ($corta !== '' && isset($mapa[$corta])) {
            return $mapa[$corta];
        }

        return null;
    }

    public static function claveCorta(string $letra, int $sucursal, int $numero): string
    {
        $letra = strtoupper(trim($letra));
        if ($letra === '' || $sucursal <= 0 || $numero <= 0) {
            return '';
        }

        return $letra.'|'.$sucursal.'|'.$numero;
    }

    public static function clave(string $tipo, string $letra, int $sucursal, int $numero): string
    {
        $tipo = strtoupper(trim($tipo));
        $letra = strtoupper(trim($letra));
        if ($tipo === '' || $letra === '' || $sucursal <= 0 || $numero <= 0) {
            return '';
        }

        return $tipo.'|'.$letra.'|'.$sucursal.'|'.$numero;
    }

    public static function fechaIsoDesdeAnita(string $ymd): string
    {
        $ymd = preg_replace('/\D+/', '', $ymd) ?? '';
        if (strlen($ymd) !== 8) {
            return '';
        }

        return substr($ymd, 0, 4).'-'.substr($ymd, 4, 2).'-'.substr($ymd, 6, 2);
    }

    public static function fechaAnitaDesdeIso(string $iso): string
    {
        $iso = trim($iso);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $iso)) {
            return str_replace('-', '', $iso);
        }
        $ymd = preg_replace('/\D+/', '', $iso) ?? '';

        return strlen($ymd) === 8 ? $ymd : '';
    }
}
