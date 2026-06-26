<?php

namespace App\Support\Stock;

use App\Models\Stock\Depmae;
use Illuminate\Support\Collection;

/**
 * Filtro de depósitos por código (depmae.codigo): lista con comas o rango con barra.
 */
final class ExistenciasDepositoFiltroDepositosSupport
{
  /**
   * @param  Collection<int, Depmae>  $depositos
   * @return Collection<int, Depmae>
   */
  public static function filtrarColeccion(Collection $depositos, string $filtro): Collection
  {
    $filtro = trim($filtro);
    if ($filtro === '' || $depositos->isEmpty()) {
      return $depositos;
    }

    if (self::esListaCodigos($filtro)) {
      $codigos = self::parseListaCodigos($filtro);
      if ($codigos === []) {
        return collect();
      }

      return $depositos->filter(fn (Depmae $dep) => self::codigoEnLista((string) $dep->codigo, $codigos))->values();
    }

    if (str_contains($filtro, '/')) {
      $partes = array_map('trim', explode('/', $filtro, 2));
      $desde = self::codigoNumerico($partes[0] ?? '');
      $hasta = self::codigoNumerico($partes[1] ?? '');

      if ($desde === null && $hasta === null) {
        return collect();
      }

      return $depositos->filter(fn (Depmae $dep) => self::codigoEnRango((string) $dep->codigo, $desde, $hasta))->values();
    }

    return $depositos->filter(fn (Depmae $dep) => self::codigoCoincide((string) $dep->codigo, $filtro))->values();
  }

  public static function esListaCodigos(string $valor): bool
  {
    return str_contains($valor, ',') || str_contains($valor, ';');
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

  public static function codigoNumerico(?string $valor): ?int
  {
    $valor = trim((string) $valor);
    if ($valor === '' || ! ctype_digit($valor)) {
      return null;
    }

    $entero = (int) $valor;

    return $entero >= 0 ? $entero : null;
  }

  public static function metaTexto(string $filtro): string
  {
    $filtro = trim($filtro);
    if ($filtro === '') {
      return '';
    }

    if (self::esListaCodigos($filtro)) {
      return 'Depósitos: lista '.$filtro;
    }

    if (str_contains($filtro, '/')) {
      return 'Depósitos: rango '.$filtro;
    }

    return 'Depósitos: '.$filtro;
  }

  private static function codigoCoincide(string $codigoDeposito, string $filtroCodigo): bool
  {
    $a = trim($codigoDeposito);
    $b = trim($filtroCodigo);

    if ($a === $b) {
      return true;
    }

    $na = self::codigoNumerico($a);
    $nb = self::codigoNumerico($b);

    return $na !== null && $nb !== null && $na === $nb;
  }

  /**
   * @param  list<string>  $codigos
   */
  private static function codigoEnLista(string $codigoDeposito, array $codigos): bool
  {
    foreach ($codigos as $codigo) {
      if (self::codigoCoincide($codigoDeposito, $codigo)) {
        return true;
      }
    }

    return false;
  }

  private static function codigoEnRango(string $codigoDeposito, ?int $desde, ?int $hasta): bool
  {
    $n = self::codigoNumerico($codigoDeposito);
    if ($n === null) {
      return false;
    }

    if ($desde !== null && $hasta !== null) {
      if ($desde > $hasta) {
        [$desde, $hasta] = [$hasta, $desde];
      }

      return $n >= $desde && $n <= $hasta;
    }

    if ($desde !== null) {
      return $n >= $desde;
    }

    if ($hasta !== null) {
      return $n <= $hasta;
    }

    return false;
  }
}
