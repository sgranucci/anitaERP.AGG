<?php

declare(strict_types=1);

namespace App\Support\Contable\MayorPlanoCuenta;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El mayor 2026 lee ctamov de Anita, que no tiene emisor. El asiento ERP sí
 * guarda anita_emisor (aplicaciones CC, reversos, etc.): se copia a la fila
 * Anita por empresa + número de asiento para que Emisor/CUIT no queden en blanco.
 */
final class MayorPlanoCuentaAnitaErpMetadatosSupport
{
    /**
     * @param  list<object>  $ctamov
     * @return list<object>
     */
    public static function adjuntarEmisorDesdeAsientoErp(array $ctamov): array
    {
        if ($ctamov === [] || ! Schema::hasTable('asiento')) {
            return $ctamov;
        }

        $tieneEmisor = Schema::hasColumn('asiento', 'anita_emisor');
        $porEmpresa = [];

        foreach ($ctamov as $linea) {
            if ((int) ($linea->erp_asiento_id ?? 0) > 0) {
                continue;
            }
            $empresaId = (int) ($linea->ctav_empresa ?? 0);
            $nro = (int) ($linea->ctav_nro_asiento ?? 0);
            if ($empresaId > 0 && $nro > 0) {
                $porEmpresa[$empresaId][$nro] = $nro;
            }
        }

        if ($porEmpresa === []) {
            return $ctamov;
        }

        $mapa = self::mapaPorEmpresaNumero($porEmpresa, $tieneEmisor);

        return self::aplicar($ctamov, $mapa);
    }

    /**
     * @param  list<object>  $ctamov
     * @param  array<string, array{emisor: string, asiento_id: int}>  $mapa
     * @return list<object>
     */
    public static function aplicar(array $ctamov, array $mapa): array
    {
        if ($mapa === []) {
            return $ctamov;
        }

        foreach ($ctamov as $linea) {
            if ((int) ($linea->erp_asiento_id ?? 0) > 0) {
                continue;
            }

            $clave = (int) ($linea->ctav_empresa ?? 0).'|'.(int) ($linea->ctav_nro_asiento ?? 0);
            $meta = $mapa[$clave] ?? null;
            if ($meta === null) {
                continue;
            }

            if ((int) ($meta['asiento_id'] ?? 0) > 0) {
                $linea->erp_asiento_id = (int) $meta['asiento_id'];
            }

            $emisor = MayorPlanoCuentaEmisorSupport::normalizarCodigo((string) ($meta['emisor'] ?? ''));
            if ($emisor !== '' && trim((string) ($linea->erp_emisor_anita ?? '')) === '') {
                $linea->erp_emisor_anita = $emisor;
            }
        }

        return $ctamov;
    }

    /**
     * @param  array<int, array<int, int>>  $porEmpresa
     * @return array<string, array{emisor: string, asiento_id: int}>
     */
    private static function mapaPorEmpresaNumero(array $porEmpresa, bool $tieneEmisor): array
    {
        $columnas = ['id', 'numeroasiento'];
        if ($tieneEmisor) {
            $columnas[] = 'anita_emisor';
        }

        $mapa = [];
        foreach ($porEmpresa as $empresaId => $numeros) {
            $numeros = array_values(array_filter(array_map('intval', $numeros), fn (int $n) => $n > 0));
            if ($numeros === []) {
                continue;
            }

            foreach (DB::table('asiento')
                ->where('empresa_id', (int) $empresaId)
                ->whereIn('numeroasiento', $numeros)
                ->get($columnas) as $row
            ) {
                $mapa[(int) $empresaId.'|'.(int) $row->numeroasiento] = [
                    'emisor' => $tieneEmisor
                        ? MayorPlanoCuentaEmisorSupport::normalizarCodigo((string) ($row->anita_emisor ?? ''))
                        : '',
                    'asiento_id' => (int) $row->id,
                ];
            }
        }

        return $mapa;
    }
}
