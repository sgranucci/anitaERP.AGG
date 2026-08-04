<?php

namespace App\Support\Ai;

use App\Models\Contable\Cuentacontable;
use App\Models\Configuracion\Empresa;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Contable\CuentacontableConsultaSupport;
use Illuminate\Support\Collection;

/**
 * Resolución de maestros para consulta IA: empresa y cuenta por id/código o nombre (parecido).
 */
final class AiResolucionMaestrosSupport
{
    /**
     * @param  array<string,mixed>  $params
     * @return array{ok: bool, empresa_id?: int|null, error?: string}
     */
    public static function resolverEmpresa(array $params): array
    {
        $id = (int) ($params['empresa_id'] ?? 0);
        if ($id > 0) {
            if (! self::empresaVisible($id)) {
                return ['ok' => false, 'error' => 'No se encontró la empresa #'.$id.' (o no está asignada a su usuario).'];
            }

            return ['ok' => true, 'empresa_id' => $id];
        }

        $ref = trim((string) ($params['empresa_nombre'] ?? $params['empresa_codigo'] ?? ''));
        if ($ref === '') {
            return ['ok' => true, 'empresa_id' => null];
        }

        $porCodigo = Empresa::query()->where('codigo', $ref)->first()
            ?? (ctype_digit($ref) ? Empresa::query()->where('codigo', (int) $ref)->first() : null)
            ?? (ctype_digit($ref) ? Empresa::query()->find((int) $ref) : null);

        if ($porCodigo && self::empresaVisible((int) $porCodigo->id)) {
            return ['ok' => true, 'empresa_id' => (int) $porCodigo->id];
        }

        if (ctype_digit($ref) && strlen($ref) <= 6) {
            return ['ok' => false, 'error' => 'No se encontró la empresa «'.$ref.'».'];
        }

        $hits = self::buscarEmpresasPorNombre($ref);
        if ($hits->isEmpty()) {
            return ['ok' => false, 'error' => 'No se encontró empresa parecida a «'.$ref.'».'];
        }

        $elegida = self::elegirEmpresaUnica($hits, $ref);
        if ($elegida !== null) {
            return ['ok' => true, 'empresa_id' => (int) $elegida->id];
        }

        $candidatos = $hits->take(5)->map(
            fn (Empresa $e) => '#'.$e->id.' '.$e->nombre.(isset($e->codigo) ? ' (cód. '.$e->codigo.')' : '')
        )->all();

        return [
            'ok' => false,
            'error' => 'Hay varias empresas parecidas a «'.$ref.'». Aclare con id o nombre más preciso: '
                .implode('; ', $candidatos),
        ];
    }

