<?php

namespace App\Support\Contable;

use App\ApiAnita;
use App\Models\Caja\Bingo\RendicionBingoCaja;
use App\Models\Configuracion\Empresa;
use App\Models\Contable\Asiento;
use App\Models\Contable\Asiento_Movimiento;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Backfill: leyendas cortas p-vtabingo.c en asientos del cierre bingo (ERP + ctamov).
 *
 * Histórico ERP: «Cierre rendición bingo — DD/MM/YYYY — … — {leyenda}».
 * Objetivo: solo «Pago de premios» / «Dev. pozo acum.» / «Canon …».
 */
final class CorregirDescripcionAsientoCierreBingoSupport
{
    /**
     * @return Collection<int, Asiento>
     */
    public function asientosAfectados(string $desde, string $hasta, ?int $empresaId = null): Collection
    {
        $ids = $this->asientoIdsRango($desde, $hasta, $empresaId);
        if ($ids === []) {
            return collect();
        }

        $query = Asiento::query()->whereIn('id', $ids)->orderBy('fecha')->orderBy('id');
        if ($empresaId !== null && $empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        return $query->get();
    }

    /**
     * @return list<int>
     */
    public function asientoIdsRango(string $desde, string $hasta, ?int $empresaId = null): array
    {
        $ids = [];
        foreach ($this->queryRendicionesCerradas($desde, $hasta, $empresaId)->get() as $rendicion) {
            foreach ($this->idsAsientoDeRendicion($rendicion) as $aid) {
                $ids[$aid] = true;
            }
        }

        $out = array_map('intval', array_keys($ids));
        sort($out);

        return $out;
    }

    /**
     * @return array{
     *   desde: string,
     *   hasta: string,
     *   rendiciones_cerradas: int,
     *   asientos: int,
     *   a_corregir: int,
     *   por_leyenda: array<string, int>,
     *   por_empresa: array<int, array{rendiciones: int, asientos: int}>
     * }
     */
    public function resumenAlcance(string $desde, string $hasta, ?int $empresaId = null): array
    {
        $rendiciones = $this->queryRendicionesCerradas($desde, $hasta, $empresaId)->get();
        $asientos = $this->asientosAfectados($desde, $hasta, $empresaId);

        $porLeyenda = [];
        $aCorregir = 0;
        foreach ($asientos as $asiento) {
            $leyenda = $this->resolverLeyendaObjetivo((string) ($asiento->observacion ?? ''));
            if ($leyenda === null) {
                continue;
            }
            $porLeyenda[$leyenda] = ($porLeyenda[$leyenda] ?? 0) + 1;
            if ($this->requiereActualizacionCabecera((string) ($asiento->observacion ?? ''), $leyenda)) {
                $aCorregir++;
            }
        }
        ksort($porLeyenda);

        $porEmpresa = [];
        foreach ($rendiciones as $rendicion) {
            $emp = (int) $rendicion->empresa_id;
            if ($emp <= 0) {
                continue;
            }
            if (! isset($porEmpresa[$emp])) {
                $porEmpresa[$emp] = ['rendiciones' => 0, 'asientos' => []];
            }
            $porEmpresa[$emp]['rendiciones']++;
            foreach ($this->idsAsientoDeRendicion($rendicion) as $aid) {
                $porEmpresa[$emp]['asientos'][$aid] = true;
            }
        }

        $porEmpresaOut = [];
        foreach ($porEmpresa as $emp => $datos) {
            $porEmpresaOut[$emp] = [
                'rendiciones' => $datos['rendiciones'],
                'asientos' => count($datos['asientos']),
            ];
        }
        ksort($porEmpresaOut);

        return [
            'desde' => $desde,
            'hasta' => $hasta,
            'rendiciones_cerradas' => $rendiciones->count(),
            'asientos' => $asientos->count(),
            'a_corregir' => $aCorregir,
            'por_leyenda' => $porLeyenda,
            'por_empresa' => $porEmpresaOut,
        ];
    }

    public function requiereActualizacionCabecera(string $observacion, ?string $leyendaObjetivo = null): bool
    {
        $leyenda = $leyendaObjetivo ?? $this->resolverLeyendaObjetivo($observacion);
        if ($leyenda === null) {
            return false;
        }

        return trim($observacion) !== $leyenda;
    }

    public function requiereActualizacionLinea(string $observacion, string $leyendaObjetivo): bool
    {
        return trim($observacion) !== $leyendaObjetivo;
    }

    /**
     * Extrae la leyenda Anita (último segmento tras « — ») o null si no es cierre bingo.
     */
    public function resolverLeyendaObjetivo(string $observacion): ?string
    {
        $obs = trim($observacion);
        if ($obs === '') {
            return null;
        }

        if ($obs === CierreRendicionBingoAsientoSupport::LEYENDA_PAGO_PREMIOS
            || $obs === CierreRendicionBingoAsientoSupport::LEYENDA_DEV_POZO
            || $obs === CierreRendicionBingoAsientoSupport::LEYENDA_CANON_HOSPITAL
            || str_starts_with($obs, 'Canon ')) {
            return $obs;
        }

        if (! str_starts_with($obs, CierreRendicionBingoAsientoSupport::DESCRIPCION_ASIENTO.' — ')) {
            return null;
        }

        $parts = preg_split('/\s—\s/u', $obs) ?: [];
        $suffix = trim((string) end($parts));
        if ($suffix === '' || $suffix === CierreRendicionBingoAsientoSupport::DESCRIPCION_ASIENTO) {
            return null;
        }

        if ($suffix === CierreRendicionBingoAsientoSupport::LEYENDA_PAGO_PREMIOS
            || $suffix === CierreRendicionBingoAsientoSupport::LEYENDA_DEV_POZO
            || $suffix === CierreRendicionBingoAsientoSupport::LEYENDA_CANON_HOSPITAL
            || str_starts_with($suffix, 'Canon ')) {
            return $suffix;
        }

        return null;
    }

    /**
     * Texto para ctav_desc_mov (30): paridad p-vtabingo (permite . y %).
     */
    public function descMovAnita(string $leyenda): string
    {
        $texto = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $leyenda);
        if (! is_string($texto) || $texto === '') {
            $texto = $leyenda;
        }
        $texto = preg_replace('/[^A-Za-z0-9 .%\\-]+/', '', $texto) ?? '';
        $texto = trim((string) preg_replace('/\s+/', ' ', $texto));
        $texto = str_replace("'", "''", $texto);

        return mb_substr($texto, 0, 30);
    }

