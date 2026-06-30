<?php

namespace App\Support\Compras;

use Illuminate\Database\Query\Builder;

final class RequisicionReporteCriteriosSupport
{
    /**
     * @return array{0: string, 1: string}
     */
    public static function normalizarRangoNumeros(string $desde, string $hasta): array
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

        if ($desde === '' && $hasta !== '' && ! self::esListaNumeros($hasta)) {
            $desde = '1';
        }

        if (! self::esListaNumeros($desde)) {
            $desdeNum = self::numeroValido($desde);
            $hastaNum = self::numeroValido($hasta);
            if ($desdeNum !== null && $hastaNum !== null && $desdeNum > $hastaNum) {
                [$desde, $hasta] = [(string) $hastaNum, (string) $desdeNum];
            }
        }

        return [$desde, $hasta];
    }

    public static function esListaNumeros(string $valor): bool
    {
        return str_contains($valor, ',') || str_contains($valor, ';');
    }

    /**
     * @return list<int>
     */
    public static function parseListaNumeros(string $valor): array
    {
        $partes = preg_split('/[,;]+/', $valor) ?: [];

        return array_values(array_unique(array_filter(array_map(
            static function ($c) {
                return self::numeroValido((string) $c);
            },
            $partes,
        ), static fn ($n) => $n !== null)));
    }

    public static function numeroValido(?string $valor): ?int
    {
        $valor = trim((string) $valor);
        if ($valor === '' || ! ctype_digit($valor)) {
            return null;
        }

        $entero = (int) $valor;

        return $entero > 0 ? $entero : null;
    }

    public static function aplicarFiltroNumerosRequisicion(
        Builder $query,
        string $desde,
        string $hasta,
        string $columna = 'r.numerorequisicion',
    ): void {
        [$desde, $hasta] = self::normalizarRangoNumeros($desde, $hasta);

        if ($desde === '' && $hasta === '') {
            return;
        }

        if (self::esListaNumeros($desde)) {
            $numeros = self::parseListaNumeros($desde);
            if ($numeros !== []) {
                $query->whereIn($columna, $numeros);
            }

            return;
        }

        $desdeNum = self::numeroValido($desde);
        $hastaNum = self::numeroValido($hasta);

        if ($desdeNum === null) {
            return;
        }

        if ($hasta === '' || $hastaNum === null) {
            $query->where($columna, $desdeNum);

            return;
        }

        if ($desdeNum > $hastaNum) {
            [$desdeNum, $hastaNum] = [$hastaNum, $desdeNum];
        }

        $query->whereBetween($columna, [$desdeNum, $hastaNum]);
    }

    public static function aplicarFiltroUsuarios(
        Builder $query,
        string $usuarios,
        string $columna = 'r.creousuario_id',
    ): void {
        self::aplicarFiltroIds($query, $usuarios, $columna);
    }

    public static function aplicarFiltroCentrocostosCodigo(
        Builder $query,
        string $codigos,
        string $columnaCabecera = 'cc.codigo',
        string $columnaDestino = 'ccd.codigo',
    ): void {
        $codigos = trim($codigos);
        if ($codigos === '') {
            return;
        }

        $lista = self::parseListaCodigos($codigos);
        if ($lista === []) {
            return;
        }

        $query->where(function (Builder $sub) use ($lista, $columnaCabecera, $columnaDestino) {
            $sub->whereIn($columnaCabecera, $lista)
                ->orWhereIn($columnaDestino, $lista);
        });
    }

    /**
     * @return list<string>
     */
    public static function parseListaCodigos(string $valor): array
    {
        $partes = preg_split('/[,;]+/', $valor) ?: [];

        return array_values(array_unique(array_filter(array_map(
            static fn ($c) => trim((string) $c),
            $partes,
        ), static fn (string $c) => $c !== '')));
    }

    /** @deprecated Use aplicarFiltroCentrocostosCodigo */
    public static function aplicarFiltroCentrocostos(
        Builder $query,
        string $centrocostos,
        string $columnaCabecera = 'r.centrocosto_id',
        string $columnaDestino = 'ra.centrocostodestino_id',
    ): void {
        self::aplicarFiltroCentrocostosCodigo($query, $centrocostos, 'cc.codigo', 'ccd.codigo');
    }

    private static function aplicarFiltroIds(Builder $query, string $valor, string $columna): void
    {
        $valor = trim($valor);
        if ($valor === '') {
            return;
        }

        if (self::esListaNumeros($valor)) {
            $ids = self::parseListaNumeros($valor);
            if ($ids !== []) {
                $query->whereIn($columna, $ids);
            }

            return;
        }

        if (str_contains($valor, '/')) {
            $partes = array_map('trim', explode('/', $valor, 2));
            $desde = self::numeroValido($partes[0] ?? '');
            $hasta = self::numeroValido($partes[1] ?? '');
            if ($desde !== null && $hasta !== null) {
                if ($desde > $hasta) {
                    [$desde, $hasta] = [$hasta, $desde];
                }
                $query->whereBetween($columna, [$desde, $hasta]);

                return;
            }
            if ($desde !== null) {
                $query->where($columna, '>=', $desde);

                return;
            }
        }

        $id = self::numeroValido($valor);
        if ($id !== null) {
            $query->where($columna, $id);
        }
    }

    public static function metaTextoUsuarios(string $usuarios): string
    {
        $usuarios = trim($usuarios);
        if ($usuarios === '') {
            return 'Todos los usuarios';
        }

        if (self::esListaNumeros($usuarios)) {
            $cantidad = count(self::parseListaNumeros($usuarios));

            return $cantidad > 1 ? 'Lista de usuarios ('.$cantidad.')' : 'Lista de usuarios';
        }

        if (str_contains($usuarios, '/')) {
            $partes = array_map('trim', explode('/', $usuarios, 2));

            return 'Rango '.($partes[0] ?? '').' al '.($partes[1] ?? '');
        }

        return '';
    }

    public static function metaTextoCentrocostosCodigo(string $codigos): string
    {
        $codigos = trim($codigos);
        if ($codigos === '') {
            return 'Todos los centros de costo';
        }

        $lista = self::parseListaCodigos($codigos);
        if ($lista === []) {
            return 'Centro de costo filtrado';
        }

        if (count($lista) > 1) {
            return 'Lista CC ('.count($lista).'): '.implode(', ', $lista);
        }

        $cc = \App\Models\Contable\Centrocosto::query()
            ->where('codigo', $lista[0])
            ->first(['codigo', 'nombre']);

        if ($cc) {
            return trim((string) $cc->codigo).' — '.trim((string) $cc->nombre);
        }

        return 'Código '.$lista[0];
    }

    /** @deprecated Use metaTextoCentrocostosCodigo */
    public static function metaTextoCentrocostos(string $centrocostos): string
    {
        return self::metaTextoCentrocostosCodigo($centrocostos);
    }

    /** @param array<string, mixed> $filtros */
    public static function subtituloRequisiciones(array $filtros): ?string
    {
        $desde = trim((string) ($filtros['requisicion_desde'] ?? ''));
        $hasta = trim((string) ($filtros['requisicion_hasta'] ?? ''));

        if ($desde === '' && $hasta === '') {
            return null;
        }

        if (self::esListaNumeros($desde)) {
            return 'Requisiciones: '.$desde;
        }

        if (str_contains($desde, '/')) {
            return 'Requisiciones: '.$desde;
        }

        return 'Requisiciones: '.($desde !== '' ? $desde : '…').' — '.($hasta !== '' ? $hasta : '…');
    }

    /** @param array<string, mixed> $filtros */
    public static function subtituloUsuarios(array $filtros): ?string
    {
        $usuarios = trim((string) ($filtros['usuarios'] ?? ''));
        if ($usuarios === '') {
            return null;
        }

        return 'Usuarios: '.$usuarios;
    }

    /** @param array<string, mixed> $filtros */
    public static function subtituloCentrocostos(array $filtros): ?string
    {
        $centrocostos = trim((string) ($filtros['centrocostos_codigo'] ?? ''));
        if ($centrocostos === '') {
            return null;
        }

        return 'Centros de costo (cód.): '.$centrocostos;
    }
}
