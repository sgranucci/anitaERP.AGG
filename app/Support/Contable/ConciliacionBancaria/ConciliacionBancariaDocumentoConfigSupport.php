<?php

namespace App\Support\Contable\ConciliacionBancaria;

use App\Models\Contable\ConciliacionBancariaDocGasto;
use App\Models\Contable\ConciliacionBancariaTipoComp;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * tipo_comp ↔ conceptos banco y líneas de documento de gastos bancarios.
 */
final class ConciliacionBancariaDocumentoConfigSupport
{
    private const CACHE_KEY = 'conciliacion_bancaria.documento_config_v1';

    public static function olvidarCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{
     *   tipo_comp: array<string, list<string>>,
     *   lineas: array<int|string, array{cuenta_codigo: ?string, concepto_ivacompra_codigo: ?string}>
     * }
     */
    public static function estructuras(): array
    {
        return Cache::remember(self::CACHE_KEY, 300, function () {
            return [
                'tipo_comp' => self::tipoCompMap(),
                'lineas' => self::lineasDocGasto(),
            ];
        });
    }

    /**
     * @return array<string, list<string>>
     */
    public static function tipoCompConceptosBanco(): array
    {
        return self::estructuras()['tipo_comp'];
    }

    /**
     * @return array{cuenta_codigo: ?string, concepto_ivacompra_codigo: ?string}|null
     */
    public static function lineaDocGasto(int|string $codigo): ?array
    {
        $lineas = self::estructuras()['lineas'];
        $key = is_numeric($codigo) ? (int) $codigo : (string) $codigo;
        if (isset($lineas[$key])) {
            return $lineas[$key];
        }
        if (isset($lineas[(string) $codigo])) {
            return $lineas[(string) $codigo];
        }

        return null;
    }

    public static function tipoCajaAbreviatura(): string
    {
        $v = trim(ConciliacionBancariaReglasSupport::texto(
            ConciliacionBancariaReglasSupport::CLAVE_DOC_TIPO_CAJA,
            (string) config('conciliacion_bancaria.documento_gastos.tipo_caja_abreviatura', 'EGR'),
        ));

        return $v !== '' ? strtoupper($v) : 'EGR';
    }

    public static function tipoCompraAbreviatura(): string
    {
        $v = trim(ConciliacionBancariaReglasSupport::texto(
            ConciliacionBancariaReglasSupport::CLAVE_DOC_TIPO_COMPRA,
            (string) config('conciliacion_bancaria.documento_gastos.tipo_compra_abreviatura', 'ICO'),
        ));

        return $v !== '' ? strtoupper($v) : 'ICO';
    }

    /**
     * @return list<array{id: int|null, tipo_comp: string, patrones: string, orden: int}>
     */
    public static function tipoCompParaFormulario(): array
    {
        if (Schema::hasTable('conciliacion_bancaria_tipo_comp') && ConciliacionBancariaTipoComp::query()->exists()) {
            return ConciliacionBancariaTipoComp::query()
                ->orderBy('orden')
                ->orderBy('id')
                ->get()
                ->map(fn (ConciliacionBancariaTipoComp $r) => [
                    'id' => (int) $r->id,
                    'tipo_comp' => (string) $r->tipo_comp,
                    'patrones' => (string) ($r->patrones ?? ''),
                    'orden' => (int) $r->orden,
                ])
                ->all();
        }

        $filas = [];
        $orden = 0;
        foreach ((array) config('conciliacion_bancaria.tipo_comp_conceptos_banco', []) as $tipo => $pats) {
            $orden++;
            $pats = array_map('strval', is_array($pats) ? $pats : []);
            $filas[] = [
                'id' => null,
                'tipo_comp' => (string) $tipo,
                'patrones' => implode('|', $pats),
                'orden' => $orden,
            ];
        }

        return $filas;
    }

