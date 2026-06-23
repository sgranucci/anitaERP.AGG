<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo_ParteUnica;
use RuntimeException;

/**
 * Interpreta NPU único, lista o rango contra los NPUs registrados en articulo_parte_unica.
 */
final class ArticuloEtiquetaNpuRangoSupport
{
    public const MAX_ETIQUETAS = 250;

    public const MAX_LISTADO_CONSULTA = 50;

    /**
     * @return array{0: string, 1: string}
     */
    public static function normalizarEntrada(string $desde, string $hasta): array
    {
        $desde = trim($desde);
        $hasta = trim($hasta);

        if (str_contains($desde, '/')) {
            $partes = array_map('trim', explode('/', $desde, 2));
            $desde = $partes[0] ?? '';
            if (($partes[1] ?? '') !== '') {
                $hasta = $partes[1];
            }
        }

        if (! self::esLista($desde)) {
            $desdeNum = self::aNumero($desde);
            $hastaNum = self::aNumero($hasta);
            if ($desdeNum !== null && $hastaNum !== null && $desdeNum > $hastaNum) {
                [$desde, $hasta] = [(string) $hastaNum, (string) $desdeNum];
            }
        }

        return [$desde, $hasta];
    }

    public static function esLista(string $valor): bool
    {
        return str_contains($valor, ',') || str_contains($valor, ';');
    }

    /**
     * @return list<int>
     */
    public static function npusRegistradosPorArticulo(int $articuloId): array
    {
        return Articulo_ParteUnica::query()
            ->where('articulo_id', $articuloId)
            ->orderBy('numeroparte')
            ->pluck('numeroparte')
            ->map(static fn ($n) => (int) $n)
            ->all();
    }

    /**
     * Listado paginado para consulta en modal (sin límite de impresión).
     *
     * @return array{
     *     npus: list<int>,
     *     total: int,
     *     page: int,
     *     per_page: int,
     *     last_page: int,
     * }
     */
    public static function consultaPaginada(int $articuloId, int $page = 1): array
    {
        $perPage = self::MAX_LISTADO_CONSULTA;
        $page = max(1, $page);

        $query = Articulo_ParteUnica::query()
            ->where('articulo_id', $articuloId)
            ->orderBy('numeroparte');

        $total = (int) $query->count();
        if ($total === 0) {
            throw new RuntimeException('No hay números de parte únicos registrados para este artículo.');
        }

        $lastPage = max(1, (int) ceil($total / $perPage));
        if ($page > $lastPage) {
            $page = $lastPage;
        }

        $npus = $query->forPage($page, $perPage)
            ->pluck('numeroparte')
            ->map(static fn ($n) => (int) $n)
            ->all();

        return [
            'npus' => $npus,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => $lastPage,
        ];
    }

    /**
     * Resuelve NPUs para imprimir (requiere criterio; máx. {@see MAX_ETIQUETAS}).
     *
     * @return list<int>
     */
    public static function resolverParaImpresion(int $articuloId, string $desde, string $hasta): array
    {
        [$desde, $hasta] = self::normalizarEntrada($desde, $hasta);

        if ($desde === '' && $hasta === '') {
            throw new RuntimeException('Indique el NPU, lista o rango a imprimir.');
        }

        return self::resolverCriterioParaArticulo($articuloId, $desde, $hasta, true);
    }

    /**
     * Resuelve NPUs con criterio para consulta previa a impresión (sin límite de listado).
     *
     * @return list<int>
     */
    public static function resolverCriterioConsulta(int $articuloId, string $desde, string $hasta): array
    {
        [$desde, $hasta] = self::normalizarEntrada($desde, $hasta);

        if ($desde === '' && $hasta === '') {
            throw new RuntimeException('Indique un NPU, lista o rango, o use Consultar sin criterio para ver el listado.');
        }

        return self::resolverCriterioParaArticulo($articuloId, $desde, $hasta, false);
    }

    /**
     * @deprecated Usar resolverParaImpresion() o consultaPaginada()
     *
     * @return list<int>
     */
    public static function resolverParaArticulo(int $articuloId, string $desde, string $hasta): array
    {
        return self::resolverParaImpresion($articuloId, $desde, $hasta);
    }

