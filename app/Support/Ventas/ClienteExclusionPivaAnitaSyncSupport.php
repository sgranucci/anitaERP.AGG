<?php

namespace App\Support\Ventas;

use App\ApiAnita;
use App\Models\Ventas\Cliente;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Database\SqlDialectSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Alinea exclusión percepción IVA (solapa → columnas legacy → Anita clim_dfexcl/hfexcl_piva).
 * Solo El Bierzo (campos Anita de Bierzo).
 */
final class ClienteExclusionPivaAnitaSyncSupport
{
    /**
     * @return array{
     *     filas: list<array<string, mixed>>,
     *     a_alinear_legacy: int,
     *     a_actualizar_anita: int,
     *     ya_ok: int,
     *     sin_anita: int,
     *     total: int
     * }
     */
    public static function analizar(?string $codigoFiltro = null): array
    {
        self::assertEntornoBierzo();

        ClienteExclusionPercepcionSupport::resetCache();

        $clientes = self::clientesConExclusionIva($codigoFiltro);
        $apiAnita = new ApiAnita();
        $filas = [];
        $aAlinearLegacy = 0;
        $aActualizarAnita = 0;
        $yaOk = 0;
        $sinAnita = 0;

        foreach ($clientes as $cliente) {
            $clienteId = (int) $cliente->id;
            [$desdeDeseado, $hastaDeseado] = ClienteExclusionPercepcionSupport::fechasIvaParaCabeceraAnita($clienteId);
            $desdeAnitaFmt = self::fechaAYmd($desdeDeseado);
            $hastaAnitaFmt = self::fechaAYmd($hastaDeseado);

            $legacyDesde = self::normalizarFechaErp($cliente->desdefecha_exclusionpercepcioniva);
            $legacyHasta = self::normalizarFechaErp($cliente->hastafecha_exclusionpercepcioniva);
            $cambiaLegacy = $legacyDesde !== ($desdeDeseado ?? '') || $legacyHasta !== ($hastaDeseado ?? '');

            $anita = self::leerFechasAnita($apiAnita, (string) $cliente->codigo);
            $existeAnita = $anita['existe'];
            $anitaDesde = $anita['desde'];
            $anitaHasta = $anita['hasta'];
            $cambiaAnita = $existeAnita
                && ($anitaDesde !== $desdeAnitaFmt || $anitaHasta !== $hastaAnitaFmt);

            if (! $existeAnita) {
                $sinAnita++;
            }
            if ($cambiaLegacy) {
                $aAlinearLegacy++;
            }
            if ($cambiaAnita) {
                $aActualizarAnita++;
            }
            if (! $cambiaLegacy && ! $cambiaAnita && $existeAnita) {
                $yaOk++;
            }

            if (! $cambiaLegacy && ! $cambiaAnita && $existeAnita) {
                continue;
            }

            $filas[] = [
                'cliente_id' => $clienteId,
                'codigo' => (string) $cliente->codigo,
                'nombre' => (string) ($cliente->nombre ?? ''),
                'solapa_desde' => $desdeDeseado ?? '',
                'solapa_hasta' => $hastaDeseado ?? '',
                'legacy_desde' => $legacyDesde,
                'legacy_hasta' => $legacyHasta,
                'anita_desde' => $anitaDesde,
                'anita_hasta' => $anitaHasta,
                'anita_existe' => $existeAnita,
                'cambia_legacy' => $cambiaLegacy,
                'cambia_anita' => $cambiaAnita,
                'anita_desde_ymd' => $desdeAnitaFmt,
                'anita_hasta_ymd' => $hastaAnitaFmt,
            ];
        }

        return [
            'filas' => $filas,
            'a_alinear_legacy' => $aAlinearLegacy,
            'a_actualizar_anita' => $aActualizarAnita,
            'ya_ok' => $yaOk,
            'sin_anita' => $sinAnita,
            'total' => count($clientes),
        ];
    }

    /**
     * @return array{legacy: int, anita: int, errores: list<string>}
     */
    public static function aplicar(?string $codigoFiltro = null): array
    {
        self::assertEntornoBierzo();

        if (! config('app.anita_sync_cliente_write', false)) {
            throw new \RuntimeException('anita_sync_cliente_write está deshabilitado; no se escribe Anita.');
        }

        $analisis = self::analizar($codigoFiltro);
        $apiAnita = new ApiAnita();
        $legacy = 0;
        $anita = 0;
        $errores = [];

        foreach ($analisis['filas'] as $fila) {
            try {
                if ($fila['cambia_legacy']) {
                    $cliente = Cliente::query()->find((int) $fila['cliente_id']);
                    if ($cliente !== null) {
                        $cliente->desdefecha_exclusionpercepcioniva = $fila['solapa_desde'] !== ''
                            ? $fila['solapa_desde']
                            : null;
                        $cliente->hastafecha_exclusionpercepcioniva = $fila['solapa_hasta'] !== ''
                            ? $fila['solapa_hasta']
                            : null;
                        $cliente->save();
                        $legacy++;
                    }
                }

                if ($fila['cambia_anita'] && $fila['anita_existe']) {
                    self::actualizarFechasAnita(
                        $apiAnita,
                        (string) $fila['codigo'],
                        (string) $fila['anita_desde_ymd'],
                        (string) $fila['anita_hasta_ymd']
                    );
                    $anita++;
                }
            } catch (\Throwable $e) {
                $errores[] = 'Código '.$fila['codigo'].': '.$e->getMessage();
            }
        }

        return [
            'legacy' => $legacy,
            'anita' => $anita,
            'errores' => $errores,
        ];
    }

