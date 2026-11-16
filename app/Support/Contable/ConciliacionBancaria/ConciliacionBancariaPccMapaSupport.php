<?php

namespace App\Support\Contable\ConciliacionBancaria;

use App\Models\Contable\ConciliacionBancariaPcc;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Mapa P.C.C. (grouping_code_ib → código conciliación).
 * Pantalla Contable → configuración. Fallback: config/conciliacion_bancaria.php.
 */
final class ConciliacionBancariaPccMapaSupport
{
    private const CACHE_KEY = 'conciliacion_bancaria.pcc.mapa_v1';

    public static function tieneTabla(): bool
    {
        return Schema::hasTable('conciliacion_bancaria_pcc');
    }

    /**
     * @return array{
     *   mapa: array<string, int|string>,
     *   acumulado: list<string>,
     *   ambiguos: array<string, list<array{codigo: int|string, descripcion: string, patrones_concepto: list<string>, patrones_descripcion: list<string>}>>
     * }
     */
    public static function estructuras(): array
    {
        return Cache::remember(self::CACHE_KEY, 300, function () {
            if (self::tieneTabla() && ConciliacionBancariaPcc::query()->exists()) {
                return self::estructurasDesdeDb();
            }

            return self::estructurasDesdeConfig();
        });
    }

    /**
     * @return array<string, int|string>
     */
    public static function mapa(): array
    {
        return self::estructuras()['mapa'];
    }

    /**
     * @return list<string>
     */
    public static function acumulados(): array
    {
        return self::estructuras()['acumulado'];
    }

    /**
     * @return array<string, list<array{codigo: int|string, descripcion: string, patrones_concepto: list<string>, patrones_descripcion: list<string>}>>
     */
    public static function ambiguos(): array
    {
        return self::estructuras()['ambiguos'];
    }