    /**
     * @param  array<string,mixed>  $params
     * @return array{ok: bool, cuenta?: Cuentacontable|null, error?: string}
     */
    public static function resolverCuenta(array $params, bool $requerida = true, ?int $empresaId = null): array
    {
        $ref = trim((string) (
            $params['cuenta_nombre']
            ?? $params['cuenta_codigo']
            ?? $params['codigo']
            ?? $params['valor']
            ?? ''
        ));

        if ($ref === '') {
            if ($requerida) {
                return ['ok' => false, 'error' => 'Indique código o nombre de cuenta contable.'];
            }

            return ['ok' => true, 'cuenta' => null];
        }

        $codigo = self::normalizarCodigoCuenta($ref);
        $pareceCodigo = $codigo !== '' && ctype_digit($codigo) && strlen($codigo) >= 4
            && preg_match('/^\d{4,12}$/', $codigo) === 1
            && ! self::pareceNombreCuenta($ref);

        if ($pareceCodigo) {
            $q = Cuentacontable::query()->where(function ($w) use ($codigo) {
                $w->where('codigo', $codigo)->orWhere('codigo', (int) $codigo);
            });
            if ($empresaId !== null && $empresaId > 0) {
                $q->where('empresa_id', $empresaId);
            }
            $cuenta = $q->orderBy('id')->first();
            if (! $cuenta && $empresaId !== null && $empresaId > 0) {
                // Fallback: mismo código en otra empresa (plan compartido / legacy)
                $cuenta = Cuentacontable::query()
                    ->where(function ($w) use ($codigo) {
                        $w->where('codigo', $codigo)->orWhere('codigo', (int) $codigo);
                    })
                    ->orderBy('id')
                    ->first();
            }
            if (! $cuenta) {
                return ['ok' => false, 'error' => 'No se encontró la cuenta «'.$codigo.'».'];
            }

            return ['ok' => true, 'cuenta' => $cuenta];
        }

        $texto = self::limpiarReferenciaCuenta($ref);
        if ($texto === '') {
            if ($requerida) {
                return ['ok' => false, 'error' => 'Indique código o nombre de cuenta contable.'];
            }

            return ['ok' => true, 'cuenta' => null];
        }

        $q = Cuentacontable::query()->select(
            'cuentacontable.id',
            'cuentacontable.empresa_id',
            'cuentacontable.codigo',
            'cuentacontable.nombre',
            'cuentacontable.tipocuenta',
            'cuentacontable.nivel'
        );
        if ($empresaId !== null && $empresaId > 0) {
            $q->where('cuentacontable.empresa_id', $empresaId);
        }
        CuentacontableConsultaSupport::aplicarFiltroTexto($q, $texto);
        CuentacontableConsultaSupport::ordenarPorRelevancia($q, $texto);
        $hits = $q->limit(40)->get();

        if ($hits->isEmpty() && ($empresaId === null || $empresaId <= 0)) {
            // nada
        } elseif ($hits->isEmpty() && $empresaId !== null && $empresaId > 0) {
            $q2 = Cuentacontable::query()->select(
                'cuentacontable.id',
                'cuentacontable.empresa_id',
                'cuentacontable.codigo',
                'cuentacontable.nombre',
                'cuentacontable.tipocuenta',
                'cuentacontable.nivel'
            );
            CuentacontableConsultaSupport::aplicarFiltroTexto($q2, $texto);
            CuentacontableConsultaSupport::ordenarPorRelevancia($q2, $texto);
            $hits = $q2->limit(40)->get();
        }

        if ($hits->isEmpty()) {
            return ['ok' => false, 'error' => 'No se encontró cuenta parecida a «'.$texto.'».'];
        }

        $hits = self::deduplicarCuentasPorCodigo($hits, $empresaId);
        $elegida = self::elegirCuentaUnica($hits, $texto);
        if ($elegida !== null) {
            return ['ok' => true, 'cuenta' => $elegida];
        }

        $candidatos = $hits->take(5)->map(
            fn (Cuentacontable $c) => $c->codigo.' — '.$c->nombre
        )->unique()->values()->all();

        return [
            'ok' => false,
            'error' => 'Hay varias cuentas parecidas a «'.$texto.'». Aclare con código: '
                .implode('; ', $candidatos),
        ];
    }

    public static function normalizarCodigoCuenta(string $codigo): string
    {
        return preg_replace('/[\s\-\.]+/', '', trim($codigo)) ?? '';
    }

    /**
     * Si la cuenta es de título/total (no imputable), devolver las hijas imputables del prefijo.
     * tipocuenta: 1 = imputable, 2 = título, 3 = total.
     *
     * @return list<Cuentacontable>
     */
    public static function cuentasParaConsulta(Cuentacontable $cuenta): array
    {
        $tipo = (string) ($cuenta->tipocuenta ?? '');
        if ($tipo === '1' || $tipo === '') {
            return [$cuenta];
        }

        $codigo = self::normalizarCodigoCuenta((string) $cuenta->codigo);
        $prefijo = rtrim($codigo, '0');
        if ($prefijo === '' || mb_strlen($prefijo) < 3) {
            $prefijo = mb_substr($codigo, 0, min(3, mb_strlen($codigo)));
        }

        $q = Cuentacontable::query()
            ->where('tipocuenta', '1')
            ->where('codigo', 'like', $prefijo.'%')
            ->orderBy('codigo');
        if ((int) ($cuenta->empresa_id ?? 0) > 0) {
            $q->where('empresa_id', (int) $cuenta->empresa_id);
        }

        $hijas = $q->get();
        if ($hijas->isEmpty()) {
            return [$cuenta];
        }

        return $hijas->all();
    }