    private static function assertEntornoBierzo(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            throw new \RuntimeException('Solo aplica en EMPRESA=EL BIERZO (campos clim_dfexcl_piva / clim_hfexcl_piva).');
        }
    }

    /**
     * @return list<object>
     */
    private static function clientesConExclusionIva(?string $codigoFiltro): array
    {
        $q = DB::table('cliente as c')
            ->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('cliente_exclusion_percepcion as e')
                    ->whereColumn('e.cliente_id', 'c.id')
                    ->where('e.tipo', 'IVA');
            })
            ->select([
                'c.id',
                'c.codigo',
                'c.nombre',
                'c.desdefecha_exclusionpercepcioniva',
                'c.hastafecha_exclusionpercepcioniva',
            ])
            ->orderByRaw(SqlDialectSupport::ordenCodigoAsc('c.codigo'));

        if ($codigoFiltro !== null && trim($codigoFiltro) !== '') {
            $norm = ltrim(trim($codigoFiltro), '0') ?: '0';
            $q->where(function ($w) use ($norm, $codigoFiltro) {
                $w->where('c.codigo', $norm)
                    ->orWhere('c.codigo', trim($codigoFiltro))
                    ->orWhere('c.codigo', str_pad($norm, 6, '0', STR_PAD_LEFT));
            });
        }

        return $q->get()->all();
    }

    /**
     * @return array{existe: bool, desde: string, hasta: string}
     */
    private static function leerFechasAnita(ApiAnita $apiAnita, string $codigo): array
    {
        $codigoPad = str_pad(ltrim(trim($codigo), '0') ?: '0', 6, '0', STR_PAD_LEFT);
        $raw = $apiAnita->apiCall([
            'acc' => 'list',
            'tabla' => 'climae',
            'sistema' => 'ventas',
            'campos' => 'clim_cliente,clim_dfexcl_piva,clim_hfexcl_piva',
            'whereArmado' => " WHERE clim_cliente = '".$codigoPad."' ",
            'limit' => 'FIRST 1',
        ]);

        $fila = ApiAnita::primeraFilaLista((string) $raw);
        if ($fila === null) {
            return ['existe' => false, 'desde' => '', 'hasta' => ''];
        }

        return [
            'existe' => true,
            'desde' => self::fechaAnitaAYmd($fila->clim_dfexcl_piva ?? null),
            'hasta' => self::fechaAnitaAYmd($fila->clim_hfexcl_piva ?? null),
        ];
    }

    private static function actualizarFechasAnita(ApiAnita $apiAnita, string $codigo, string $desdeYmd, string $hastaYmd): void
    {
        $codigoPad = str_pad(ltrim(trim($codigo), '0') ?: '0', 6, '0', STR_PAD_LEFT);
        $apiAnita->apiCall([
            'acc' => 'update',
            'tabla' => 'climae',
            'sistema' => 'ventas',
            'valores' => "
                clim_dfexcl_piva = '".$desdeYmd."',
                clim_hfexcl_piva = '".$hastaYmd."'
            ",
            'whereArmado' => " WHERE clim_cliente = '".$codigoPad."' ",
        ]);
    }

    private static function fechaAYmd(?string $ymd): string
    {
        if ($ymd === null || $ymd === '' || $ymd === '0000-00-00') {
            return '00000000';
        }

        try {
            return Carbon::parse($ymd)->format('Ymd');
        } catch (\Throwable) {
            return '00000000';
        }
    }

    private static function fechaAnitaAYmd(mixed $valor): string
    {
        $texto = trim((string) ($valor ?? ''));
        if ($texto === '' || $texto === '0' || $texto === '00000000' || $texto === '0000-00-00') {
            return '00000000';
        }
        if (preg_match('/^\d{8}$/', $texto)) {
            return $texto;
        }
        try {
            return Carbon::parse($texto)->format('Ymd');
        } catch (\Throwable) {
            return '00000000';
        }
    }

    private static function normalizarFechaErp(mixed $valor): string
    {
        if ($valor === null || $valor === '' || $valor === '0000-00-00') {
            return '';
        }
        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('Y-m-d');
        }
        $texto = trim((string) $valor);
        if ($texto === '' || $texto === '0000-00-00') {
            return '';
        }

        try {
            return Carbon::parse($texto)->format('Y-m-d');
        } catch (\Throwable) {
            return substr($texto, 0, 10);
        }
    }
}