    /**
     * @return list<array{id: int|null, codigo: string, cuenta_codigo: string, concepto_ivacompra_codigo: string, orden: int}>
     */
    public static function docGastoParaFormulario(): array
    {
        if (Schema::hasTable('conciliacion_bancaria_doc_gasto') && ConciliacionBancariaDocGasto::query()->exists()) {
            return ConciliacionBancariaDocGasto::query()
                ->orderBy('orden')
                ->orderBy('id')
                ->get()
                ->map(fn (ConciliacionBancariaDocGasto $r) => [
                    'id' => (int) $r->id,
                    'codigo' => (string) $r->codigo,
                    'cuenta_codigo' => (string) ($r->cuenta_codigo ?? ''),
                    'concepto_ivacompra_codigo' => (string) ($r->concepto_ivacompra_codigo ?? ''),
                    'orden' => (int) $r->orden,
                ])
                ->all();
        }

        $filas = [];
        $orden = 0;
        foreach ((array) config('conciliacion_bancaria.documento_gastos.lineas', []) as $codigo => $def) {
            $orden++;
            $filas[] = [
                'id' => null,
                'codigo' => (string) $codigo,
                'cuenta_codigo' => (string) ($def['cuenta_codigo'] ?? ''),
                'concepto_ivacompra_codigo' => (string) ($def['concepto_ivacompra_codigo'] ?? ''),
                'orden' => $orden,
            ];
        }

        return $filas;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    public static function guardarTipoComp(array $filas): void
    {
        if (! Schema::hasTable('conciliacion_bancaria_tipo_comp')) {
            throw new \RuntimeException('Falta la tabla conciliacion_bancaria_tipo_comp.');
        }

        $ids = [];
        $orden = 0;
        $vistos = [];
        foreach ($filas as $raw) {
            $tipo = strtoupper(trim((string) ($raw['tipo_comp'] ?? '')));
            if ($tipo === '' || isset($vistos[$tipo])) {
                continue;
            }
            $vistos[$tipo] = true;
            $orden++;
            $ids[] = self::upsert(ConciliacionBancariaTipoComp::class, (int) ($raw['id'] ?? 0), [
                'tipo_comp' => $tipo,
                'patrones' => self::normalizarPatrones($raw['patrones'] ?? null),
                'orden' => $orden,
            ]);
        }
        self::borrarNoConservados(ConciliacionBancariaTipoComp::query(), $ids);
        self::olvidarCache();
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    public static function guardarDocGasto(array $filas): void
    {
        if (! Schema::hasTable('conciliacion_bancaria_doc_gasto')) {
            throw new \RuntimeException('Falta la tabla conciliacion_bancaria_doc_gasto.');
        }

        $ids = [];
        $orden = 0;
        $vistos = [];
        foreach ($filas as $raw) {
            $codigo = strtoupper(trim((string) ($raw['codigo'] ?? '')));
            if ($codigo === '' || ($codigo !== 'AC' && ! ctype_digit($codigo))) {
                continue;
            }
            $codigoNorm = $codigo === 'AC' ? 'AC' : (string) ((int) $codigo);
            if (isset($vistos[$codigoNorm])) {
                continue;
            }
            $cuenta = trim((string) ($raw['cuenta_codigo'] ?? ''));
            $concepto = trim((string) ($raw['concepto_ivacompra_codigo'] ?? ''));
            if ($cuenta === '' && $concepto === '') {
                continue;
            }
            $vistos[$codigoNorm] = true;
            $orden++;
            $ids[] = self::upsert(ConciliacionBancariaDocGasto::class, (int) ($raw['id'] ?? 0), [
                'codigo' => $codigoNorm,
                'cuenta_codigo' => $cuenta !== '' ? $cuenta : null,
                'concepto_ivacompra_codigo' => $concepto !== '' ? $concepto : null,
                'orden' => $orden,
            ]);
        }
        self::borrarNoConservados(ConciliacionBancariaDocGasto::query(), $ids);
        self::olvidarCache();
    }

    /**
     * @return array<string, list<string>>
     */
    private static function tipoCompMap(): array
    {
        if (Schema::hasTable('conciliacion_bancaria_tipo_comp') && ConciliacionBancariaTipoComp::query()->exists()) {
            $mapa = [];
            foreach (ConciliacionBancariaTipoComp::query()->orderBy('orden')->get() as $r) {
                $tipo = strtoupper(trim((string) $r->tipo_comp));
                if ($tipo === '') {
                    continue;
                }
                $mapa[$tipo] = self::parsePatrones($r->patrones);
            }

            return $mapa;
        }

        $mapa = [];
        foreach ((array) config('conciliacion_bancaria.tipo_comp_conceptos_banco', []) as $tipo => $pats) {
            $mapa[strtoupper((string) $tipo)] = array_values(array_map(
                fn ($p) => strtoupper((string) $p),
                is_array($pats) ? $pats : []
            ));
        }

        return $mapa;
    }

    /**
     * @return array<int|string, array{cuenta_codigo: ?string, concepto_ivacompra_codigo: ?string}>
     */
    private static function lineasDocGasto(): array
    {
        if (Schema::hasTable('conciliacion_bancaria_doc_gasto') && ConciliacionBancariaDocGasto::query()->exists()) {
            $mapa = [];
            foreach (ConciliacionBancariaDocGasto::query()->orderBy('orden')->get() as $r) {
                $codigo = trim((string) $r->codigo);
                if ($codigo === '') {
                    continue;
                }
                $key = ctype_digit($codigo) ? (int) $codigo : $codigo;
                $mapa[$key] = [
                    'cuenta_codigo' => $r->cuenta_codigo !== null && $r->cuenta_codigo !== '' ? (string) $r->cuenta_codigo : null,
                    'concepto_ivacompra_codigo' => $r->concepto_ivacompra_codigo !== null && $r->concepto_ivacompra_codigo !== ''
                        ? (string) $r->concepto_ivacompra_codigo
                        : null,
                ];
            }

            return $mapa;
        }

        $mapa = [];
        foreach ((array) config('conciliacion_bancaria.documento_gastos.lineas', []) as $codigo => $def) {
            $key = is_numeric($codigo) ? (int) $codigo : (string) $codigo;
            $mapa[$key] = [
                'cuenta_codigo' => isset($def['cuenta_codigo']) && $def['cuenta_codigo'] !== null && $def['cuenta_codigo'] !== ''
                    ? (string) $def['cuenta_codigo']
                    : null,
                'concepto_ivacompra_codigo' => isset($def['concepto_ivacompra_codigo']) && $def['concepto_ivacompra_codigo'] !== null && $def['concepto_ivacompra_codigo'] !== ''
                    ? (string) $def['concepto_ivacompra_codigo']
                    : null,
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

    private static function normalizarPatrones(mixed $raw): ?string
    {
        $parts = is_array($raw) ? $raw : self::parsePatrones((string) $raw);
        $parts = array_values(array_filter(array_map(
            fn ($p) => strtoupper(trim((string) $p)),
            $parts
        ), fn ($p) => $p !== ''));

        return $parts === [] ? null : implode('|', $parts);
    }
}
