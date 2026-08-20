<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;
use Illuminate\Support\Facades\DB;

/**
 * Alta de insumos I8xxx y SKU alternativo en artículos de limpieza (LIM*) del ERP.
 */
final class LimpiezaSkuAlternativoCargaSupport
{
    public const RANGO_DESDE = 8100;

    public const TIPO_MATERIA_PRIMA = 7;

    public const USO_INSUMO_GASTRONOMIA = 6;

    /**
     * @return array{
     *     pendientes: list<array<string, mixed>>,
     *     ya_vinculados: int,
     *     creados: int,
     *     actualizados: int
     * }
     */
    public static function ejecutar(bool $aplicar): array
    {
        RecepcionProveedorDepositoSupport::reiniciarCache();

        $lims = Articulo::query()
            ->where('sku', 'like', 'LIM%')
            ->where('estado', 'ACTIVO')
            ->orderBy('sku')
            ->get();

        $pendientes = [];
        $yaVinculados = 0;

        foreach ($lims as $lim) {
            $insumo = RecepcionProveedorDepositoSupport::resolverArticuloInsumo($lim, (int) ($lim->empresa_id ?? 1) ?: 1);
            if ($insumo !== null) {
                $yaVinculados++;
                continue;
            }
            $pendientes[] = $lim;
        }

        $plan = [];
        $numeros = self::numerosLibres(count($pendientes));
        foreach ($pendientes as $i => $lim) {
            $numero = $numeros[$i];
            $plan[] = [
                'articulo_id' => (int) $lim->id,
                'sku' => (string) $lim->sku,
                'descripcion' => (string) ($lim->descripcion ?? ''),
                'skualternativo_actual' => trim((string) ($lim->skualternativo ?? '')),
                'insumo_sku' => self::skuInsumo($numero),
                'insumo_descripcion' => self::descripcionInsumo((string) ($lim->descripcion ?? ''), (string) $lim->sku),
                'skualternativo_nuevo' => (string) $numero,
            ];
        }

        $creados = 0;
        $actualizados = 0;

        if ($aplicar && $plan !== []) {
            DB::transaction(function () use ($pendientes, $plan, &$creados, &$actualizados): void {
                $porId = [];
                foreach ($pendientes as $lim) {
                    $porId[(int) $lim->id] = $lim;
                }

                foreach ($plan as $fila) {
                    $lim = $porId[(int) $fila['articulo_id']];
                    $insumo = self::crearInsumo($lim, $fila['insumo_sku'], $fila['insumo_descripcion']);
                    $creados++;

                    $lim->skualternativo = $fila['skualternativo_nuevo'];
                    $lim->save();
                    $actualizados++;

                    unset($insumo);
                }
            });

            RecepcionProveedorDepositoSupport::reiniciarCache();
        }

        return [
            'pendientes' => $plan,
            'ya_vinculados' => $yaVinculados,
            'creados' => $creados,
            'actualizados' => $actualizados,
        ];
    }

    public static function skuInsumo(int $numero): string
    {
        return 'I'.$numero;
    }

    public static function descripcionInsumo(string $descripcionCompra, string $skuCompra): string
    {
        $base = trim($descripcionCompra);
        if ($base === '') {
            $base = $skuCompra;
        }
        if (! str_ends_with(mb_strtoupper($base), 'INSUMO')) {
            $base .= ' INSUMO';
        }

        return mb_substr($base, 0, 80);
    }

    /** @return list<int> */
    private static function numerosLibres(int $cantidad): array
    {
        if ($cantidad <= 0) {
            return [];
        }

        $usados = [];
        foreach (Articulo::query()->where('sku', 'like', 'I%')->pluck('sku') as $sku) {
            if (preg_match('/^I0*(\d+)$/', (string) $sku, $m)) {
                $usados[(int) $m[1]] = true;
            }
        }

        $libres = [];
        $n = self::RANGO_DESDE;
        while (count($libres) < $cantidad) {
            if (! isset($usados[$n]) && Articulo::query()->where('sku', self::skuInsumo($n))->doesntExist()) {
                $libres[] = $n;
            }
            $n++;
        }

        return $libres;
    }

    private static function crearInsumo(Articulo $compra, string $skuInsumo, string $descripcion): Articulo
    {
        $insumo = new Articulo;
        $insumo->sku = $skuInsumo;
        $insumo->descripcion = $descripcion;
        $insumo->skualternativo = '';
        $insumo->estado = 'ACTIVO';
        $insumo->tipoarticulo_id = self::TIPO_MATERIA_PRIMA;
        $insumo->usoarticulo_id = self::USO_INSUMO_GASTRONOMIA;
        $insumo->empresa_id = $compra->empresa_id;
        $insumo->unidadmedida_id = $compra->unidadmedida_id;
        $insumo->unidadmedidaalternativa_id = $compra->unidadmedidaalternativa_id;
        $insumo->unidadesxenvase = $compra->unidadesxenvase;
        $insumo->impuesto_id = $compra->impuesto_id;
        $insumo->cuentacontablecompra_id = $compra->cuentacontablecompra_id;
        $insumo->cuentacontableventa_id = $compra->cuentacontableventa_id;
        $insumo->cuentacontableimpinterno_id = $compra->cuentacontableimpinterno_id;
        $insumo->coeficienteconversion = 0;
        $insumo->nofactura = '1';
        $insumo->usuario_id = $compra->usuario_id;
        $insumo->save();

        return $insumo;
    }
}
