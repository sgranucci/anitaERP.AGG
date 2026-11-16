<?php

namespace App\Support\Contable\ConciliacionBancaria;

use App\Models\Contable\ConciliacionBancariaCodOp;
use App\Models\Contable\ConciliacionBancariaConcepto;
use App\Models\Contable\ConciliacionBancariaPatronGasto;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Mapas de codificación (concepto IB, cód. operación, patrones gasto).
 * Pantalla Contable → configuración. Fallback: config/conciliacion_bancaria.php.
 */
final class ConciliacionBancariaCodificacionMapasSupport
{
    private const CACHE_KEY = 'conciliacion_bancaria.codificacion_mapas_v1';

    public static function olvidarCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{
     *   concepto_map: array<string, array{codigo: int|string, descripcion: string}>,
     *   conceptos_acumulado: list<string>,
     *   cod_op_map: array<string, int|string>,
     *   codificacion_gastos: array<int|string, array{descripcion: string, patrones: list<string>}>
     * }
     */
    public static function estructuras(): array
    {
        return Cache::remember(self::CACHE_KEY, 300, function () {
            $tieneConcepto = Schema::hasTable('conciliacion_bancaria_concepto')
                && ConciliacionBancariaConcepto::query()->exists();
            $tieneCodOp = Schema::hasTable('conciliacion_bancaria_cod_op')
                && ConciliacionBancariaCodOp::query()->exists();
            $tienePatron = Schema::hasTable('conciliacion_bancaria_patron_gasto')
                && ConciliacionBancariaPatronGasto::query()->exists();

            return [
                'concepto_map' => $tieneConcepto ? self::conceptoMapDesdeDb() : (array) config('conciliacion_bancaria.concepto_map', []),
                'conceptos_acumulado' => $tieneConcepto
                    ? self::conceptosAcumuladoDesdeDb()
                    : array_values(array_map('strval', (array) config('conciliacion_bancaria.conceptos_acumulado', []))),
                'cod_op_map' => $tieneCodOp ? self::codOpMapDesdeDb() : (array) config('conciliacion_bancaria.cod_op_map', []),
                'codificacion_gastos' => $tienePatron
                    ? self::patronesGastoDesdeDb()
                    : (array) config('conciliacion_bancaria.codificacion_gastos', []),
            ];
        });
    }

    /**
     * @return array<string, array{codigo: int|string, descripcion: string}>
     */
    public static function conceptoMap(): array
    {
        return self::estructuras()['concepto_map'];
    }

    /**
     * @return list<string>
     */
    public static function conceptosAcumulado(): array
    {
        return self::estructuras()['conceptos_acumulado'];
    }

    /**
     * @return array<string, int|string>
     */
    public static function codOpMap(): array
    {
        return self::estructuras()['cod_op_map'];
    }

    /**
     * @return array<int|string, array{descripcion: string, patrones: list<string>}>
     */
    public static function codificacionGastos(): array
    {
        return self::estructuras()['codificacion_gastos'];
    }

    public static function descripcionCodigo(int|string $codigo): string
    {
        if ($codigo === 'AC') {
            return 'ACUMULADO DIARIO';
        }
        $def = self::codificacionGastos()[(int) $codigo] ?? [];

        return (string) ($def['descripcion'] ?? '');
    }

