<?php

namespace App\Support\Contable\MayorPlanoCuenta;

use App\Services\Ai\Skills\AiSkillContext;
use App\Services\Ai\Skills\AiSkillRegistry;
use App\Services\Contable\Ai\ResumirCompraOrdencompraSkill;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Texto de “qué se compró” a partir de los ítems de la OC.
 * Primero resume en reglas; si la skill IA está habilitada, pide una frase más natural.
 */
class MayorPlanoCuentaOcCompraResumenSupport
{
    public const CACHE_PREFIJO = 'mayor_plano_oc_resumen_v1_';

    public const CACHE_SEGUNDOS = 2592000; // 30 días

    /**
     * @param  list<array{sku?:string,descripcion?:string,detalle?:string,cantidad?:float|int,partida?:string}>  $items
     */
    public static function resumenDeterministico(array $items): string
    {
        $agrupados = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $nombre = self::nombreItem($item);
            if ($nombre === '') {
                continue;
            }
            $clave = mb_strtolower($nombre);
            if (! isset($agrupados[$clave])) {
                $agrupados[$clave] = ['nombre' => $nombre, 'cantidad' => 0.0];
            }
            $agrupados[$clave]['cantidad'] += (float) ($item['cantidad'] ?? 0);
        }

        if ($agrupados === []) {
            return '';
        }

        $partes = [];
        $i = 0;
        $total = count($agrupados);
        foreach ($agrupados as $grupo) {
            $i++;
            if ($i > 8) {
                $partes[] = '+'.($total - 8).' más';
                break;
            }
            $cant = (float) $grupo['cantidad'];
            $nombre = (string) $grupo['nombre'];
            if ($cant > 0.0005 && abs($cant - 1.0) > 0.0005) {
                $partes[] = self::formatearCantidad($cant).' × '.$nombre;
            } else {
                $partes[] = $nombre;
            }
        }

        return 'Compra de '.implode(', ', $partes);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public static function nombreItem(array $item): string
    {
        $desc = trim((string) ($item['descripcion'] ?? ''));
        $detalle = trim((string) ($item['detalle'] ?? ''));
        $sku = trim((string) ($item['sku'] ?? ''));
        $partida = trim((string) ($item['partida'] ?? ''));

        if ($detalle !== '' && $desc !== '' && mb_strtolower($detalle) !== mb_strtolower($desc)) {
            return $detalle;
        }
        if ($desc !== '') {
            return $desc;
        }
        if ($detalle !== '') {
            return $detalle;
        }
        if ($sku !== '') {
            return 'SKU '.$sku;
        }

        return $partida;
    }

    public static function formatearCantidad(float $cantidad): string
    {
        if (abs($cantidad - round($cantidad)) < 0.0005) {
            return (string) (int) round($cantidad);
        }

        return rtrim(rtrim(number_format($cantidad, 2, ',', ''), '0'), ',');
    }

    /**
     * @param  array<int, list<array<string, mixed>>>  $itemsPorOc
     * @return array<int, string>
     */
    public function resumirVarias(array $itemsPorOc, bool $usarIa = false): array
    {
        $textos = [];
        $pendientesIa = [];

        foreach ($itemsPorOc as $ocId => $items) {
            $ocId = (int) $ocId;
            $fallback = self::resumenDeterministico($items);
            $clave = self::claveCache($ocId, $items);
            $cacheado = Cache::get($clave);
            if (is_string($cacheado) && trim($cacheado) !== '') {
                $textos[$ocId] = trim($cacheado);

                continue;
            }

            $textos[$ocId] = $fallback;
            if ($usarIa && $fallback !== '') {
                $pendientesIa[] = [
                    'id' => $ocId,
                    'items' => $items,
                    'fallback' => $fallback,
                    'cache' => $clave,
                ];
            }
        }

        if ($pendientesIa === []) {
            return $textos;
        }

        $mejoras = $this->pedirResumenesIa($pendientesIa);
        foreach ($pendientesIa as $pendiente) {
            $ocId = (int) $pendiente['id'];
            $ia = trim((string) ($mejoras[$ocId] ?? ''));
            if ($ia === '') {
                continue;
            }
            $textos[$ocId] = $ia;
            Cache::put((string) $pendiente['cache'], $ia, self::CACHE_SEGUNDOS);
        }

        return $textos;
    }

    /**
     * @param  list<array{id:int,items:list<array<string,mixed>>,fallback:string,cache:string}>  $pendientes
     * @return array<int, string>
     */
    private function pedirResumenesIa(array $pendientes): array
    {
        $loteMax = max(1, (int) config('ai.skills.resumir_compra_ordencompra.lote', 12));
        $out = [];

        try {
            /** @var AiSkillRegistry $registry */
            $registry = app(AiSkillRegistry::class);
        } catch (Throwable $e) {
            Log::debug('mayor_plano_oc_resumen.sin_registry', ['error' => $e->getMessage()]);

            return [];
        }

        foreach (array_chunk($pendientes, $loteMax) as $lote) {
            try {
                $result = $registry->ejecutar(
                    ResumirCompraOrdencompraSkill::NOMBRE,
                    new AiSkillContext(
                        entradas: ['ocs' => $lote],
                        entidadTipo: 'ordencompra',
                    ),
                );
            } catch (Throwable $e) {
                Log::warning('mayor_plano_oc_resumen.skill', ['error' => $e->getMessage()]);

                continue;
            }

            if (! $result->ok) {
                continue;
            }

            $map = $result->datos['textos'] ?? [];
            if (! is_array($map)) {
                continue;
            }
            foreach ($map as $ocId => $texto) {
                $ocId = (int) $ocId;
                $texto = trim((string) $texto);
                if ($ocId > 0 && $texto !== '') {
                    $out[$ocId] = $texto;
                }
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public static function claveCache(int $ocId, array $items): string
    {
        return self::CACHE_PREFIJO.$ocId.'_'.substr(sha1(json_encode(self::firmaItems($items)) ?: ''), 0, 16);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array{sku:string,d:string,t:string,c:string}>
     */
    public static function firmaItems(array $items): array
    {
        $firma = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $firma[] = [
                'sku' => trim((string) ($item['sku'] ?? '')),
                'd' => trim((string) ($item['descripcion'] ?? '')),
                't' => trim((string) ($item['detalle'] ?? '')),
                'c' => (string) ($item['cantidad'] ?? ''),
            ];
        }

        return $firma;
    }
}
