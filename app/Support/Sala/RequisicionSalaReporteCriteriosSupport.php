<?php

namespace App\Support\Sala;

use Illuminate\Database\Query\Builder;

final class RequisicionSalaReporteCriteriosSupport
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
        string $columna = 'rs.numerorequisicion',
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
        string $columna = 'rs.usuario_id',
    ): void {
        $usuarios = trim($usuarios);
        if ($usuarios === '') {
            return;
        }

        if (self::esListaNumeros($usuarios)) {
            $ids = self::parseListaNumeros($usuarios);
            if ($ids !== []) {
                $query->whereIn($columna, $ids);
            }

            return;
        }

        if (str_contains($usuarios, '/')) {
            $partes = array_map('trim', explode('/', $usuarios, 2));
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

        $id = self::numeroValido($usuarios);
        if ($id !== null) {
            $query->where($columna, $id);
        }
    }

    public static function metaTextoCriterioNumeros(string $desde, string $hasta): string
    {
        [$desde, $hasta] = self::normalizarRangoNumeros($desde, $hasta);

        if ($desde === '' && $hasta === '') {
            return 'Todas';
        }

        if (self::esListaNumeros($desde)) {
            return 'Lista';
        }

        if (str_contains(trim($desde), '/') || ($desde !== '' && $hasta !== '')) {
            return 'Rango '.($desde !== '' ? $desde : '…').' al '.($hasta !== '' ? $hasta : '…');
        }

        return '';
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
}