    /**
     * @return list<int>
     */
    private static function resolverCriterioParaArticulo(
        int $articuloId,
        string $desde,
        string $hasta,
        bool $validarMaxImpresion,
    ): array {
        $existentes = self::npusRegistradosPorArticulo($articuloId);

        if ($existentes === []) {
            throw new RuntimeException('No hay números de parte únicos registrados para este artículo.');
        }

        if (self::esLista($desde)) {
            $solicitados = self::parseListaSinValidarArticulo($desde);

            return self::filtrarSolicitadosConExistentes($solicitados, $existentes, $validarMaxImpresion);
        }

        $desdeNum = self::aNumero($desde);
        if ($desdeNum === null) {
            throw new RuntimeException('Indique un NPU válido (solo dígitos).');
        }

        $hastaTrim = trim($hasta);
        if ($hastaTrim === '') {
            return self::filtrarSolicitadosConExistentes([$desdeNum], $existentes, $validarMaxImpresion);
        }

        $hastaNum = self::aNumero($hastaTrim);
        if ($hastaNum === null) {
            throw new RuntimeException('Indique un NPU «hasta» válido (solo dígitos).');
        }

        if ($desdeNum > $hastaNum) {
            [$desdeNum, $hastaNum] = [$hastaNum, $desdeNum];
        }

        $enRango = array_values(array_filter(
            $existentes,
            static fn (int $n) => $n >= $desdeNum && $n <= $hastaNum,
        ));

        if ($enRango === []) {
            throw new RuntimeException(
                "No hay NPUs del {$desdeNum} al {$hastaNum} registrados para este artículo."
            );
        }

        if ($validarMaxImpresion) {
            return self::validarCantidadImpresion($enRango);
        }

        return $enRango;
    }

    /**
     * @deprecated Usar resolverParaImpresion()
     *
     * @return list<int>
     */
    public static function expandirALista(string $desde, string $hasta): array
    {
        throw new RuntimeException('Método obsoleto. Use resolverParaImpresion().');
    }

    /**
     * @param  list<int>  $solicitados
     * @param  list<int>  $existentes
     * @return list<int>
     */
    private static function filtrarSolicitadosConExistentes(
        array $solicitados,
        array $existentes,
        bool $validarMaxImpresion,
    ): array {
        $indice = array_flip($existentes);
        $resultado = [];
        $faltantes = [];

        foreach ($solicitados as $n) {
            if (isset($indice[$n])) {
                $resultado[] = $n;
            } else {
                $faltantes[] = $n;
            }
        }

        if ($faltantes !== []) {
            throw new RuntimeException(
                'NPU no registrado para este artículo: '.implode(', ', $faltantes).'.'
            );
        }

        if ($validarMaxImpresion) {
            return self::validarCantidadImpresion($resultado);
        }

        return $resultado;
    }

    public static function formatearCriterio(string $desde, string $hasta): string
    {
        [$desde, $hasta] = self::normalizarEntrada($desde, $hasta);

        if ($desde === '' && $hasta === '') {
            return 'Todos los NPUs del artículo';
        }

        if (self::esLista($desde)) {
            return 'NPU '.implode(', ', self::parseListaSinValidarArticulo($desde));
        }

        if ($hasta !== '') {
            return $desde.' al '.$hasta;
        }

        return 'NPU '.$desde;
    }

    /**
     * @return list<int>
     */
    private static function parseListaSinValidarArticulo(string $valor): array
    {
        $partes = preg_split('/[,;]+/', $valor) ?: [];
        $lista = [];

        foreach ($partes as $parte) {
            $num = self::aNumero(trim((string) $parte));
            if ($num === null) {
                throw new RuntimeException('Lista de NPU inválida: «'.trim((string) $parte).'».');
            }
            $lista[] = $num;
        }

        return array_values(array_unique($lista));
    }

    private static function aNumero(?string $valor): ?int
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return null;
        }

        if (! ctype_digit($valor)) {
            $soloDigitos = preg_replace('/\D+/', '', $valor) ?? '';
            if ($soloDigitos === '' || ! ctype_digit($soloDigitos)) {
                return null;
            }
            $valor = $soloDigitos;
        }

        $num = (int) $valor;

        return $num > 0 ? $num : null;
    }

    /**
     * @param  list<int>  $lista
     * @return list<int>
     */
    private static function validarCantidadImpresion(array $lista): array
    {
        if (count($lista) > self::MAX_ETIQUETAS) {
            throw new RuntimeException(
                'El criterio supera el máximo de '.self::MAX_ETIQUETAS.' etiquetas por impresión.'
            );
        }

        return $lista;
    }
}
