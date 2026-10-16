<?php

declare(strict_types=1);

namespace App\Services\Compras;

use App\Models\Compras\Proveedor;
use App\Models\Configuracion\Condicioniva;
use App\Services\Arca\ConstanciaInscripcionService;
use App\Support\Compras\ProveedorControlGananciasReporteFiltros;
use App\Support\Compras\ProveedorControlGananciasValidacionSupport;
use App\Support\Configuracion\CondicionivaLetraComprasSupport;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ProveedorControlGananciasReporteService
{
    public function __construct(
        private readonly ConstanciaInscripcionService $constanciaInscripcionService,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     * @param  callable(int, int): void|null  $progreso  procesados, total
     * @return array{filas: list<array<string, mixed>>, stats: array<string, int>, advertencias: list<string>}
     */
    public function generar(array $filtros, ?callable $progreso = null): array
    {
        $this->extenderTiempo();

        $advertencias = [];
        $proveedores = $this->queryProveedores($filtros)->get();

        $cacheSeg = max(0, (int) config('compras_control_ganancias.cache_arca_segundos', 86400));
        $delayMs = max(0, (int) config('compras_control_ganancias.delay_ms_entre_consultas', 150));
        $consultarArca = ! empty($filtros['consultar_arca']);

        if ($consultarArca && $proveedores->count() > 50) {
            $advertencias[] = 'La consulta ARCA de '.$proveedores->count()
                .' proveedores puede demorar varios minutos (caché '.$cacheSeg.'s).';
        }

        $padronPorCuit = [];
        $erroresPorCuit = [];
        $consultasArca = 0;

        if ($consultarArca) {
            $totalProveedores = $proveedores->count();
            $procesados = 0;
            foreach ($proveedores as $proveedor) {
                $procesados++;
                if ($progreso !== null && ($procesados === 1 || $procesados % 10 === 0 || $procesados === $totalProveedores)) {
                    $progreso($procesados, $totalProveedores);
                }
                $cuit = preg_replace('/\D+/', '', (string) ($proveedor->nroinscripcion ?? '')) ?? '';
                if (strlen($cuit) !== 11) {
                    $erroresPorCuit[$cuit !== '' ? $cuit : 'p'.$proveedor->id] = 'CUIT inválida (11 dígitos).';

                    continue;
                }
                if (isset($padronPorCuit[$cuit]) || isset($erroresPorCuit[$cuit])) {
                    continue;
                }

                $cacheKey = 'compras_control_ganancias_arca_'.$cuit;
                try {
                    if ($consultasArca > 0 && $consultasArca % 25 === 0) {
                        $this->extenderTiempo();
                    }
                    if ($cacheSeg > 0 && Cache::has($cacheKey)) {
                        $padronPorCuit[$cuit] = Cache::get($cacheKey);
                    } else {
                        if ($consultasArca > 0 && $delayMs > 0) {
                            usleep($delayMs * 1000);
                        }
                        $data = $this->constanciaInscripcionService->getPersonaV2($cuit);
                        unset($data['raw'], $data['soap']);
                        $padronPorCuit[$cuit] = $data;
                        if ($cacheSeg > 0) {
                            Cache::put($cacheKey, $data, $cacheSeg);
                        }
                        $consultasArca++;
                    }
                } catch (Throwable $e) {
                    $erroresPorCuit[$cuit] = $e->getMessage();
                }
            }
        }

        $filas = [];
        foreach ($proveedores as $proveedor) {
            $cuit = preg_replace('/\D+/', '', (string) ($proveedor->nroinscripcion ?? '')) ?? '';
            $claveError = strlen($cuit) === 11 ? $cuit : 'p'.$proveedor->id;
            $padron = strlen($cuit) === 11 ? ($padronPorCuit[$cuit] ?? null) : null;
            $error = $erroresPorCuit[$claveError] ?? $erroresPorCuit[$cuit] ?? null;

            $fila = ProveedorControlGananciasValidacionSupport::armarFila(
                $proveedor,
                is_array($padron) ? $padron : null,
                $error
            );

            if (! empty($filtros['solo_discrepancias']) && empty($fila['discrepancia'])) {
                continue;
            }

            $filas[] = $fila;
        }

        usort($filas, static function (array $a, array $b): int {
            $cmp = ((int) (! empty($b['discrepancia']))) <=> ((int) (! empty($a['discrepancia'])));
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp((string) ($a['razon_social'] ?? ''), (string) ($b['razon_social'] ?? ''));
        });

        $stats = [
            'proveedores_evaluados' => $proveedores->count(),
            'filas' => count($filas),
            'discrepancias' => count(array_filter($filas, static fn (array $f) => ! empty($f['discrepancia']))),
            'consultas_arca' => $consultasArca,
            'ri' => count(array_filter($filas, static fn (array $f) => ($f['segmento'] ?? '') === 'ri')),
            'mono' => count(array_filter($filas, static fn (array $f) => ($f['segmento'] ?? '') === 'mono')),
        ];

        return [
            'filas' => $filas,
            'stats' => $stats,
            'advertencias' => $advertencias,
        ];
    }

    private function extenderTiempo(): void
    {
        @ini_set('memory_limit', '-1');
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);
    }

    public function paginarFilas(array $filas, int $perPage, int $page): LengthAwarePaginator
    {
        $page = max(1, $page);
        $perPage = max(10, min(500, $perPage));
        $total = count($filas);
        $offset = ($page - 1) * $perPage;

        return new LengthAwarePaginator(
            array_slice($filas, $offset, $perPage),
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function queryProveedores(array $filtros)
    {
        $limite = (int) ($filtros['limite'] ?? 100);
        $riId = (int) config('arca.padron_validacion_proveedor.condicioniva_responsable_inscripto_id', 1);
        $monoId = CondicionivaLetraComprasSupport::condicionivaMonotributoId();

        $monoIds = Condicioniva::query()
            ->where(function ($q) use ($monoId) {
                $q->where('id', $monoId)
                    ->orWhere('nombre', 'like', '%monotribut%');
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $query = Proveedor::query()
            ->with([
                'condicionivas:id,nombre,letra,letra_compras',
                'retencionganancias:id,codigo,nombre',
            ])
            ->orderBy('nombre');

        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        if ($empresaId > 0) {
            $query->where(function ($q) use ($empresaId) {
                $q->where('empresa_id', $empresaId)->orWhereNull('empresa_id');
            });
        }

        $estado = trim((string) ($filtros['estado'] ?? ''));
        if ($estado !== '') {
            $query->where('estado', $estado);
        }

        $proveedorId = (int) ($filtros['proveedor_id'] ?? 0);
        if ($proveedorId > 0) {
            $query->whereKey($proveedorId);
        }

        $segmento = (string) ($filtros['segmento'] ?? ProveedorControlGananciasReporteFiltros::SEGMENTO_TODOS);
        if ($segmento === ProveedorControlGananciasReporteFiltros::SEGMENTO_RI) {
            $query->where('condicioniva_id', $riId);
        } elseif ($segmento === ProveedorControlGananciasReporteFiltros::SEGMENTO_MONO) {
            if ($monoIds !== []) {
                $query->whereIn('condicioniva_id', $monoIds);
            } else {
                $query->whereRaw('1=0');
            }
        } else {
            $ids = array_values(array_unique(array_merge([$riId], $monoIds)));
            $query->whereIn('condicioniva_id', $ids);
        }

        if ($limite > 0) {
            $query->limit($limite);
        }

        return $query;
    }
}
