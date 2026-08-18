<?php

namespace App\Support\Sueldos;

use App\ApiAnita;

/**
 * Lectura unificada auxrec/auxhist (nómina normal) y auxconf/auxconfh (confidencial).
 * Prefijos alineados con SicoreSueldosDatosService.
 */
final class AnitaAuxLiquidacionSupport
{
    public const NOMINA_NORMAL = 'normal';

    public const NOMINA_CONFIDENCIAL = 'confidencial';

    public const NOMINA_AMBOS = 'ambos';

    /** @var array<string, string> tabla => prefijo columnas */
    private const PREFIJO = [
        'auxrec' => 'aux_',
        'auxhist' => 'auxh_',
        'auxconf' => 'axco_',
        'auxconfh' => 'auxcoh_',
    ];

    public function __construct(private ApiAnita $apiAnita) {}

    /**
     * @return list<string>
     */
    public function tablasParaNomina(string $nomina, bool $soloHistorico = true): array
    {
        $nomina = strtolower(trim($nomina));
        $hist = $soloHistorico;
        $normal = $hist ? ['auxhist'] : ['auxrec', 'auxhist'];
        $conf = $hist ? ['auxconfh'] : ['auxconf', 'auxconfh'];

        return match ($nomina) {
            self::NOMINA_CONFIDENCIAL => $conf,
            self::NOMINA_AMBOS => array_merge($normal, $conf),
            default => $normal,
        };
    }

    /**
     * @param  list<int>  $codigosConcepto
     * @return array<int, array<int, array{importe:float,cantidad:float,valor:float}>> legajo => codigo => métricas
     */
    public function valoresPorLegajoConcepto(
        int $empresaAnita,
        int $liquidacionAnita,
        array $codigosConcepto,
        string $nomina = self::NOMINA_NORMAL,
        bool $soloHistorico = true
    ): array {
        $codigos = array_values(array_unique(array_filter(array_map('intval', $codigosConcepto), fn ($c) => $c > 0)));
        if ($codigos === []) {
            return [];
        }

        $acumulado = [];
        foreach ($this->tablasParaNomina($nomina, $soloHistorico) as $tabla) {
            $this->acumularTabla($tabla, $empresaAnita, $liquidacionAnita, $codigos, $acumulado);
        }

        return $acumulado;
    }

    /**
     * @return list<int>
     */
    public function legajosEnNomina(
        int $empresaAnita,
        int $liquidacionAnita,
        string $nomina = self::NOMINA_CONFIDENCIAL,
        bool $soloHistorico = true
    ): array {
        $legajos = [];
        foreach ($this->tablasParaNomina($nomina, $soloHistorico) as $tabla) {
            $prefijo = self::PREFIJO[$tabla] ?? null;
            if ($prefijo === null) {
                continue;
            }
            $payload = [
                'acc' => 'list',
                'sistema' => 'sueldos',
                'tabla' => $tabla,
                'campos' => $prefijo.'empresa,'.$prefijo.'liquidacion,'.$prefijo.'legajo',
                'whereArmado' => sprintf(
                    ' WHERE %sempresa = %d AND %sliquidacion = %d',
                    $prefijo,
                    $empresaAnita,
                    $prefijo,
                    $liquidacionAnita
                ),
                'orderBy' => $prefijo.'legajo',
            ];
            foreach (ApiAnita::decodificarListaFilas($this->apiAnita->apiCall($payload)) as $fila) {
                $fila = (array) $fila;
                $legajo = (int) ($fila[$prefijo.'legajo'] ?? 0);
                if ($legajo > 0) {
                    $legajos[$legajo] = $legajo;
                }
            }
        }

        return array_values($legajos);
    }