    /**
     * @param  list<Cuentacontable>  $cuentas
     * @return list<int>
     */
    public static function idsCuentas(array $cuentas): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (Cuentacontable $c) => (int) $c->id,
            $cuentas
        ), static fn (int $id) => $id > 0)));
    }

    private static function pareceNombreCuenta(string $ref): bool
    {
        $ref = trim($ref);
        if ($ref === '') {
            return false;
        }
        // Tiene letras → nombre (ej. "caja y banco", "1110 caja")
        return preg_match('/[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]/u', $ref) === 1;
    }

    private static function limpiarReferenciaCuenta(string $ref): string
    {
        $ref = trim($ref);
        $ref = preg_replace('/^(de\s+la|de\s+el|del|de|la|el)\s+/ui', '', $ref) ?? $ref;

        return trim($ref);
    }

    private static function empresaVisible(int $empresaId): bool
    {
        if ($empresaId <= 0) {
            return false;
        }

        /** @var EmpresaRepositoryInterface $repo */
        $repo = app(EmpresaRepositoryInterface::class);
        $ids = $repo->allFiltrado()->pluck('id')->map(fn ($id) => (int) $id)->all();

        // Sin empresas asignadas = acceso total
        if ($ids === []) {
            return Empresa::query()->whereKey($empresaId)->exists();
        }

        return in_array($empresaId, $ids, true);
    }

    /**
     * @return Collection<int, Empresa>
     */
    private static function buscarEmpresasPorNombre(string $texto): Collection
    {
        /** @var EmpresaRepositoryInterface $repo */
        $repo = app(EmpresaRepositoryInterface::class);
        $base = $repo->allFiltrado();
        if ($base->isEmpty()) {
            $base = Empresa::query()->orderBy('id')->get();
        }

        $norm = self::normalizarTexto($texto);
        $tokens = array_values(array_filter(
            preg_split('/\s+/u', $norm) ?: [],
            fn ($t) => mb_strlen((string) $t) >= 2
        ));

        $filtradas = $base->filter(function (Empresa $e) use ($norm, $tokens, $texto) {
            $nombreNorm = self::normalizarTexto((string) $e->nombre);
            if ($nombreNorm === '') {
                return false;
            }
            if (str_contains($nombreNorm, $norm)) {
                return true;
            }
            foreach ($tokens as $tok) {
                if (! str_contains($nombreNorm, (string) $tok)) {
                    return false;
                }
            }
            if ($tokens !== []) {
                return true;
            }

            return similar_text($nombreNorm, $norm) / max(1, mb_strlen($norm)) > 0.7;
        })->values();

        if ($filtradas->isEmpty() && mb_strlen($norm) >= 4) {
            $filtradas = $base->filter(function (Empresa $e) use ($norm) {
                $nombreNorm = self::normalizarTexto((string) $e->nombre);
                $dist = levenshtein(
                    mb_substr($norm, 0, 20),
                    mb_substr($nombreNorm, 0, 20)
                );

                return $dist <= 3 || str_contains($nombreNorm, mb_substr($norm, 0, 4));
            })->values();
        }

        return $filtradas->sortBy(fn (Empresa $e) => self::scoreEmpresa($texto, $e))->values();
    }

    /**
     * @param  Collection<int, Empresa>  $hits
     */
    private static function elegirEmpresaUnica(Collection $hits, string $texto): ?Empresa
    {
        if ($hits->isEmpty()) {
            return null;
        }
        if ($hits->count() === 1) {
            return $hits->first();
        }

        $mejor = $hits->first();
        $scoreMejor = self::scoreEmpresa($texto, $mejor);
        $segundo = $hits->get(1);
        $scoreSegundo = $segundo ? self::scoreEmpresa($texto, $segundo) : 9999;

        // Ganador claro
        if ($scoreMejor <= 8 && $scoreSegundo >= $scoreMejor + 12) {
            return $mejor;
        }
        if ($scoreMejor <= 5) {
            return $mejor;
        }

        return null;
    }

    private static function scoreEmpresa(string $texto, Empresa $e): int
    {
        $norm = self::normalizarTexto($texto);
        $nombre = self::normalizarTexto((string) $e->nombre);
        $score = 50;

        if ($nombre === $norm) {
            return 0;
        }
        if (str_starts_with($nombre, $norm)) {
            $score = 2;
        } elseif (preg_match('/\b'.preg_quote($norm, '/').'\b/u', $nombre) === 1) {
            $score = 4;
        } elseif (str_contains($nombre, $norm)) {
            $score = 10;
        } else {
            $score = 20 + levenshtein(mb_substr($norm, 0, 20), mb_substr($nombre, 0, 20));
        }

        // Preferir razón social principal si el usuario no pidió budget/temporal
        $pidioExtras = str_contains($norm, 'budget') || str_contains($norm, 'temporal');
        if (! $pidioExtras) {
            if (str_contains($nombre, 'temporal')) {
                $score += 25;
            }
            if (str_contains($nombre, 'budget')) {
                $score += 20;
            }
        }

        $score += (int) min(15, mb_strlen($nombre) / 4);

        return $score;
    }

    /**
     * @param  Collection<int, Cuentacontable>  $hits
     * @return Collection<int, Cuentacontable>
     */
    private static function deduplicarCuentasPorCodigo(Collection $hits, ?int $empresaId): Collection
    {
        return $hits
            ->groupBy(fn (Cuentacontable $c) => (string) $c->codigo)
            ->map(function (Collection $grupo) use ($empresaId) {
                if ($empresaId !== null && $empresaId > 0) {
                    $deEmpresa = $grupo->first(fn (Cuentacontable $c) => (int) $c->empresa_id === $empresaId);
                    if ($deEmpresa) {
                        return $deEmpresa;
                    }
                }

                return $grupo->sortBy('id')->first();
            })
            ->values();
    }

    /**
     * @param  Collection<int, Cuentacontable>  $hits
     */
    private static function elegirCuentaUnica(Collection $hits, string $texto): ?Cuentacontable
    {
        if ($hits->isEmpty()) {
            return null;
        }
        if ($hits->count() === 1) {
            return $hits->first();
        }

        $ordenadas = $hits->sortBy(fn (Cuentacontable $c) => self::scoreCuenta($texto, $c))->values();
        $mejor = $ordenadas->first();
        $scoreMejor = self::scoreCuenta($texto, $mejor);
        $segundo = $ordenadas->get(1);
        $scoreSegundo = $segundo ? self::scoreCuenta($texto, $segundo) : 9999;

        if ($scoreMejor <= 6 && $scoreSegundo >= $scoreMejor + 10) {
            return $mejor;
        }
        if ($scoreMejor <= 3) {
            return $mejor;
        }

        return null;
    }

    private static function scoreCuenta(string $texto, Cuentacontable $c): int
    {
        $norm = self::normalizarTexto($texto);
        $nombre = self::normalizarTexto((string) $c->nombre);
        $codigo = (string) $c->codigo;

        if ($nombre === $norm) {
            $score = 0;
        } elseif (str_starts_with($nombre, $norm)) {
            $score = 1;
        } elseif (str_contains($nombre, $norm)) {
            $score = 4;
        } else {
            // Tokens AND ya filtraron; menor longitud de nombre = más específico
            $score = 8;
            foreach (preg_split('/\s+/u', $norm) ?: [] as $tok) {
                if ($tok !== '' && ! str_contains($nombre, $tok)) {
                    $score += 5;
                }
            }
        }

        $pidioTotal = str_contains($norm, 'total');
        if (! $pidioTotal && str_starts_with($nombre, 'total ')) {
            $score += 30;
        }

        // Preferir cuentas de movimiento / nivel operativo frente a títulos muy cortos genéricos
        $score += (int) min(10, mb_strlen($nombre) / 8);
        if ($codigo !== '' && str_ends_with($codigo, '99999')) {
            $score += 15; // totales de rubro típicos
        }

        return $score;
    }

    private static function normalizarTexto(string $texto): string
    {
        $texto = mb_strtolower(trim($texto), 'UTF-8');
        $texto = strtr($texto, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);
        $texto = preg_replace('/[^a-z0-9\s]/u', ' ', $texto) ?? $texto;
        $texto = preg_replace('/\s+/u', ' ', $texto) ?? $texto;

        return trim($texto);
    }
}
