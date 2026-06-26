<?php

namespace App\Support\Stock;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ExistenciasDepositoListadoFiltros
{
  public const ID_PRIMERO = 0;

  public const ID_ULTIMO = 99999999;

  /**
   * @return array{
   *     empresa_id: ?int,
   *     fecha_desde: ?string,
   *     fecha_hasta: ?string,
   *     desdearticulo_id: ?int,
   *     hastaarticulo_id: ?int,
   *     desdecategoria_id: ?int,
   *     hastacategoria_id: ?int,
   *     desdeusoarticulo_id: ?int,
   *     hastausoarticulo_id: ?int,
   *     desdetipoarticulo_id: ?int,
   *     hastatipoarticulo_id: ?int,
   *     depositos_filtro: string,
   *     solo_con_saldo: bool
   * }
   */
  public static function resolverDesdeRequest(Request $request): array
  {
    $filtros = [
      'empresa_id' => self::enteroOpcional($request->input('empresa_id')),
      'fecha_desde' => self::fechaOpcional($request->input('fecha_desde')),
      'fecha_hasta' => self::fechaOpcional($request->input('fecha_hasta')),
      'desdearticulo_id' => self::enteroRangoOpcional($request->input('desdearticulo_id')),
      'hastaarticulo_id' => self::enteroRangoOpcional($request->input('hastaarticulo_id')),
      'desdecategoria_id' => self::enteroRangoOpcional($request->input('desdecategoria_id')),
      'hastacategoria_id' => self::enteroRangoOpcional($request->input('hastacategoria_id')),
      'desdeusoarticulo_id' => self::enteroRangoOpcional($request->input('desdeusoarticulo_id')),
      'hastausoarticulo_id' => self::enteroRangoOpcional($request->input('hastausoarticulo_id')),
      'desdetipoarticulo_id' => self::enteroRangoOpcional($request->input('desdetipoarticulo_id')),
      'hastatipoarticulo_id' => self::enteroRangoOpcional($request->input('hastatipoarticulo_id')),
      'depositos_filtro' => self::textoOpcional($request->input('depositos_filtro')),
      'solo_con_saldo' => $request->input('solo_con_saldo', '1') !== '0',
    ];

    if ($request->boolean('consultar')) {
      self::aplicarFechasPorDefecto($filtros);
    }

    return $filtros;
  }

  /** @return array<string, mixed> */
  public static function paraQueryString(array $filtros): array
  {
    return array_filter([
      'empresa_id' => $filtros['empresa_id'] ?? null,
      'fecha_desde' => $filtros['fecha_desde'] ?? null,
      'fecha_hasta' => $filtros['fecha_hasta'] ?? null,
      'desdearticulo_id' => $filtros['desdearticulo_id'] ?? null,
      'hastaarticulo_id' => $filtros['hastaarticulo_id'] ?? null,
      'desdecategoria_id' => $filtros['desdecategoria_id'] ?? null,
      'hastacategoria_id' => $filtros['hastacategoria_id'] ?? null,
      'desdeusoarticulo_id' => $filtros['desdeusoarticulo_id'] ?? null,
      'hastausoarticulo_id' => $filtros['hastausoarticulo_id'] ?? null,
      'desdetipoarticulo_id' => $filtros['desdetipoarticulo_id'] ?? null,
      'hastatipoarticulo_id' => $filtros['hastatipoarticulo_id'] ?? null,
      'depositos_filtro' => $filtros['depositos_filtro'] ?? null,
      'solo_con_saldo' => ($filtros['solo_con_saldo'] ?? true) ? '1' : '0',
      'consultar' => 1,
    ], fn ($v) => $v !== null && $v !== '');
  }

  public static function tieneCriteriosAplicados(array $filtros): bool
  {
    return ! empty($filtros['empresa_id'])
      || ! empty($filtros['fecha_desde'])
      || ! empty($filtros['fecha_hasta'])
      || isset($filtros['desdearticulo_id'])
      || isset($filtros['hastaarticulo_id'])
      || isset($filtros['desdecategoria_id'])
      || isset($filtros['hastacategoria_id'])
      || isset($filtros['desdeusoarticulo_id'])
      || isset($filtros['hastausoarticulo_id'])
      || isset($filtros['desdetipoarticulo_id'])
      || isset($filtros['hastatipoarticulo_id'])
      || trim((string) ($filtros['depositos_filtro'] ?? '')) !== ''
      || ! ($filtros['solo_con_saldo'] ?? true);
  }

  /** @param  array<string, mixed>  $filtros */
  public static function aplicarFechasPorDefecto(array &$filtros): void
  {
    if (empty($filtros['fecha_hasta'])) {
      $filtros['fecha_hasta'] = date('Y-m-d');
    }

    if (empty($filtros['fecha_desde'])) {
      $min = DB::table('articulo_movimiento')
        ->whereNull('deleted_at')
        ->min('fecha');

      $filtros['fecha_desde'] = $min ? substr((string) $min, 0, 10) : $filtros['fecha_hasta'];
    }
  }

  private static function enteroOpcional($valor): ?int
  {
    if ($valor === null || $valor === '') {
      return null;
    }

    $entero = (int) $valor;

    return $entero > 0 ? $entero : null;
  }

  private static function enteroRangoOpcional($valor): ?int
  {
    if ($valor === null || $valor === '') {
      return null;
    }

    return (int) $valor;
  }

  private static function fechaOpcional($valor): ?string
  {
    $valor = trim((string) $valor);

    return $valor !== '' ? substr($valor, 0, 10) : null;
  }

  private static function textoOpcional($valor): string
  {
    return trim((string) $valor);
  }
}
