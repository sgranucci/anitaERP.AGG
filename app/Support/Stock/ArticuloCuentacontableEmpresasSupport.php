<?php

namespace App\Support\Stock;

use App\Models\Contable\Cuentacontable;
use App\Models\Stock\Articulo;
use App\Models\Stock\Articulo_Cuentacontable;
use App\Support\Contable\CuentacontableEmpresaResolverSupport;
use Illuminate\Support\Facades\DB;

/**
 * Completa articulo_cuentacontable en Biyemas / Kandiko / Rebisco.
 * Replica el tipo de imputación que ya existe (origen preferido Biyemas)
 * resolviendo la cuenta homologada por código en el plan de cada empresa.
 */
final class ArticuloCuentacontableEmpresasSupport
{
    public const EMPRESAS_DEFAULT = [1, 2, 3];

    /**
     * @return list<int>
     */
    public static function empresasObjetivo(): array
    {
        $ids = array_values(array_filter(
            array_map('intval', (array) config('stock.depmae_anita_empresas_sync', self::EMPRESAS_DEFAULT)),
            fn (int $id) => $id > 0
        ));

        return $ids !== [] ? $ids : self::EMPRESAS_DEFAULT;
    }

    /**
     * @param  list<array{
     *     id:int,
     *     articulo_id:int,
     *     empresa_id:int,
     *     tipoimputacion:string,
     *     cuentacontable_id:int,
     *     cuenta_empresa_id:int,
     *     cuenta_codigo:string,
     *     creousuario_id?:int
     * }>  $filas
     * @param  array<int, array<string, int>>  $cuentasPorEmpresaCodigo
     * @param  list<int>  $empresaIds
     * @return array{
     *     altas: list<array<string, mixed>>,
     *     homologaciones: list<array<string, mixed>>,
     *     errores: list<array<string, mixed>>,
     *     articulos_con_cuenta: int,
     *     articulos_completos: int,
     *     articulos_incompletos: int,
     *     articulos_solo_origen: int,
     *     pares_existentes: int
     * }
     */
    public static function planificar(
        array $filas,
        array $cuentasPorEmpresaCodigo,
        array $empresaIds,
        int $origenPreferido
    ): array {
        $empresaIds = array_values(array_unique(array_filter(
            array_map('intval', $empresaIds),
            fn (int $id) => $id > 0
        )));
        sort($empresaIds);

        $porArticulo = [];
        foreach ($filas as $fila) {
            $articuloId = (int) ($fila['articulo_id'] ?? 0);
            $empresaId = (int) ($fila['empresa_id'] ?? 0);
            $tipo = self::normalizarTipo((string) ($fila['tipoimputacion'] ?? ''));
            if ($articuloId <= 0 || $empresaId <= 0 || $tipo === '' || ! in_array($empresaId, $empresaIds, true)) {
                continue;
            }
            $porArticulo[$articuloId][$tipo][$empresaId][] = $fila;
        }

        $altas = [];
        $homologaciones = [];
        $errores = [];
        $articulosIncompletos = [];
        $articulosSoloOrigen = [];
        $paresExistentes = 0;

        foreach ($porArticulo as $articuloId => $tipos) {
            $empresasDelArticulo = [];
            foreach ($tipos as $filasPorEmpresa) {
                foreach (array_keys($filasPorEmpresa) as $empresaId) {
                    $empresasDelArticulo[(int) $empresaId] = true;
                }
            }
            if (count($empresasDelArticulo) === 1 && isset($empresasDelArticulo[$origenPreferido])) {
                $articulosSoloOrigen[$articuloId] = true;
            }

            foreach ($tipos as $tipo => $filasPorEmpresa) {
                $fuente = self::elegirFuente($filasPorEmpresa, $origenPreferido, $empresaIds);
                if ($fuente === null) {
                    continue;
                }
                $codigoFuente = self::codigoLookup((string) ($fuente['cuenta_codigo'] ?? ''));
                $creousuarioId = (int) ($fuente['creousuario_id'] ?? 0);

                foreach ($empresaIds as $destinoId) {
                    $existentes = $filasPorEmpresa[$destinoId] ?? [];
                    if ($existentes === []) {
                        $cuentaDestinoId = self::cuentaIdEnMapa($cuentasPorEmpresaCodigo, $destinoId, $codigoFuente);
                        if ($cuentaDestinoId === null) {
                            $errores[] = [
                                'accion' => 'alta',
                                'articulo_id' => $articuloId,
                                'empresa_id' => $destinoId,
                                'tipoimputacion' => $tipo,
                                'codigo' => (string) ($fuente['cuenta_codigo'] ?? ''),
                                'detalle' => 'Sin cuenta homologada en el plan de la empresa',
                            ];
                            $articulosIncompletos[$articuloId] = true;
                            continue;
                        }
                        $altas[] = [
                            'articulo_id' => $articuloId,
                            'empresa_id' => $destinoId,
                            'tipoimputacion' => $tipo,
                            'cuentacontable_id' => $cuentaDestinoId,
                            'codigo' => (string) ($fuente['cuenta_codigo'] ?? ''),
                            'origen_empresa_id' => (int) ($fuente['empresa_id'] ?? 0),
                            'creousuario_id' => $creousuarioId,
                        ];
                        $articulosIncompletos[$articuloId] = true;
                        continue;
                    }

                    $paresExistentes++;
                    foreach ($existentes as $existente) {
                        $cuentaActualId = (int) ($existente['cuentacontable_id'] ?? 0);
                        $cuentaEmpresaId = (int) ($existente['cuenta_empresa_id'] ?? 0);
                        if ($cuentaActualId > 0 && $cuentaEmpresaId === $destinoId) {
                            continue;
                        }
                        $codigoExistente = self::codigoLookup((string) ($existente['cuenta_codigo'] ?? $fuente['cuenta_codigo'] ?? ''));
                        $cuentaDestinoId = self::cuentaIdEnMapa($cuentasPorEmpresaCodigo, $destinoId, $codigoExistente);
                        if ($cuentaDestinoId === null) {
                            $errores[] = [
                                'accion' => 'homologar',
                                'articulo_id' => $articuloId,
                                'empresa_id' => $destinoId,
                                'tipoimputacion' => $tipo,
                                'codigo' => (string) ($existente['cuenta_codigo'] ?? ''),
                                'detalle' => 'Fila existente sin cuenta homologada',
                                'id' => (int) ($existente['id'] ?? 0),
                            ];
                            $articulosIncompletos[$articuloId] = true;
                            continue;
                        }
                        if ($cuentaDestinoId === $cuentaActualId) {
                            continue;
                        }
                        $homologaciones[] = [
                            'id' => (int) ($existente['id'] ?? 0),
                            'articulo_id' => $articuloId,
                            'empresa_id' => $destinoId,
                            'tipoimputacion' => $tipo,
                            'cuentacontable_id' => $cuentaDestinoId,
                            'cuentacontable_id_anterior' => $cuentaActualId,
                            'codigo' => (string) ($existente['cuenta_codigo'] ?? ''),
                        ];
                    }
                }
            }
        }

        $articulosConCuenta = count($porArticulo);

        return [
            'altas' => $altas,
            'homologaciones' => $homologaciones,
            'errores' => $errores,
            'articulos_con_cuenta' => $articulosConCuenta,
            'articulos_completos' => $articulosConCuenta - count($articulosIncompletos),
            'articulos_incompletos' => count($articulosIncompletos),
            'articulos_solo_origen' => count($articulosSoloOrigen),
            'pares_existentes' => $paresExistentes,
        ];
    }

