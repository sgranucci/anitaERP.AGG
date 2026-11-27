<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\ApiAnita;
use App\Models\Configuracion\Empresa;
use App\Models\Contable\Asiento;

/**
 * Verifica (solo lectura) si el asiento ERP de una venta está en `ctamov` de Anita.
 *
 * Un asiento nacido en el ERP (`ctav_asi_mon_ref = -1`) vive en ctamov con la clave del comprobante
 * (`ctav_tipo/letra/sucursal/nro`). Una réplica de venta (`replicarVentaErpEnAnita`) no lo graba:
 * lo hace `FacturacionService::sincronizarCtamovAnitaDeVenta`.
 *
 * Estados:
 * - ok: líneas presentes, clave coincide y misma cantidad de líneas que el ERP.
 * - faltante: no hay filas con ese número de asiento. Se puede grabar.
 * - clave_incompleta: hay filas pero con tipo/letra/número en blanco. Se puede reparar.
 * - clave_distinta: hay filas con la clave de otro comprobante. No se toca.
 * - lineas_distintas: clave correcta pero otra cantidad de líneas. No se toca.
 * - sin_asiento_erp: la venta no tiene asiento en el ERP. Nada para grabar.
 * - asiento_nativo_anita: el asiento viene de subdiario/subhist. No va a ctamov (regla anita-subdiario-asiento-erp).
 * - error_lectura: el bridge no respondió. No se escribe.
 */
final class VentaCtamovAnitaVerificadorSupport
{
    public const OK = 'ok';

    public const FALTANTE = 'faltante';

    public const CLAVE_INCOMPLETA = 'clave_incompleta';

    public const CLAVE_DISTINTA = 'clave_distinta';

    public const LINEAS_DISTINTAS = 'lineas_distintas';

    public const SIN_ASIENTO = 'sin_asiento_erp';

    public const NATIVO_ANITA = 'asiento_nativo_anita';

    public const ERROR_LECTURA = 'error_lectura';

    private const CHUNK_ASIENTOS = 60;

    private const REINTENTOS_LECTURA = 3;

    /**
     * Estados en los que el comando puede grabar el asiento sin pisar nada ajeno.
     *
     * @return list<string>
     */
    public static function estadosReparables(): array
    {
        return [self::FALTANTE, self::CLAVE_INCOMPLETA];
    }

