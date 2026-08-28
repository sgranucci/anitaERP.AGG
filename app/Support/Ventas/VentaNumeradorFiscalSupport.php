<?php

namespace App\Support\Ventas;

use App\ApiAnita;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Venta_Serie_Numerador;
use Illuminate\Support\Facades\DB;
use App\Support\Ventas\PedidoFacturaAnitaArchivosSupport;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

/**
 * Numerador fiscal local: serie = codigo_afip (tipo ARCA) + puntoventa.
 * FAC A=001 y FAC B=006 son series distintas. La letra no es clave.
 *
 * Los facturadores no lo llaman hasta config facturacion.NUMERADOR_FISCAL_EN_USO.
 */
final class VentaNumeradorFiscalSupport
{
    public static function estaEnUso(): bool
    {
        return (bool) config('facturacion.NUMERADOR_FISCAL_EN_USO', false);
    }

    public static function proximoNumero(int $ultimoNumero, int $piso = 0): int
    {
        return max(0, $ultimoNumero, $piso) + 1;
    }

    public static function consultar(int $puntoventaId, int $codigoAfip): ?Venta_Serie_Numerador
    {
        if ($puntoventaId <= 0 || $codigoAfip <= 0) {
            return null;
        }

        return Venta_Serie_Numerador::query()
            ->where('puntoventa_id', $puntoventaId)
            ->where('codigo_afip', $codigoAfip)
            ->first();
    }