    /**
     * @return list<array{id: int|null, patron: string, codigo: string, descripcion: string, es_acumulado: bool, orden: int}>
     */
    public static function conceptosParaFormulario(): array
    {
        if (Schema::hasTable('conciliacion_bancaria_concepto') && ConciliacionBancariaConcepto::query()->exists()) {
            return ConciliacionBancariaConcepto::query()
                ->orderBy('orden')
                ->orderBy('id')
                ->get()
                ->map(fn (ConciliacionBancariaConcepto $r) => [
                    'id' => (int) $r->id,
                    'patron' => (string) $r->patron,
                    'codigo' => (string) $r->codigo,
                    'descripcion' => (string) ($r->descripcion ?? ''),
                    'es_acumulado' => (bool) $r->es_acumulado,
                    'orden' => (int) $r->orden,
                ])
                ->all();
        }

        $filas = [];
        $orden = 0;
        foreach ((array) config('conciliacion_bancaria.concepto_map', []) as $patron => $def) {
            $orden++;
            $filas[] = [
                'id' => null,
                'patron' => (string) $patron,
                'codigo' => (string) ($def['codigo'] ?? ''),
                'descripcion' => (string) ($def['descripcion'] ?? ''),
                'es_acumulado' => false,
                'orden' => $orden,
            ];
        }
        foreach ((array) config('conciliacion_bancaria.conceptos_acumulado', []) as $patron) {
            $orden++;
            $filas[] = [
                'id' => null,
                'patron' => (string) $patron,
                'codigo' => 'AC',
                'descripcion' => 'ACUMULADO DIARIO',
                'es_acumulado' => true,
                'orden' => $orden,
            ];
        }

        return $filas;
    }

    /**
     * @return list<array{id: int|null, cod_op: string, codigo: string, orden: int}>
     */
    public static function codOpsParaFormulario(): array
    {
        if (Schema::hasTable('conciliacion_bancaria_cod_op') && ConciliacionBancariaCodOp::query()->exists()) {
            return ConciliacionBancariaCodOp::query()
                ->orderBy('orden')
                ->orderBy('id')
                ->get()
                ->map(fn (ConciliacionBancariaCodOp $r) => [
                    'id' => (int) $r->id,
                    'cod_op' => (string) $r->cod_op,
                    'codigo' => (string) $r->codigo,
                    'orden' => (int) $r->orden,
                ])
                ->all();
        }

        $filas = [];
        $orden = 0;
        foreach ((array) config('conciliacion_bancaria.cod_op_map', []) as $codOp => $codigo) {
            $orden++;
            $filas[] = [
                'id' => null,
                'cod_op' => (string) $codOp,
                'codigo' => (string) $codigo,
                'orden' => $orden,
            ];
        }

        return $filas;
    }