    /**
     * @param  iterable<array{id?:int, empresa_id?:int, codigo?:string}|object>  $cuentas
     * @return array<int, array<string, int>>
     */
    public static function indexarCuentas(iterable $cuentas): array
    {
        $mapa = [];
        foreach ($cuentas as $cuenta) {
            $empresaId = (int) (is_array($cuenta) ? ($cuenta['empresa_id'] ?? 0) : ($cuenta->empresa_id ?? 0));
            $id = (int) (is_array($cuenta) ? ($cuenta['id'] ?? 0) : ($cuenta->id ?? 0));
            $codigo = trim((string) (is_array($cuenta) ? ($cuenta['codigo'] ?? '') : ($cuenta->codigo ?? '')));
            if ($empresaId <= 0 || $id <= 0 || $codigo === '') {
                continue;
            }
            $mapa[$empresaId][$codigo] = $id;
            $alt = ltrim($codigo, '0');
            if ($alt !== '' && $alt !== $codigo && ! isset($mapa[$empresaId][$alt])) {
                $mapa[$empresaId][$alt] = $id;
            }
        }

        return $mapa;
    }

    /**
     * @param  list<int>  $empresaIds
     * @return array<string, mixed>
     */
    public static function ejecutar(bool $aplicar, array $empresaIds = [], int $origenPreferido = 1, int $usuarioId = 1): array
    {
        $empresaIds = $empresaIds !== [] ? $empresaIds : self::empresasObjetivo();
        $usuarioId = max(1, $usuarioId);

        $filas = Articulo_Cuentacontable::query()
            ->join('cuentacontable', 'cuentacontable.id', '=', 'articulo_cuentacontable.cuentacontable_id')
            ->whereIn('articulo_cuentacontable.empresa_id', $empresaIds)
            ->get([
                'articulo_cuentacontable.id',
                'articulo_cuentacontable.articulo_id',
                'articulo_cuentacontable.empresa_id',
                'articulo_cuentacontable.tipoimputacion',
                'articulo_cuentacontable.cuentacontable_id',
                'articulo_cuentacontable.creousuario_id',
                'cuentacontable.empresa_id as cuenta_empresa_id',
                'cuentacontable.codigo as cuenta_codigo',
            ])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'articulo_id' => (int) $row->articulo_id,
                'empresa_id' => (int) $row->empresa_id,
                'tipoimputacion' => (string) $row->tipoimputacion,
                'cuentacontable_id' => (int) $row->cuentacontable_id,
                'creousuario_id' => (int) ($row->creousuario_id ?? 0),
                'cuenta_empresa_id' => (int) $row->cuenta_empresa_id,
                'cuenta_codigo' => (string) $row->cuenta_codigo,
            ])
            ->all();

        $cuentas = Cuentacontable::query()
            ->whereIn('empresa_id', $empresaIds)
            ->get(['id', 'empresa_id', 'codigo']);
        $mapa = self::indexarCuentas($cuentas);

        $plan = self::planificar($filas, $mapa, $empresaIds, $origenPreferido);

        $articulosTotal = (int) Articulo::query()->count();
        $plan['articulos_total'] = $articulosTotal;
        $plan['articulos_sin_cuenta'] = max(0, $articulosTotal - (int) $plan['articulos_con_cuenta']);
        $plan['aplicado'] = false;
        $plan['altas_aplicadas'] = 0;
        $plan['homologaciones_aplicadas'] = 0;

        if (! $aplicar) {
            return $plan;
        }

        $ahora = now();
        $altasAplicadas = 0;
        foreach (array_chunk($plan['altas'], 500) as $chunk) {
            $payload = [];
            foreach ($chunk as $alta) {
                $payload[] = [
                    'articulo_id' => (int) $alta['articulo_id'],
                    'empresa_id' => (int) $alta['empresa_id'],
                    'tipoimputacion' => (string) $alta['tipoimputacion'],
                    'cuentacontable_id' => (int) $alta['cuentacontable_id'],
                    'creousuario_id' => ((int) ($alta['creousuario_id'] ?? 0)) > 0
                        ? (int) $alta['creousuario_id']
                        : $usuarioId,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];
            }
            DB::table('articulo_cuentacontable')->insert($payload);
            $altasAplicadas += count($payload);
        }

        $homologacionesAplicadas = 0;
        $porCuenta = [];
        foreach ($plan['homologaciones'] as $homo) {
            $id = (int) ($homo['id'] ?? 0);
            $cuentaId = (int) ($homo['cuentacontable_id'] ?? 0);
            if ($id <= 0 || $cuentaId <= 0) {
                continue;
            }
            $porCuenta[$cuentaId][] = $id;
        }
        foreach ($porCuenta as $cuentaId => $ids) {
            foreach (array_chunk($ids, 1000) as $chunkIds) {
                $homologacionesAplicadas += DB::table('articulo_cuentacontable')
                    ->whereIn('id', $chunkIds)
                    ->update([
                        'cuentacontable_id' => $cuentaId,
                        'updated_at' => $ahora,
                    ]);
            }
        }

        $plan['aplicado'] = true;
        $plan['altas_aplicadas'] = $altasAplicadas;
        $plan['homologaciones_aplicadas'] = $homologacionesAplicadas;

        return $plan;
    }

    /**
     * Crea o homóloga la fila de un tipo en todas las empresas objetivo.
     */
    public static function asegurarDesdeCuentaOrigen(
        int $articuloId,
        string $tipoimputacion,
        int $cuentacontableOrigenId,
        int $usuarioId,
        ?array $empresaIds = null
    ): void {
        if ($articuloId <= 0 || $cuentacontableOrigenId <= 0) {
            return;
        }
        $tipo = self::normalizarTipo($tipoimputacion);
        if ($tipo === '') {
            return;
        }
        $empresaIds = $empresaIds !== null && $empresaIds !== [] ? $empresaIds : self::empresasObjetivo();
        $usuarioId = max(1, $usuarioId);

        foreach ($empresaIds as $empresaId) {
            $empresaId = (int) $empresaId;
            $cuentaId = CuentacontableEmpresaResolverSupport::resolverIdDesdeId($cuentacontableOrigenId, $empresaId);
            if ($cuentaId === null) {
                continue;
            }
            $existente = Articulo_Cuentacontable::query()
                ->where('articulo_id', $articuloId)
                ->where('empresa_id', $empresaId)
                ->where('tipoimputacion', $tipo)
                ->orderBy('id')
                ->first();
            if ($existente) {
                if ((int) $existente->cuentacontable_id !== $cuentaId) {
                    $existente->update(['cuentacontable_id' => $cuentaId]);
                }
                continue;
            }
            Articulo_Cuentacontable::query()->create([
                'articulo_id' => $articuloId,
                'empresa_id' => $empresaId,
                'tipoimputacion' => $tipo,
                'cuentacontable_id' => $cuentaId,
                'creousuario_id' => $usuarioId,
            ]);
        }
    }

    /**
     * @param  array<int, list<array<string, mixed>>>  $filasPorEmpresa
     * @param  list<int>  $empresaIds
     * @return array<string, mixed>|null
     */
    private static function elegirFuente(array $filasPorEmpresa, int $origenPreferido, array $empresaIds): ?array
    {
        $orden = array_values(array_unique(array_merge([$origenPreferido], $empresaIds)));
        foreach ($orden as $empresaId) {
            if (! empty($filasPorEmpresa[$empresaId][0])) {
                return $filasPorEmpresa[$empresaId][0];
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, int>>  $mapa
     */
    private static function cuentaIdEnMapa(array $mapa, int $empresaId, string $codigo): ?int
    {
        if ($codigo === '') {
            return null;
        }
        $id = (int) ($mapa[$empresaId][$codigo] ?? 0);
        if ($id > 0) {
            return $id;
        }
        $alt = ltrim($codigo, '0');
        if ($alt !== '' && $alt !== $codigo) {
            $id = (int) ($mapa[$empresaId][$alt] ?? 0);
            if ($id > 0) {
                return $id;
            }
        }

        return null;
    }

    private static function codigoLookup(string $codigo): string
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return '';
        }
        $alt = ltrim($codigo, '0');

        return $alt !== '' ? $codigo : '';
    }

    private static function normalizarTipo(string $tipo): string
    {
        return strtoupper(trim($tipo));
    }
}