    /**
     * Reserva el siguiente número de la serie. No usar desde facturadores
     * mientras estaEnUso() sea false.
     */
    public static function reservarSiguiente(
        int $puntoventaId,
        int $codigoAfip,
        ?int $empresaId = null,
    ): int {
        if ($puntoventaId <= 0 || $codigoAfip <= 0) {
            throw new InvalidArgumentException('Serie fiscal inválida (PV y tipo ARCA son obligatorios).');
        }

        return (int) DB::transaction(function () use ($puntoventaId, $codigoAfip, $empresaId): int {
            $row = Venta_Serie_Numerador::query()
                ->where('puntoventa_id', $puntoventaId)
                ->where('codigo_afip', $codigoAfip)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                $row = Venta_Serie_Numerador::query()->create([
                    'empresa_id' => self::resolverEmpresaId($puntoventaId, $empresaId),
                    'puntoventa_id' => $puntoventaId,
                    'codigo_afip' => $codigoAfip,
                    'ultimo_numero' => 0,
                    'piso' => 0,
                ]);
                $row = Venta_Serie_Numerador::query()
                    ->whereKey($row->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $siguiente = self::proximoNumero((int) $row->ultimo_numero, (int) $row->piso);
            $row->ultimo_numero = $siguiente;
            if ($empresaId !== null && $empresaId > 0 && (int) $row->empresa_id !== $empresaId) {
                $row->empresa_id = $empresaId;
            }
            $row->save();

            return $siguiente;
        });
    }

    /**
     * Siembra desde Anita (venta del bridge) por tipo ARCA + sucursal.
     * El ERP es fallback: solo cubre series que Anita no trajo.
     * No baja un último ya mayor.
     *
     * @return array{creadas: int, actualizadas: int, sin_cambio: int, desde_anita: int, desde_erp: int, omitidas: int, aviso: ?string}
     */
    public static function sembrar(bool $usarFallbackErp = true): array
    {
        $omitidas = 0;
        $aviso = null;
        try {
            $anita = self::maximosDesdeAnita($omitidas);
        } catch (RuntimeException $e) {
            if (! $usarFallbackErp) {
                throw $e;
            }
            Log::warning('numerador_fiscal.sembrar_anita_fallo_usa_erp', ['error' => $e->getMessage()]);
            $anita = [];
            $aviso = 'Anita no respondió; se usó solo el máximo de ventas del ERP. '.$e->getMessage();
        }
        $erp = $usarFallbackErp ? self::maximosDesdeVentaErp() : [];
        $series = self::fusionarMaximosSemilla($anita, $erp, $usarFallbackErp);

        $r = self::persistirSeries($series, $omitidas);
        $r['aviso'] = $aviso;

        return $r;
    }

    /** @deprecated Usar sembrar(). Conservado por llamadas previas. */
    public static function sembrarDesdeVentas(): array
    {
        return self::sembrar(true);
    }

    /**
     * Anita manda; el ERP solo entra si falta la serie.
     *
     * @param  list<array{puntoventa_id: int, codigo_afip: int, max_nro: int}>  $anita
     * @param  list<array{puntoventa_id: int, codigo_afip: int, max_nro: int}>  $erp
     * @return list<array{puntoventa_id: int, codigo_afip: int, max_nro: int, origen: string}>
     */
    public static function fusionarMaximosSemilla(array $anita, array $erp, bool $usarFallbackErp): array
    {
        $out = [];
        foreach ($anita as $fila) {
            $clave = self::claveSerie((int) $fila['puntoventa_id'], (int) $fila['codigo_afip']);
            if ($clave === '') {
                continue;
            }
            $nro = (int) $fila['max_nro'];
            if (! isset($out[$clave]) || $nro > $out[$clave]['max_nro']) {
                $out[$clave] = [
                    'puntoventa_id' => (int) $fila['puntoventa_id'],
                    'codigo_afip' => (int) $fila['codigo_afip'],
                    'max_nro' => $nro,
                    'origen' => 'anita',
                ];
            }
        }

        if ($usarFallbackErp) {
            foreach ($erp as $fila) {
                $clave = self::claveSerie((int) $fila['puntoventa_id'], (int) $fila['codigo_afip']);
                if ($clave === '' || isset($out[$clave])) {
                    continue;
                }
                $out[$clave] = [
                    'puntoventa_id' => (int) $fila['puntoventa_id'],
                    'codigo_afip' => (int) $fila['codigo_afip'],
                    'max_nro' => (int) $fila['max_nro'],
                    'origen' => 'erp',
                ];
            }
        }

        return array_values($out);
    }

    /**
     * @return list<array{puntoventa_id: int, codigo_afip: int, max_nro: int}>
     */
    private static function maximosDesdeAnita(int &$omitidas): array
    {
        $pvs = Puntoventa::query()->get(['id', 'codigo', 'empresa_id']);
        $divisionIds = PedidoFacturaAnitaArchivosSupport::idsPuntoVentaDivision();
        $filas = self::filasAnitaASeries(
            self::consultarMaximosAnita(null),
            $pvs,
            $divisionIds,
            excluirDivision: $divisionIds !== [],
            omitidas: $omitidas,
        );
        if ($divisionIds === []) {
            return $filas;
        }

        try {
            $filas = array_merge(
                $filas,
                self::filasAnitaASeries(
                    self::consultarMaximosAnita(PedidoFacturaAnitaArchivosSupport::PATH_VILLAFRANCA),
                    $pvs,
                    $divisionIds,
                    excluirDivision: false,
                    omitidas: $omitidas,
                    soloDivision: true,
                ),
            );
        } catch (RuntimeException $e) {
            Log::warning('numerador_fiscal.sembrar_anita_villafranca', ['error' => $e->getMessage()]);
        }

        return $filas;
    }

    /**
     * @return list<array{ven_sucursal: string, ven_tipo: string, ven_letra: string, max_nro: int}>
     */
    private static function consultarMaximosAnita(?string $pathSistema): array
    {
        $api = new ApiAnita;
        $data = [
            'acc' => 'list',
            'tabla' => 'venta',
            'sistema' => 'ventas',
            'campos' => 'ven_sucursal,ven_tipo,ven_letra,max(ven_nro) as max_nro',
            'whereArmado' => ' WHERE ven_nro > 0',
            'groupBy' => 'ven_sucursal,ven_tipo,ven_letra',
        ];
        if ($pathSistema !== null && $pathSistema !== '') {
            $data['path_sistema'] = $pathSistema;
        }

        $parseado = ApiAnita::parsearRespuestaLista($api->apiCall($data));
        if ($parseado['error_lectura'] !== null) {
            throw new RuntimeException(
                'No se pudo leer venta en Anita'.($pathSistema ? ' ('.$pathSistema.')' : '').': '.$parseado['error_lectura']
            );
        }

        $out = [];
        foreach ($parseado['filas'] as $fila) {
            $out[] = [
                'ven_sucursal' => trim((string) ($fila->ven_sucursal ?? '')),
                'ven_tipo' => trim((string) ($fila->ven_tipo ?? '')),
                'ven_letra' => trim((string) ($fila->ven_letra ?? '')),
                'max_nro' => (int) ($fila->max_nro ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{ven_sucursal: string, ven_tipo: string, ven_letra: string, max_nro: int}>  $filas
     * @param  \Illuminate\Support\Collection<int, Puntoventa>  $pvs
     * @param  list<int>  $divisionIds
     * @return list<array{puntoventa_id: int, codigo_afip: int, max_nro: int}>
     */
    private static function filasAnitaASeries(
        array $filas,
        $pvs,
        array $divisionIds,
        bool $excluirDivision,
        int &$omitidas,
        bool $soloDivision = false,
    ): array {
        $porSucursal = [];
        foreach ($pvs as $pv) {
            foreach (self::clavesSucursal((string) $pv->codigo) as $clave) {
                $porSucursal[$clave][] = $pv;
            }
        }

        $out = [];
        foreach ($filas as $fila) {
            $afip = TipotransaccionCodigoAfipSupport::codigoAfipDesdeAnitaTipoLetra(
                $fila['ven_tipo'],
                $fila['ven_letra'],
            );
            $maxNro = (int) $fila['max_nro'];
            if ($afip <= 0 || $maxNro <= 0) {
                if ($maxNro > 0) {
                    $omitidas++;
                }
                continue;
            }

            $candidatos = [];
            foreach (self::clavesSucursal($fila['ven_sucursal']) as $clave) {
                foreach ($porSucursal[$clave] ?? [] as $pv) {
                    $candidatos[(int) $pv->id] = $pv;
                }
            }
            if ($candidatos === []) {
                $omitidas++;
                continue;
            }

            foreach ($candidatos as $pv) {
                $pvId = (int) $pv->id;
                $esDivision = in_array($pvId, $divisionIds, true);
                if ($excluirDivision && $esDivision) {
                    continue;
                }
                if ($soloDivision && ! $esDivision) {
                    continue;
                }
                $out[] = [
                    'puntoventa_id' => $pvId,
                    'codigo_afip' => $afip,
                    'max_nro' => $maxNro,
                ];
            }
        }

        return $out;
    }

    /**
     * @return list<array{puntoventa_id: int, codigo_afip: int, max_nro: int}>
     */
    private static function maximosDesdeVentaErp(): array
    {
        $series = Venta::query()
            ->select([
                'venta.puntoventa_id',
                'venta.codigo_afip',
                DB::raw('MAX(venta.numerocomprobante) as max_nro'),
            ])
            ->whereNotNull('venta.codigo_afip')
            ->where('venta.codigo_afip', '>', 0)
            ->where('venta.puntoventa_id', '>', 0)
            ->groupBy('venta.puntoventa_id', 'venta.codigo_afip')
            ->get();

        $out = [];
        foreach ($series as $serie) {
            $pvId = (int) $serie->puntoventa_id;
            $afip = (int) $serie->codigo_afip;
            $maxNro = (int) $serie->max_nro;
            if ($pvId <= 0 || $afip <= 0 || $maxNro <= 0) {
                continue;
            }
            $out[] = [
                'puntoventa_id' => $pvId,
                'codigo_afip' => $afip,
                'max_nro' => $maxNro,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{puntoventa_id: int, codigo_afip: int, max_nro: int, origen: string}>  $series
     * @return array{creadas: int, actualizadas: int, sin_cambio: int, desde_anita: int, desde_erp: int, omitidas: int}
     */
    private static function persistirSeries(array $series, int $omitidas): array
    {
        $creadas = 0;
        $actualizadas = 0;
        $sinCambio = 0;
        $desdeAnita = 0;
        $desdeErp = 0;

        foreach ($series as $serie) {
            $pvId = (int) $serie['puntoventa_id'];
            $afip = (int) $serie['codigo_afip'];
            $maxNro = (int) $serie['max_nro'];
            $origen = (string) ($serie['origen'] ?? 'anita');
            if ($pvId <= 0 || $afip <= 0 || $maxNro <= 0) {
                continue;
            }

            if ($origen === 'erp') {
                $desdeErp++;
            } else {
                $desdeAnita++;
            }

            $row = Venta_Serie_Numerador::query()
                ->where('puntoventa_id', $pvId)
                ->where('codigo_afip', $afip)
                ->first();

            $obs = $origen === 'erp'
                ? 'Sembrado desde venta ERP (fallback)'
                : 'Sembrado desde Anita';

            if ($row === null) {
                Venta_Serie_Numerador::query()->create([
                    'empresa_id' => self::resolverEmpresaId($pvId, null),
                    'puntoventa_id' => $pvId,
                    'codigo_afip' => $afip,
                    'ultimo_numero' => $maxNro,
                    'piso' => 0,
                    'observacion' => $obs,
                ]);
                $creadas++;
                continue;
            }

            if ((int) $row->ultimo_numero < $maxNro) {
                $row->ultimo_numero = $maxNro;
                $row->observacion = $obs;
                $row->save();
                $actualizadas++;
                continue;
            }

            $sinCambio++;
        }

        return [
            'creadas' => $creadas,
            'actualizadas' => $actualizadas,
            'sin_cambio' => $sinCambio,
            'desde_anita' => $desdeAnita,
            'desde_erp' => $desdeErp,
            'omitidas' => $omitidas,
        ];
    }

    private static function claveSerie(int $puntoventaId, int $codigoAfip): string
    {
        if ($puntoventaId <= 0 || $codigoAfip <= 0) {
            return '';
        }

        return $puntoventaId.'|'.$codigoAfip;
    }

    /**
     * @return list<string>
     */
    private static function clavesSucursal(string $codigo): array
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return [];
        }

        $claves = [$codigo];
        if (ctype_digit($codigo)) {
            $sinCeros = ltrim($codigo, '0');
            if ($sinCeros === '') {
                $sinCeros = '0';
            }
            $claves[] = $sinCeros;
            $claves[] = str_pad($sinCeros, 5, '0', STR_PAD_LEFT);
        }

        return array_values(array_unique($claves));
    }

    private static function resolverEmpresaId(int $puntoventaId, ?int $empresaId): ?int
    {
        if ($empresaId !== null && $empresaId > 0) {
            return $empresaId;
        }

        $desdePv = (int) (Puntoventa::query()->whereKey($puntoventaId)->value('empresa_id') ?? 0);

        return $desdePv > 0 ? $desdePv : null;
    }
}
