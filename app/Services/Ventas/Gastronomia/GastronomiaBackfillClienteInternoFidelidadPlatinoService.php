<?php

declare(strict_types=1);

namespace App\Services\Ventas\Gastronomia;

use App\Support\Ventas\GastronomiaDescuentoClienteInternoSupport;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Corrige cliente_interno_descuento_id en canjes fidelidad Platino (solo ERP).
 *
 * Fuente: categoriafidelidad_entrega_gastronomia + categoría Wigos platino (levelCode 3).
 * 500 (CNJE FIDELIZ DIARIO) → 1500 (CANJE PLATINO).
 */
final class GastronomiaBackfillClienteInternoFidelidadPlatinoService
{
    /**
     * @param  list<int>  $empresaIds
     * @return array{
     *   fidelidad_platino_corregidas:int,
     *   omitidas:int,
     *   errores:list<string>,
     *   por_empresa:array<int, array{fidelidad_platino_corregidas:int, omitidas:int}>
     * }
     */
    public function ejecutar(
        string $fechaDesde,
        string $fechaHasta,
        array $empresaIds,
        bool $dryRun = false,
    ): array {
        $cliDefaultId = GastronomiaDescuentoClienteInternoSupport::clienteInternoIdCanjePremioDefault();
        $cliPlatinoId = GastronomiaDescuentoClienteInternoSupport::clienteInternoIdCanjePremioPlatino();

        if ($cliDefaultId === null || $cliDefaultId <= 0) {
            throw new InvalidArgumentException(
                'No existe cliente interno default ('
                .GastronomiaDescuentoClienteInternoSupport::codigoClienteInternoCanjePremioDefault().').'
            );
        }

        if ($cliPlatinoId === null || $cliPlatinoId <= 0) {
            throw new InvalidArgumentException(
                'No existe cliente interno platino ('
                .GastronomiaDescuentoClienteInternoSupport::codigoClienteInternoCanjePremioPlatino().').'
            );
        }

        $codigosCategoriaPlatino = $this->codigosCategoriaPlatinoWigos();
        if ($codigosCategoriaPlatino === []) {
            throw new InvalidArgumentException('GASTRONOMIA_CANJE_PREMIO_PLATINO_LEVEL_CODES vacío.');
        }

        $ret = [
            'fidelidad_platino_corregidas' => 0,
            'omitidas' => 0,
            'errores' => [],
            'por_empresa' => [],
        ];

        foreach ($empresaIds as $empresaId) {
            $empresaId = (int) $empresaId;
            if ($empresaId <= 0) {
                continue;
            }

            $ret['por_empresa'][$empresaId] = [
                'fidelidad_platino_corregidas' => 0,
                'omitidas' => 0,
            ];

            $filas = DB::table('categoriafidelidad_entrega_gastronomia as e')
                ->join('categoriafidelidad_gastronomia as cat', 'cat.id', '=', 'e.categoriafidelidad_id')
                ->join('venta_gastronomia_emision as vge', 'vge.venta_id', '=', 'e.venta_id')
                ->join('venta as v', 'v.id', '=', 'e.venta_id')
                ->join('cuenta_gastronomia as cg', 'cg.id', '=', 'vge.cuenta_gastronomia_id')
                ->whereNull('v.deleted_at')
                ->whereNull('vge.venta_factura_origen_id')
                ->where('cg.empresa_id', $empresaId)
                ->whereIn('cat.codigo', $codigosCategoriaPlatino)
                ->where('cg.cliente_interno_descuento_id', $cliDefaultId)
                ->whereBetween('v.fechajornada', [$fechaDesde, $fechaHasta])
                ->select([
                    'cg.id as cuenta_id',
                    'e.id as entrega_id',
                    'e.venta_id',
                    'v.fechajornada',
                    'cat.codigo as categoria_codigo',
                ])
                ->orderBy('e.id')
                ->get();

            foreach ($filas as $row) {
                try {
                    $cuentaId = (int) $row->cuenta_id;
                    if ($cuentaId <= 0) {
                        $ret['omitidas']++;
                        $ret['por_empresa'][$empresaId]['omitidas']++;

                        continue;
                    }

                    if ($dryRun) {
                        $ret['fidelidad_platino_corregidas']++;
                        $ret['por_empresa'][$empresaId]['fidelidad_platino_corregidas']++;

                        continue;
                    }

                    $actualizadas = DB::table('cuenta_gastronomia')
                        ->where('id', $cuentaId)
                        ->where('cliente_interno_descuento_id', $cliDefaultId)
                        ->update(['cliente_interno_descuento_id' => $cliPlatinoId]);

                    if ($actualizadas <= 0) {
                        $ret['omitidas']++;
                        $ret['por_empresa'][$empresaId]['omitidas']++;

                        continue;
                    }

                    $ret['fidelidad_platino_corregidas']++;
                    $ret['por_empresa'][$empresaId]['fidelidad_platino_corregidas']++;
                } catch (\Throwable $e) {
                    $ret['errores'][] = 'fidelidad_platino entrega_id='.(int) $row->entrega_id
                        .' cuenta_id='.(int) $row->cuenta_id.': '.$e->getMessage();
                }
            }
        }

        return $ret;
    }

    /**
     * @return list<string>
     */
    private function codigosCategoriaPlatinoWigos(): array
    {
        $raw = trim((string) config('gastronomia.canje_premio_platino_level_codes', '3'));
        if ($raw === '') {
            return [];
        }

        $codigos = [];
        foreach (preg_split('/[\s,;]+/', $raw) ?: [] as $part) {
            $part = trim((string) $part);
            if ($part === '') {
                continue;
            }
            $codigos[] = $part;
            if (ctype_digit($part)) {
                $sinCeros = ltrim($part, '0') ?: '0';
                if ($sinCeros !== $part) {
                    $codigos[] = $sinCeros;
                }
            }
        }

        return array_values(array_unique($codigos));
    }
}
