<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\ApiAnita;

/**
 * Corrige venibr.veni_provincia grabado con jurisdicción AFIP (901, 915…)
 * al código zonamult que usa ibrxprov (1=CABA, 4=Neuquén…).
 */
final class VenibrZonamultCorreccionSupport
{
    /**
     * @return list<array{
     *   tipo: string,
     *   letra: string,
     *   sucursal: int,
     *   nro: int,
     *   provincia_actual: int,
     *   provincia_correcta: int,
     *   porcentaje: float,
     *   importe: float,
     *   fecha: string,
     *   cliente: string,
     *   nombre: string
     * }>
     */
    public static function listarIncorrectas(string $fechaDesdeYmd, string $fechaHastaYmd): array
    {
        $desde = (int) str_replace('-', '', $fechaDesdeYmd);
        $hasta = (int) str_replace('-', '', $fechaHastaYmd);
        if ($desde <= 0 || $hasta <= 0 || $desde > $hasta) {
            throw new \InvalidArgumentException('Rango de fechas inválido (Y-m-d).');
        }

        $api = new ApiAnita();
        $path = PedidoFacturaAnitaArchivosSupport::pathSistemaParaSucursal(10)
            ?? (string) config('anita.bdd_path', '');

        $payload = [
            'acc' => 'list',
            'sistema' => 'ventas',
            'tabla' => 'venibr,venta',
            'campos' => 'veni_tipo,veni_letra,veni_sucursal,veni_nro,veni_provincia,veni_porcentaje,veni_importe,ven_fecha,ven_cliente,ven_nombre_cliente',
            'whereArmado' => ' WHERE veni_tipo=ven_tipo AND veni_letra=ven_letra'
                .' AND veni_sucursal=ven_sucursal AND veni_nro=ven_nro'
                .' AND ven_fecha >= '.$desde
                .' AND ven_fecha <= '.$hasta
                .' AND veni_provincia >= 900'
                .' AND veni_importe <> 0',
        ];
        if ($path !== '') {
            $payload['path_sistema'] = $path;
        }

        $filas = ApiAnita::decodificarListaFilas($api->apiCall($payload));
        $out = [];
        foreach ($filas as $fila) {
            $r = (array) $fila;
            $actual = (int) ($r['veni_provincia'] ?? 0);
            if ($actual < 900) {
                continue;
            }
            $correcta = ClienteAnitaZonamultSupport::codigoDesdeJurisdiccion($actual);
            if ($correcta <= 0 || $correcta === $actual) {
                continue;
            }
            $out[] = [
                'tipo' => trim((string) ($r['veni_tipo'] ?? '')),
                'letra' => trim((string) ($r['veni_letra'] ?? '')),
                'sucursal' => (int) ($r['veni_sucursal'] ?? 0),
                'nro' => (int) ($r['veni_nro'] ?? 0),
                'provincia_actual' => $actual,
                'provincia_correcta' => $correcta,
                'porcentaje' => (float) ($r['veni_porcentaje'] ?? 0),
                'importe' => (float) ($r['veni_importe'] ?? 0),
                'fecha' => (string) ($r['ven_fecha'] ?? ''),
                'cliente' => trim((string) ($r['ven_cliente'] ?? '')),
                'nombre' => trim((string) ($r['ven_nombre_cliente'] ?? '')),
            ];
        }

        return $out;
    }

    /**
     * @param  array{
     *   tipo: string,
     *   letra: string,
     *   sucursal: int,
     *   nro: int,
     *   provincia_actual: int,
     *   provincia_correcta: int
     * }  $fila
     * @return array{ok: bool, mensaje: string, filas_afectadas?: int}
     */
    public static function corregirFila(array $fila, bool $dryRun = true): array
    {
        $tipo = $fila['tipo'];
        $letra = $fila['letra'];
        $sucursal = (int) $fila['sucursal'];
        $nro = (int) $fila['nro'];
        $desde = (int) $fila['provincia_actual'];
        $hasta = (int) $fila['provincia_correcta'];

        $where = " WHERE veni_tipo='{$tipo}' AND veni_letra='{$letra}'"
            ." AND veni_sucursal='{$sucursal}' AND veni_nro='{$nro}'"
            ." AND veni_provincia='{$desde}' ";

        $api = new ApiAnita();
        $path = PedidoFacturaAnitaArchivosSupport::pathSistemaParaSucursal($sucursal)
            ?? (string) config('anita.bdd_path', '');

        $countPayload = [
            'acc' => 'list',
            'sistema' => 'ventas',
            'tabla' => 'venibr',
            'campos' => 'veni_tipo,veni_letra,veni_sucursal,veni_nro,veni_provincia,veni_importe',
            'whereArmado' => $where,
        ];
        if ($path !== '') {
            $countPayload['path_sistema'] = $path;
        }
        $existentes = ApiAnita::decodificarListaFilas($api->apiCall($countPayload));
        $n = count($existentes);
        if ($n === 0) {
            return ['ok' => false, 'mensaje' => 'No hay fila venibr con esa clave/provincia'];
        }
        if ($n > 5) {
            return ['ok' => false, 'mensaje' => "COUNT={$n} (>5): no se actualiza sin revisión"];
        }

        $ref = sprintf('%s %s-%d-%d prov %d→%d', $tipo, $letra, $sucursal, $nro, $desde, $hasta);
        if ($dryRun) {
            return ['ok' => true, 'mensaje' => 'DRY-RUN '.$ref, 'filas_afectadas' => $n];
        }

        $update = [
            'acc' => 'update',
            'sistema' => 'ventas',
            'tabla' => 'venibr',
            'valores' => " veni_provincia='{$hasta}' ",
            'whereArmado' => $where,
        ];
        if ($path !== '') {
            $update['path_sistema'] = $path;
        }

        $raw = $api->apiCallEscritura($update, exigirFilasAfectadas: true);
        if (is_string($raw) && (str_starts_with(trim($raw), 'Error') || str_contains(strtolower($raw), '"error"'))) {
            return ['ok' => false, 'mensaje' => 'UPDATE falló: '.$raw];
        }

        return ['ok' => true, 'mensaje' => 'OK '.$ref, 'filas_afectadas' => $n];
    }
}