    /**
     * @return array{
     *   asientos_erp: int,
     *   lineas_erp: int,
     *   lineas_anita: int,
     *   ya_ok: int,
     *   sin_leyenda: int,
     *   errores: list<string>,
     *   detalle: list<string>
     * }
     */
    public function ejecutar(string $desde, string $hasta, bool $dryRun = false, ?int $empresaId = null): array
    {
        return $this->ejecutarSobreColeccion(
            $this->asientosAfectados($desde, $hasta, $empresaId),
            $dryRun,
        );
    }

    private function queryRendicionesCerradas(string $desde, string $hasta, ?int $empresaId = null)
    {
        $query = RendicionBingoCaja::query()
            ->whereNotNull('asiento_id')
            ->where('asiento_id', '>', 0)
            ->whereDate('fecha_jornada', '>=', $desde)
            ->whereDate('fecha_jornada', '<=', $hasta);

        if ($empresaId !== null && $empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        return $query;
    }

    /**
     * @return list<int>
     */
    private function idsAsientoDeRendicion(RendicionBingoCaja $rendicion): array
    {
        $ids = [];
        $principal = (int) ($rendicion->asiento_id ?? 0);
        if ($principal > 0) {
            $ids[$principal] = true;
        }
        $json = is_array($rendicion->asientos_cierre_ids_json) ? $rendicion->asientos_cierre_ids_json : [];
        foreach ($json as $aid) {
            $aid = (int) $aid;
            if ($aid > 0) {
                $ids[$aid] = true;
            }
        }

        return array_map('intval', array_keys($ids));
    }

    /**
     * @param  Collection<int, Asiento>  $asientos
     * @return array{
     *   asientos_erp: int,
     *   lineas_erp: int,
     *   lineas_anita: int,
     *   ya_ok: int,
     *   sin_leyenda: int,
     *   errores: list<string>,
     *   detalle: list<string>
     * }
     */
    private function ejecutarSobreColeccion(Collection $asientos, bool $dryRun): array
    {
        $resultado = [
            'asientos_erp' => 0,
            'lineas_erp' => 0,
            'lineas_anita' => 0,
            'ya_ok' => 0,
            'sin_leyenda' => 0,
            'errores' => [],
            'detalle' => [],
        ];

        foreach ($asientos as $asiento) {
            $obsActual = (string) ($asiento->observacion ?? '');
            $leyenda = $this->resolverLeyendaObjetivo($obsActual);
            if ($leyenda === null) {
                $resultado['sin_leyenda']++;
                $resultado['errores'][] = 'Asiento ERP #'.$asiento->id
                    .': no se pudo resolver leyenda desde «'.mb_strimwidth($obsActual, 0, 80, '…').'».';
                continue;
            }

            $descAnita = $this->descMovAnita($leyenda);
            $huboCambioErp = false;

            if ($this->requiereActualizacionCabecera($obsActual, $leyenda)) {
                $resultado['detalle'][] = sprintf(
                    '#%d nro=%s | %s → %s',
                    $asiento->id,
                    (string) ($asiento->numeroasiento ?? $asiento->id),
                    mb_strimwidth($obsActual, 0, 60, '…'),
                    $leyenda,
                );
                if (! $dryRun) {
                    $asiento->observacion = $leyenda;
                    $asiento->save();
                }
                $resultado['asientos_erp']++;
                $huboCambioErp = true;
            }

            $lineas = Asiento_Movimiento::query()
                ->where('asiento_id', $asiento->id)
                ->orderBy('id')
                ->get();

            foreach ($lineas as $linea) {
                if (! $this->requiereActualizacionLinea((string) ($linea->observacion ?? ''), $leyenda)) {
                    continue;
                }
                if (! $dryRun) {
                    $linea->observacion = $leyenda;
                    $linea->save();
                }
                $resultado['lineas_erp']++;
                $huboCambioErp = true;
            }

            $empresa = Empresa::query()->find($asiento->empresa_id);
            $codigoEmpresa = (string) ($empresa->codigo ?? '1');
            $nroAsiento = trim((string) ($asiento->numeroasiento ?? ''));

            if ($nroAsiento === '') {
                if ($huboCambioErp) {
                    $resultado['errores'][] = 'Asiento ERP #'.$asiento->id.': sin numeroasiento para ctamov.';
                }
                continue;
            }

            $pendientesAnita = 0;
            try {
                $pendientesAnita = $this->contarLineasAnitaPendientes($codigoEmpresa, $nroAsiento, $descAnita);
            } catch (RuntimeException $e) {
                $resultado['errores'][] = 'Asiento ERP #'.$asiento->id.' (Anita '.$nroAsiento.'): '.$e->getMessage();
                continue;
            }

            if (! $huboCambioErp && $pendientesAnita === 0) {
                $resultado['ya_ok']++;
                continue;
            }

            if ($dryRun) {
                $resultado['lineas_anita'] += $pendientesAnita;
                continue;
            }

            if ($pendientesAnita > 0) {
                try {
                    $resultado['lineas_anita'] += $this->actualizarLineasAnita(
                        $codigoEmpresa,
                        $nroAsiento,
                        $descAnita,
                    );
                } catch (RuntimeException $e) {
                    $resultado['errores'][] = 'Asiento ERP #'.$asiento->id.' (Anita '.$nroAsiento.'): '.$e->getMessage();
                }
            }
        }

        return $resultado;
    }

    private function contarLineasAnitaPendientes(string $codigoEmpresa, string $nroAsiento, string $descNueva): int
    {
        $pendientes = 0;
        foreach ($this->lineasCtamov($codigoEmpresa, $nroAsiento) as $fila) {
            if ((string) ($fila['ctav_desc_mov'] ?? '') !== $descNueva) {
                $pendientes++;
            }
        }

        return $pendientes;
    }

    /**
     * @return list<array{ctav_nro_linea: string, ctav_desc_mov: string}>
     */
    private function lineasCtamov(string $codigoEmpresa, string $nroAsiento): array
    {
        $api = new ApiAnita;
        $filas = ApiAnita::decodificarListaFilas($api->apiCall([
            'acc' => 'list',
            'tabla' => 'ctamov',
            'sistema' => 'contab',
            'campos' => 'ctav_nro_linea,ctav_desc_mov',
            'whereArmado' => " WHERE ctav_empresa = '".$codigoEmpresa."' AND ctav_nro_asiento = '".$nroAsiento."'",
        ]));

        if ($filas === []) {
            throw new RuntimeException('No se encontraron líneas ctamov en Anita.');
        }

        $out = [];
        foreach ($filas as $fila) {
            $row = is_array($fila) ? $fila : (array) $fila;
            $out[] = [
                'ctav_nro_linea' => (string) ($row['ctav_nro_linea'] ?? ''),
                'ctav_desc_mov' => (string) ($row['ctav_desc_mov'] ?? ''),
            ];
        }

        return $out;
    }

    private function actualizarLineasAnita(string $codigoEmpresa, string $nroAsiento, string $descNueva): int
    {
        $actualizadas = 0;
        $api = new ApiAnita;

        foreach ($this->lineasCtamov($codigoEmpresa, $nroAsiento) as $fila) {
            $linea = $fila['ctav_nro_linea'];
            $desc = $fila['ctav_desc_mov'];

            if ($desc === $descNueva) {
                continue;
            }

            $respuesta = $api->apiCallEscritura([
                'acc' => 'update',
                'tabla' => 'ctamov',
                'sistema' => 'contab',
                'valores' => " ctav_desc_mov = '".$descNueva."' ",
                'whereArmado' => " WHERE ctav_empresa = '".$codigoEmpresa
                    ."' AND ctav_nro_asiento = '".$nroAsiento
                    ."' AND ctav_nro_linea = '".$linea."' ",
            ], 'ctamov update descripcion cierre bingo');

            if (! ApiAnita::respuestaBridgeEscrituraExitosa($respuesta)) {
                $err = ApiAnita::extraerMensajeError($respuesta) ?? trim($respuesta);
                throw new RuntimeException('Bridge Anita: '.($err !== '' ? $err : 'respuesta no exitosa'));
            }

            $actualizadas++;
        }

        return $actualizadas;
    }
}