    /**
     * @param  list<array{venta_id:int,tipo:string,letra:string,sucursal:int,nro:int}>  $items
     * @return array<int, array{estado:string,asiento_id:?int,numeroasiento:?string,lineas_anita:int,lineas_erp:int,mensaje:string}>
     */
    public static function evaluar(array $items): array
    {
        $resultado = [];
        if ($items === []) {
            return $resultado;
        }

        $ventaIds = array_values(array_unique(array_map(static fn (array $i): int => (int) $i['venta_id'], $items)));

        $asientos = Asiento::query()
            ->whereIn('venta_id', $ventaIds)
            ->withCount(['asiento_movimientos as lineas_erp' => static fn ($q) => $q->where('monto', '<>', 0)])
            ->orderByDesc('id')
            ->get()
            ->unique('venta_id')
            ->keyBy('venta_id');

        $empresaCodigos = [];
        /** @var array<string, array{codigo:string,path:?string,items:list<array<string,mixed>>}> $grupos */
        $grupos = [];

        foreach ($items as $item) {
            $ventaId = (int) $item['venta_id'];
            /** @var Asiento|null $asiento */
            $asiento = $asientos->get($ventaId);

            if ($asiento === null) {
                $resultado[$ventaId] = self::fila(self::SIN_ASIENTO, null, null, 0, 0, 'La venta no tiene asiento en el ERP.');

                continue;
            }

            $origen = strtolower(trim((string) ($asiento->anita_origen ?? '')));
            if ($origen === 'subdiario' || $origen === 'subhist') {
                $resultado[$ventaId] = self::fila(
                    self::NATIVO_ANITA,
                    (int) $asiento->id,
                    (string) $asiento->numeroasiento,
                    0,
                    (int) ($asiento->lineas_erp ?? 0),
                    'Asiento tomado de '.$origen.': no se graba en ctamov.'
                );

                continue;
            }

            $numero = trim((string) $asiento->numeroasiento);
            if ($numero === '' || ! ctype_digit($numero)) {
                $resultado[$ventaId] = self::fila(
                    self::SIN_ASIENTO,
                    (int) $asiento->id,
                    $numero !== '' ? $numero : null,
                    0,
                    (int) ($asiento->lineas_erp ?? 0),
                    'El asiento ERP no tiene número válido.'
                );

                continue;
            }

            $empresaId = (int) $asiento->empresa_id;
            if (! array_key_exists($empresaId, $empresaCodigos)) {
                $empresa = Empresa::query()->find($empresaId);
                $empresaCodigos[$empresaId] = $empresa ? (string) $empresa->codigo : '1';
            }

            $path = PedidoFacturaAnitaArchivosSupport::pathSistemaParaSucursal((int) $item['sucursal']);
            $claveGrupo = $empresaCodigos[$empresaId].'|'.($path ?? '');
            $grupos[$claveGrupo] ??= ['codigo' => $empresaCodigos[$empresaId], 'path' => $path, 'items' => []];
            $grupos[$claveGrupo]['items'][] = [
                'venta_id' => $ventaId,
                'asiento_id' => (int) $asiento->id,
                'numeroasiento' => $numero,
                'lineas_erp' => (int) ($asiento->lineas_erp ?? 0),
                'tipo' => strtoupper(trim((string) $item['tipo'])),
                'letra' => strtoupper(trim((string) $item['letra'])),
                'sucursal' => (int) $item['sucursal'],
                'nro' => (int) $item['nro'],
            ];
        }

        foreach ($grupos as $grupo) {
            foreach (array_chunk($grupo['items'], self::CHUNK_ASIENTOS) as $chunk) {
                $numeros = array_map(static fn (array $c): string => (string) $c['numeroasiento'], $chunk);
                $leido = self::leerCtamov($grupo['codigo'], $grupo['path'], $numeros);

                if ($leido['error'] !== null) {
                    foreach ($chunk as $c) {
                        $resultado[(int) $c['venta_id']] = self::fila(
                            self::ERROR_LECTURA,
                            (int) $c['asiento_id'],
                            (string) $c['numeroasiento'],
                            0,
                            (int) $c['lineas_erp'],
                            'No se pudo leer ctamov: '.$leido['error']
                        );
                    }

                    continue;
                }

                // Los números que no aparecen se vuelven a leer una vez: una lectura vacía no alcanza
                // para decidir que falta (el bridge a veces devuelve vacío bajo carga).
                $ausentes = array_values(array_filter(
                    $numeros,
                    static fn (string $n): bool => ! isset($leido['filas'][$n])
                ));
                if ($ausentes !== []) {
                    $segunda = self::leerCtamov($grupo['codigo'], $grupo['path'], $ausentes);
                    if ($segunda['error'] !== null) {
                        foreach ($chunk as $c) {
                            if (in_array((string) $c['numeroasiento'], $ausentes, true)) {
                                $resultado[(int) $c['venta_id']] = self::fila(
                                    self::ERROR_LECTURA,
                                    (int) $c['asiento_id'],
                                    (string) $c['numeroasiento'],
                                    0,
                                    (int) $c['lineas_erp'],
                                    'No se pudo confirmar la ausencia en ctamov: '.$segunda['error']
                                );
                            }
                        }
                    }
                    foreach ($segunda['filas'] as $n => $filas) {
                        $leido['filas'][$n] = $filas;
                    }
                }

                foreach ($chunk as $c) {
                    $ventaId = (int) $c['venta_id'];
                    if (isset($resultado[$ventaId])) {
                        continue;
                    }
                    $resultado[$ventaId] = self::clasificar($c, $leido['filas'][(string) $c['numeroasiento']] ?? []);
                }
            }
        }

        return $resultado;
    }