    /**
     * @return list<array{id: int|null, codigo: string, descripcion: string, patrones: string, orden: int}>
     */
    public static function patronesGastoParaFormulario(): array
    {
        if (Schema::hasTable('conciliacion_bancaria_patron_gasto') && ConciliacionBancariaPatronGasto::query()->exists()) {
            return ConciliacionBancariaPatronGasto::query()
                ->orderBy('orden')
                ->orderBy('id')
                ->get()
                ->map(fn (ConciliacionBancariaPatronGasto $r) => [
                    'id' => (int) $r->id,
                    'codigo' => (string) $r->codigo,
                    'descripcion' => (string) ($r->descripcion ?? ''),
                    'patrones' => (string) ($r->patrones ?? ''),
                    'orden' => (int) $r->orden,
                ])
                ->all();
        }

        $filas = [];
        $orden = 0;
        foreach ((array) config('conciliacion_bancaria.codificacion_gastos', []) as $codigo => $def) {
            $orden++;
            $pats = array_map('strval', $def['patrones'] ?? []);
            $filas[] = [
                'id' => null,
                'codigo' => (string) $codigo,
                'descripcion' => (string) ($def['descripcion'] ?? ''),
                'patrones' => implode('|', $pats),
                'orden' => $orden,
            ];
        }

        return $filas;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    public static function guardarConceptos(array $filas): void
    {
        if (! Schema::hasTable('conciliacion_bancaria_concepto')) {
            throw new \RuntimeException('Falta la tabla conciliacion_bancaria_concepto.');
        }

        $ids = [];
        $orden = 0;
        foreach ($filas as $raw) {
            $patron = strtoupper(trim((string) ($raw['patron'] ?? '')));
            $codigo = strtoupper(trim((string) ($raw['codigo'] ?? '')));
            if ($patron === '' || $codigo === '') {
                continue;
            }
            if ($codigo !== 'AC' && ! ctype_digit($codigo)) {
                continue;
            }
            $orden++;
            $esAcumulado = filter_var($raw['es_acumulado'] ?? false, FILTER_VALIDATE_BOOLEAN)
                || $codigo === 'AC';
            $payload = [
                'patron' => $patron,
                'codigo' => $codigo === 'AC' ? 'AC' : (string) ((int) $codigo),
                'descripcion' => self::nullSiVacio($raw['descripcion'] ?? null),
                'es_acumulado' => $esAcumulado,
                'orden' => $orden,
            ];
            $ids[] = self::upsert(ConciliacionBancariaConcepto::class, (int) ($raw['id'] ?? 0), $payload);
        }
        self::borrarNoConservados(ConciliacionBancariaConcepto::query(), $ids);
        self::olvidarCache();
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    public static function guardarCodOps(array $filas): void
    {
        if (! Schema::hasTable('conciliacion_bancaria_cod_op')) {
            throw new \RuntimeException('Falta la tabla conciliacion_bancaria_cod_op.');
        }

        $ids = [];
        $orden = 0;
        foreach ($filas as $raw) {
            $codOp = strtoupper(trim((string) ($raw['cod_op'] ?? '')));
            $codigo = strtoupper(trim((string) ($raw['codigo'] ?? '')));
            if ($codOp === '' || $codigo === '') {
                continue;
            }
            if ($codigo !== 'AC' && ! ctype_digit($codigo)) {
                continue;
            }
            $orden++;
            $payload = [
                'cod_op' => $codOp,
                'codigo' => $codigo === 'AC' ? 'AC' : (string) ((int) $codigo),
                'orden' => $orden,
            ];
            $ids[] = self::upsert(ConciliacionBancariaCodOp::class, (int) ($raw['id'] ?? 0), $payload);
        }
        self::borrarNoConservados(ConciliacionBancariaCodOp::query(), $ids);
        self::olvidarCache();
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    public static function guardarPatronesGasto(array $filas): void
    {
        if (! Schema::hasTable('conciliacion_bancaria_patron_gasto')) {
            throw new \RuntimeException('Falta la tabla conciliacion_bancaria_patron_gasto.');
        }

        $ids = [];
        $orden = 0;
        $codigosVistos = [];
        foreach ($filas as $raw) {
            $codigo = strtoupper(trim((string) ($raw['codigo'] ?? '')));
            if ($codigo === '' || ($codigo !== 'AC' && ! ctype_digit($codigo))) {
                continue;
            }
            $codigoNorm = $codigo === 'AC' ? 'AC' : (string) ((int) $codigo);
            if (isset($codigosVistos[$codigoNorm])) {
                continue;
            }
            $codigosVistos[$codigoNorm] = true;
            $orden++;
            $payload = [
                'codigo' => $codigoNorm,
                'descripcion' => self::nullSiVacio($raw['descripcion'] ?? null),
                'patrones' => self::normalizarPatronesTexto($raw['patrones'] ?? null),
                'orden' => $orden,
            ];
            $ids[] = self::upsert(ConciliacionBancariaPatronGasto::class, (int) ($raw['id'] ?? 0), $payload);
        }
        self::borrarNoConservados(ConciliacionBancariaPatronGasto::query(), $ids);
        self::olvidarCache();
    }

    /**
     * @return array<string, array{codigo: int|string, descripcion: string}>
     */
    private static function conceptoMapDesdeDb(): array
    {
        $mapa = [];
        foreach (ConciliacionBancariaConcepto::query()->orderBy('orden')->orderBy('id')->get() as $r) {
            if ($r->es_acumulado) {
                continue;
            }
            $patron = strtoupper(trim((string) $r->patron));
            if ($patron === '' || isset($mapa[$patron])) {
                continue;
            }
            $mapa[$patron] = [
                'codigo' => self::parseCodigo((string) $r->codigo),
                'descripcion' => (string) ($r->descripcion ?? ''),
            ];
        }

        return $mapa;
    }

    /**
     * @return list<string>
     */
    private static function conceptosAcumuladoDesdeDb(): array
    {
        return ConciliacionBancariaConcepto::query()
            ->where('es_acumulado', true)
            ->orderBy('orden')
            ->pluck('patron')
            ->map(fn ($p) => strtoupper(trim((string) $p)))
            ->filter(fn ($p) => $p !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, int|string>
     */
    private static function codOpMapDesdeDb(): array
    {
        $mapa = [];
        foreach (ConciliacionBancariaCodOp::query()->orderBy('orden')->orderBy('id')->get() as $r) {
            $codOp = strtoupper(trim((string) $r->cod_op));
            if ($codOp === '' || isset($mapa[$codOp])) {
                continue;
            }
            $mapa[$codOp] = self::parseCodigo((string) $r->codigo);
        }

        return $mapa;
    }

    /**
     * @return array<int|string, array{descripcion: string, patrones: list<string>}>
     */
    private static function patronesGastoDesdeDb(): array
    {
        $mapa = [];
        foreach (ConciliacionBancariaPatronGasto::query()->orderBy('orden')->orderBy('id')->get() as $r) {
            $codigo = self::parseCodigo((string) $r->codigo);
            $key = is_int($codigo) ? $codigo : (string) $codigo;
            $mapa[$key] = [
                'descripcion' => (string) ($r->descripcion ?? ''),
                'patrones' => self::parsePatrones($r->patrones),
            ];
        }

        return $mapa;
    }

    /**
     * @param  class-string<Model>  $clase
     * @param  array<string, mixed>  $payload
     */
    private static function upsert(string $clase, int $id, array $payload): int
    {
        if ($id > 0) {
            /** @var Model|null $modelo */
            $modelo = $clase::query()->find($id);
            if ($modelo) {
                $modelo->fill($payload);
                $modelo->save();

                return (int) $modelo->getKey();
            }
        }

        /** @var Model $nuevo */
        $nuevo = $clase::query()->create($payload);

        return (int) $nuevo->getKey();
    }

    /**
     * @param  Builder<Model>  $query
     * @param  list<int>  $idsConservar
     */
    private static function borrarNoConservados(Builder $query, array $idsConservar): void
    {
        if ($idsConservar !== []) {
            $query->whereNotIn('id', $idsConservar);
        }
        EloquentAuditDeleteSupport::each($query);
    }

    private static function parseCodigo(string $codigo): int|string
    {
        $codigo = strtoupper(trim($codigo));
        if ($codigo === 'AC') {
            return 'AC';
        }

        return (int) $codigo;
    }

    /**
     * @return list<string>
     */
    private static function parsePatrones(?string $texto): array
    {
        $texto = trim((string) $texto);
        if ($texto === '') {
            return [];
        }
        $parts = preg_split('/[|;\n]+/', $texto) ?: [];

        return array_values(array_filter(array_map(
            fn ($p) => strtoupper(trim((string) $p)),
            $parts
        ), fn ($p) => $p !== ''));
    }

    private static function normalizarPatronesTexto(mixed $raw): ?string
    {
        $parts = is_array($raw) ? $raw : self::parsePatrones((string) $raw);
        $parts = array_values(array_filter(array_map(
            fn ($p) => strtoupper(trim((string) $p)),
            $parts
        ), fn ($p) => $p !== ''));

        return $parts === [] ? null : implode('|', $parts);
    }

    private static function nullSiVacio(mixed $v): ?string
    {
        $s = trim((string) ($v ?? ''));

        return $s === '' ? null : $s;
    }
}