    /**
     * Filas para el formulario (incluye ids).
     *
     * @return list<array{id: int|null, pcc: string, codigo: string, descripcion: string, patrones_concepto: string, patrones_descripcion: string, orden: int}>
     */
    public static function paraFormulario(): array
    {
        if (self::tieneTabla() && ConciliacionBancariaPcc::query()->exists()) {
            return ConciliacionBancariaPcc::query()
                ->orderBy('orden')
                ->orderBy('id')
                ->get()
                ->map(fn (ConciliacionBancariaPcc $r) => [
                    'id' => (int) $r->id,
                    'pcc' => (string) $r->pcc,
                    'codigo' => (string) $r->codigo,
                    'descripcion' => (string) ($r->descripcion ?? ''),
                    'patrones_concepto' => (string) ($r->patrones_concepto ?? ''),
                    'patrones_descripcion' => (string) ($r->patrones_descripcion ?? ''),
                    'orden' => (int) $r->orden,
                ])
                ->all();
        }

        $filas = [];
        $orden = 0;
        foreach (self::estructurasDesdeConfig()['filas_planas'] as $f) {
            $orden++;
            $filas[] = [
                'id' => null,
                'pcc' => $f['pcc'],
                'codigo' => (string) $f['codigo'],
                'descripcion' => (string) ($f['descripcion'] ?? ''),
                'patrones_concepto' => implode('|', $f['patrones_concepto'] ?? []),
                'patrones_descripcion' => implode('|', $f['patrones_descripcion'] ?? []),
                'orden' => $orden,
            ];
        }

        return $filas;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    public static function guardar(array $filas): void
    {
        if (! self::tieneTabla()) {
            throw new \RuntimeException('Falta la tabla conciliacion_bancaria_pcc.');
        }

        $idsConservar = [];
        $orden = 0;

        foreach ($filas as $raw) {
            $pcc = ConciliacionBancariaMovimientoBancoSupport::normalizarPcc($raw['pcc'] ?? '');
            $codigo = strtoupper(trim((string) ($raw['codigo'] ?? '')));
            if ($pcc === '' || $codigo === '') {
                continue;
            }
            if ($codigo !== 'AC' && ! ctype_digit($codigo)) {
                continue;
            }
            $orden++;
            $payload = [
                'pcc' => $pcc,
                'codigo' => $codigo === 'AC' ? 'AC' : (string) ((int) $codigo),
                'descripcion' => self::nullSiVacio($raw['descripcion'] ?? null),
                'patrones_concepto' => self::normalizarPatronesTexto($raw['patrones_concepto'] ?? null),
                'patrones_descripcion' => self::normalizarPatronesTexto($raw['patrones_descripcion'] ?? null),
                'orden' => $orden,
            ];

            $id = (int) ($raw['id'] ?? 0);
            if ($id > 0) {
                $modelo = ConciliacionBancariaPcc::query()->find($id);
                if ($modelo) {
                    $modelo->fill($payload);
                    $modelo->save();
                    $idsConservar[] = $id;

                    continue;
                }
            }

            $nuevo = ConciliacionBancariaPcc::query()->create($payload);
            $idsConservar[] = (int) $nuevo->id;
        }

        $q = ConciliacionBancariaPcc::query();
        if ($idsConservar !== []) {
            $q->whereNotIn('id', $idsConservar);
        }
        EloquentAuditDeleteSupport::each($q);

        Cache::forget(self::CACHE_KEY);
    }

    public static function olvidarCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{
     *   mapa: array<string, int|string>,
     *   acumulado: list<string>,
     *   ambiguos: array<string, list<array{codigo: int|string, descripcion: string, patrones_concepto: list<string>, patrones_descripcion: list<string>}>>
     * }
     */
    private static function estructurasDesdeDb(): array
    {
        $mapa = [];
        $acumulado = [];
        $ambiguos = [];

        $rows = ConciliacionBancariaPcc::query()
            ->orderBy('orden')
            ->orderBy('id')
            ->get();

        foreach ($rows as $r) {
            $pcc = ConciliacionBancariaMovimientoBancoSupport::normalizarPcc($r->pcc);
            if ($pcc === '') {
                continue;
            }
            $codigo = self::parseCodigo((string) $r->codigo);
            $patConc = self::parsePatrones($r->patrones_concepto);
            $patDesc = self::parsePatrones($r->patrones_descripcion);
            $tienePatrones = $patConc !== [] || $patDesc !== [];

            if ($codigo === 'AC') {
                $acumulado[] = $pcc;
            }

            if ($tienePatrones) {
                $ambiguos[$pcc] ??= [];
                $ambiguos[$pcc][] = [
                    'codigo' => $codigo,
                    'descripcion' => (string) ($r->descripcion ?? ''),
                    'patrones_concepto' => $patConc,
                    'patrones_descripcion' => $patDesc,
                ];
            } elseif (! array_key_exists($pcc, $mapa)) {
                $mapa[$pcc] = $codigo;
            }
        }

        return [
            'mapa' => $mapa,
            'acumulado' => array_values(array_unique($acumulado)),
            'ambiguos' => $ambiguos,
        ];
    }

    /**
     * @return array{
     *   mapa: array<string, int|string>,
     *   acumulado: list<string>,
     *   ambiguos: array<string, list<array{codigo: int|string, descripcion: string, patrones_concepto: list<string>, patrones_descripcion: list<string>}>>,
     *   filas_planas: list<array<string, mixed>>
     * }
     */
    private static function estructurasDesdeConfig(): array
    {
        $mapa = (array) config('conciliacion_bancaria.pcc_map', []);
        $acumulado = array_values(array_map('strval', (array) config('conciliacion_bancaria.pcc_acumulado', [])));
        $ambiguosRaw = (array) config('conciliacion_bancaria.pcc_ambiguos', []);
        $ambiguos = [];
        $filasPlanas = [];

        foreach ($mapa as $pcc => $codigo) {
            $filasPlanas[] = [
                'pcc' => (string) $pcc,
                'codigo' => $codigo,
                'descripcion' => '',
                'patrones_concepto' => [],
                'patrones_descripcion' => [],
            ];
        }

        foreach ($ambiguosRaw as $pcc => $reglas) {
            if (! is_array($reglas)) {
                continue;
            }
            $pcc = (string) $pcc;
            $ambiguos[$pcc] = [];
            foreach ($reglas as $regla) {
                $item = [
                    'codigo' => $regla['codigo'] ?? 10,
                    'descripcion' => (string) ($regla['descripcion'] ?? ''),
                    'patrones_concepto' => array_values(array_map('strval', $regla['patrones_concepto'] ?? [])),
                    'patrones_descripcion' => array_values(array_map('strval', $regla['patrones_descripcion'] ?? [])),
                ];
                $ambiguos[$pcc][] = $item;
                $filasPlanas[] = [
                    'pcc' => $pcc,
                    'codigo' => $item['codigo'],
                    'descripcion' => $item['descripcion'],
                    'patrones_concepto' => $item['patrones_concepto'],
                    'patrones_descripcion' => $item['patrones_descripcion'],
                ];
            }
        }

        foreach ($acumulado as $pcc) {
            if (! isset($mapa[$pcc]) || $mapa[$pcc] !== 'AC') {
                // Ya puede estar en filas por mapa AC; si no, agregar.
                $existe = false;
                foreach ($filasPlanas as $f) {
                    if ($f['pcc'] === $pcc && (string) $f['codigo'] === 'AC') {
                        $existe = true;
                        break;
                    }
                }
                if (! $existe) {
                    $filasPlanas[] = [
                        'pcc' => $pcc,
                        'codigo' => 'AC',
                        'descripcion' => 'ACUMULADO DIARIO',
                        'patrones_concepto' => [],
                        'patrones_descripcion' => [],
                    ];
                }
            }
        }

        return [
            'mapa' => $mapa,
            'acumulado' => $acumulado,
            'ambiguos' => $ambiguos,
            'filas_planas' => $filasPlanas,
        ];
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
        if (is_array($raw)) {
            $parts = $raw;
        } else {
            $parts = self::parsePatrones((string) $raw);
        }
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