    /**
     * @param  array<string,mixed>  $c
     * @param  list<array<string,mixed>>  $filas
     * @return array{estado:string,asiento_id:?int,numeroasiento:?string,lineas_anita:int,lineas_erp:int,mensaje:string}
     */
    private static function clasificar(array $c, array $filas): array
    {
        $asientoId = (int) $c['asiento_id'];
        $numero = (string) $c['numeroasiento'];
        $lineasErp = (int) $c['lineas_erp'];
        $lineasAnita = count($filas);

        if ($lineasAnita === 0) {
            return self::fila(self::FALTANTE, $asientoId, $numero, 0, $lineasErp, 'Sin líneas en ctamov.');
        }

        $hayDistinta = false;
        $hayBlanca = false;
        foreach ($filas as $f) {
            $tipo = strtoupper(trim((string) ($f['ctav_tipo'] ?? '')));
            $letra = strtoupper(trim((string) ($f['ctav_letra'] ?? '')));
            $sucursal = (int) trim((string) ($f['ctav_sucursal'] ?? '0'));
            $nro = (int) trim((string) ($f['ctav_nro'] ?? '0'));

            if ($tipo === '' && $letra === '' && $sucursal === 0 && $nro === 0) {
                $hayBlanca = true;

                continue;
            }

            if ($tipo !== $c['tipo'] || $letra !== $c['letra'] || $sucursal !== (int) $c['sucursal'] || $nro !== (int) $c['nro']) {
                $hayDistinta = true;
            }
        }

        if ($hayDistinta) {
            return self::fila(
                self::CLAVE_DISTINTA,
                $asientoId,
                $numero,
                $lineasAnita,
                $lineasErp,
                'El asiento '.$numero.' en ctamov pertenece a otro comprobante.'
            );
        }

        if ($hayBlanca) {
            return self::fila(
                self::CLAVE_INCOMPLETA,
                $asientoId,
                $numero,
                $lineasAnita,
                $lineasErp,
                'Líneas sin tipo/letra/sucursal/número del comprobante.'
            );
        }

        if ($lineasErp > 0 && $lineasAnita !== $lineasErp) {
            return self::fila(
                self::LINEAS_DISTINTAS,
                $asientoId,
                $numero,
                $lineasAnita,
                $lineasErp,
                'ctamov tiene '.$lineasAnita.' líneas y el ERP '.$lineasErp.'.'
            );
        }

        return self::fila(self::OK, $asientoId, $numero, $lineasAnita, $lineasErp, '');
    }

    /**
     * @param  list<string>  $numeros
     * @return array{error:?string,filas:array<string,list<array<string,mixed>>>}
     */
    private static function leerCtamov(string $codigoEmpresa, ?string $path, array $numeros): array
    {
        $enteros = array_map(static fn (string $n): int => (int) $n, $numeros);
        $data = [
            'acc' => 'list',
            'tabla' => 'ctamov',
            'sistema' => 'contab',
            'campos' => 'ctav_nro_asiento,ctav_tipo,ctav_letra,ctav_sucursal,ctav_nro',
            'whereArmado' => " WHERE ctav_empresa = '".str_replace("'", "''", $codigoEmpresa)."'"
                .' AND ctav_nro_asiento IN ('.implode(',', $enteros).') ',
        ];
        if ($path !== null) {
            $data['path_sistema'] = $path;
        }

        $api = new ApiAnita();
        $ultimoError = null;
        for ($intento = 1; $intento <= self::REINTENTOS_LECTURA; $intento++) {
            $parsed = ApiAnita::parsearRespuestaLista((string) $api->apiCall($data));
            if ($parsed['error_lectura'] === null) {
                $porAsiento = [];
                foreach ($parsed['filas'] as $fila) {
                    $fila = (array) $fila;
                    $porAsiento[(string) (int) trim((string) ($fila['ctav_nro_asiento'] ?? '0'))][] = $fila;
                }

                return ['error' => null, 'filas' => $porAsiento];
            }
            $ultimoError = (string) $parsed['error_lectura'];
        }

        return ['error' => $ultimoError ?? 'sin respuesta', 'filas' => []];
    }

    /**
     * @return array{estado:string,asiento_id:?int,numeroasiento:?string,lineas_anita:int,lineas_erp:int,mensaje:string}
     */
    private static function fila(string $estado, ?int $asientoId, ?string $numero, int $lineasAnita, int $lineasErp, string $mensaje): array
    {
        return [
            'estado' => $estado,
            'asiento_id' => $asientoId,
            'numeroasiento' => $numero,
            'lineas_anita' => $lineasAnita,
            'lineas_erp' => $lineasErp,
            'mensaje' => $mensaje,
        ];
    }
}