    /**
     * Filas normalizadas de nómina confidencial para una liquidación Anita.
     *
     * @return array{fuente:string,filas:list<array<string,mixed>>,errores:list<string>}
     */
    public function filasConfidencialesLiquidacion(
        int $empresaAnita,
        int $liquidacionAnita,
        string $fuente = 'auto'
    ): array {
        $fuente = strtolower(trim($fuente));
        $tablas = match ($fuente) {
            'auxconf' => ['auxconf'],
            'auxconfh' => ['auxconfh'],
            default => ['auxconf', 'auxconfh'],
        };

        $porTabla = [];
        $errores = [];
        foreach ($tablas as $tabla) {
            try {
                $porTabla[$tabla] = $this->leerTablaNormalizada($tabla, $empresaAnita, $liquidacionAnita);
            } catch (\Throwable $e) {
                $errores[] = $tabla.': '.$e->getMessage();
                $porTabla[$tabla] = [];
            }
        }

        if ($fuente === 'auto') {
            $actual = $porTabla['auxconf'] ?? [];
            $hist = $porTabla['auxconfh'] ?? [];
            if ($actual !== [] && $hist === []) {
                return ['fuente' => 'auxconf', 'filas' => $actual, 'errores' => $errores];
            }
            if ($hist !== [] && $actual === []) {
                return ['fuente' => 'auxconfh', 'filas' => $hist, 'errores' => $errores];
            }
            if ($actual === [] && $hist === []) {
                return ['fuente' => 'ninguna', 'filas' => [], 'errores' => $errores];
            }

            // Ambas con datos: comparar por clave natural; si idénticas, usar histórico.
            $mapA = $this->indexarPorClaveNatural($actual);
            $mapH = $this->indexarPorClaveNatural($hist);
            $claves = array_unique(array_merge(array_keys($mapA), array_keys($mapH)));
            $diferencias = 0;
            foreach ($claves as $k) {
                if (! isset($mapA[$k], $mapH[$k]) || $this->filaMetrica($mapA[$k]) !== $this->filaMetrica($mapH[$k])) {
                    $diferencias++;
                }
            }
            if ($diferencias > 0) {
                $errores[] = "auxconf y auxconfh difieren en {$diferencias} clave(s); especifique --fuente=auxconf|auxconfh";

                return ['fuente' => 'conflicto', 'filas' => [], 'errores' => $errores];
            }

            return ['fuente' => 'auxconfh', 'filas' => $hist, 'errores' => $errores];
        }

        $elegida = $tablas[0];

        return [
            'fuente' => $elegida,
            'filas' => $porTabla[$elegida] ?? [],
            'errores' => $errores,
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function leerTablaNormalizada(string $tabla, int $empresaAnita, int $liquidacionAnita): array
    {
        $prefijo = self::PREFIJO[$tabla] ?? null;
        if ($prefijo === null) {
            throw new \InvalidArgumentException("Tabla aux no soportada: {$tabla}");
        }

        // Descripción al final: el bridge parte por | sin respetar escapes.
        $campos = [
            $prefijo.'empresa',
            $prefijo.'liquidacion',
            $prefijo.'legajo',
            $prefijo.'codigo',
            $prefijo.'total',
            $prefijo.'haberes',
            $prefijo.'deduc',
            $prefijo.'centro',
            $prefijo.'cierre',
            $prefijo.'fecha',
            $prefijo.'valor',
            $prefijo.'nro_interno',
            $prefijo.'serial',
            $prefijo.'desc',
        ];

        $payload = [
            'acc' => 'list',
            'sistema' => 'sueldos',
            'tabla' => $tabla,
            'campos' => implode(',', $campos),
            'whereArmado' => sprintf(
                ' WHERE %sempresa = %d AND %sliquidacion = %d',
                $prefijo,
                $empresaAnita,
                $prefijo,
                $liquidacionAnita
            ),
            'orderBy' => $prefijo.'legajo,'.$prefijo.'codigo,'.$prefijo.'nro_interno',
        ];

        $parsed = ApiAnita::parsearRespuestaLista($this->apiAnita->apiCall($payload));
        if (! empty($parsed['error_lectura'])) {
            throw new \RuntimeException((string) $parsed['error_lectura']);
        }

        $out = [];
        foreach ($parsed['filas'] as $fila) {
            $fila = (array) $fila;
            $legajo = (int) ($fila[$prefijo.'legajo'] ?? 0);
            $codigo = (int) ($fila[$prefijo.'codigo'] ?? 0);
            if ($legajo <= 0 || $codigo <= 0) {
                continue;
            }
            $haberes = (float) ($fila[$prefijo.'haberes'] ?? 0);
            $deduc = (float) ($fila[$prefijo.'deduc'] ?? 0);
            $out[] = [
                'tabla' => $tabla,
                'empresa' => (int) ($fila[$prefijo.'empresa'] ?? $empresaAnita),
                'liquidacion' => (int) ($fila[$prefijo.'liquidacion'] ?? $liquidacionAnita),
                'legajo' => $legajo,
                'codigo' => $codigo,
                'descripcion' => trim((string) ($fila[$prefijo.'desc'] ?? '')),
                'cantidad' => (float) ($fila[$prefijo.'total'] ?? 0),
                'haberes' => $haberes,
                'deduc' => $deduc,
                'valor' => (float) ($fila[$prefijo.'valor'] ?? 0),
                'centro' => trim((string) ($fila[$prefijo.'centro'] ?? '')),
                'cierre' => trim((string) ($fila[$prefijo.'cierre'] ?? '')),
                'fecha' => (int) ($fila[$prefijo.'fecha'] ?? 0),
                'nro_interno' => (int) ($fila[$prefijo.'nro_interno'] ?? 0),
                'serial' => (int) ($fila[$prefijo.'serial'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string,mixed>>  $filas
     * @return array<string, array<string,mixed>>
     */
    private function indexarPorClaveNatural(array $filas): array
    {
        $map = [];
        foreach ($filas as $f) {
            $k = implode('|', [
                (int) $f['empresa'],
                (int) $f['liquidacion'],
                (int) $f['legajo'],
                (int) $f['codigo'],
                (int) $f['nro_interno'],
            ]);
            $map[$k] = $f;
        }

        return $map;
    }

    /**
     * @param  array<string,mixed>  $f
     */
    private function filaMetrica(array $f): string
    {
        return implode('|', [
            round((float) $f['haberes'], 4),
            round((float) $f['deduc'], 4),
            round((float) $f['cantidad'], 4),
            round((float) $f['valor'], 4),
        ]);
    }

    /**
     * @param  list<int>  $codigos
     * @param  array<int, array<int, array{importe:float,cantidad:float,valor:float}>>  $acumulado
     */
    private function acumularTabla(
        string $tabla,
        int $empresaAnita,
        int $liquidacionAnita,
        array $codigos,
        array &$acumulado
    ): void {
        $prefijo = self::PREFIJO[$tabla] ?? null;
        if ($prefijo === null) {
            return;
        }

        $campos = [
            $prefijo.'empresa',
            $prefijo.'liquidacion',
            $prefijo.'legajo',
            $prefijo.'codigo',
            $prefijo.'total',
            $prefijo.'haberes',
            $prefijo.'deduc',
            $prefijo.'valor',
        ];

        foreach (array_chunk($codigos, 80) as $lote) {
            $payload = [
                'acc' => 'list',
                'sistema' => 'sueldos',
                'tabla' => $tabla,
                'campos' => implode(',', $campos),
                'whereArmado' => sprintf(
                    ' WHERE %sempresa = %d AND %sliquidacion = %d AND %scodigo IN (%s)',
                    $prefijo,
                    $empresaAnita,
                    $prefijo,
                    $liquidacionAnita,
                    $prefijo,
                    implode(',', $lote)
                ),
                'orderBy' => $prefijo.'legajo,'.$prefijo.'codigo',
            ];

            foreach (ApiAnita::decodificarListaFilas($this->apiAnita->apiCall($payload)) as $fila) {
                $fila = (array) $fila;
                $legajo = (int) ($fila[$prefijo.'legajo'] ?? 0);
                $codigo = (int) ($fila[$prefijo.'codigo'] ?? 0);
                if ($legajo <= 0 || $codigo <= 0) {
                    continue;
                }
                $acumulado[$legajo][$codigo]['importe']
                    = (float) ($acumulado[$legajo][$codigo]['importe'] ?? 0)
                    + (float) ($fila[$prefijo.'haberes'] ?? 0)
                    + (float) ($fila[$prefijo.'deduc'] ?? 0);
                $acumulado[$legajo][$codigo]['cantidad']
                    = (float) ($acumulado[$legajo][$codigo]['cantidad'] ?? 0)
                    + (float) ($fila[$prefijo.'total'] ?? 0);
                $acumulado[$legajo][$codigo]['valor']
                    = (float) ($acumulado[$legajo][$codigo]['valor'] ?? 0)
                    + (float) ($fila[$prefijo.'valor'] ?? 0);
            }
        }
    }
}
