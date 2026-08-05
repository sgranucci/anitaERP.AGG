<?php

declare(strict_types=1);

namespace App\Services\Caja;

use App\ApiAnita;
use App\Models\Caja\CotizacionTesoreria;
use App\Models\Configuracion\Empresa;
use App\Support\Caja\CotizacionTesoreriaMonedasSupport;
use App\Support\Ventas\GastronomiaTicketTarjetaAnitaBridgeSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Importa Informix caja.cotiz_tes (a-rendmaquina / p-vtagastro) hacia cotizacion_tesoreria.
 * Por empresa: Biyemas (1) + bridges Kandiko (2) / Rebisco (3).
 */
final class CotizacionTesoreriaAnitaImportService
{
    /**
     * @param  callable(string):void|null  $log
     * @return array{leidos: int, creados: int, actualizados: int, omitidos: int, por_empresa: array<int, array{leidos: int, creados: int, actualizados: int, omitidos: int}>}
     */
    public function importarTodas(?string $fechaDesde = null, ?string $fechaHasta = null, ?callable $log = null): array
    {
        $totales = [
            'leidos' => 0,
            'creados' => 0,
            'actualizados' => 0,
            'omitidos' => 0,
            'por_empresa' => [],
        ];

        foreach ($this->empresasSyncOrden() as $empresaId) {
            if (! Empresa::query()->whereKey($empresaId)->exists()) {
                if ($log !== null) {
                    $log("Empresa id {$empresaId} inexistente; se omite.");
                }
                continue;
            }

            $ret = $this->importarRango($fechaDesde, $fechaHasta, $empresaId, $log);
            $totales['por_empresa'][$empresaId] = $ret;
            $totales['leidos'] += $ret['leidos'];
            $totales['creados'] += $ret['creados'];
            $totales['actualizados'] += $ret['actualizados'];
            $totales['omitidos'] += $ret['omitidos'];
        }

        return $totales;
    }

    /**
     * @param  callable(string):void|null  $log
     * @return array{leidos: int, creados: int, actualizados: int, omitidos: int}
     */
    public function importarRango(
        ?string $fechaDesde = null,
        ?string $fechaHasta = null,
        int $empresaId = 1,
        ?callable $log = null,
    ): array {
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', '-1');

        if ($empresaId <= 0 || ! Empresa::query()->whereKey($empresaId)->exists()) {
            throw new \InvalidArgumentException("empresa_id {$empresaId} inexistente.");
        }

        $hasta = $fechaHasta
            ? Carbon::parse($fechaHasta)->startOfDay()
            : Carbon::today()->startOfDay();
        $desde = $fechaDesde
            ? Carbon::parse($fechaDesde)->startOfDay()
            : Carbon::create(2000, 1, 1)->startOfDay();

        if ($desde->gt($hasta)) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        $reportar = static function (string $mensaje) use ($log): void {
            if ($log !== null) {
                $log($mensaje);
            }
        };

        $bridge = $this->parametrosBridge($empresaId);
        $reportar(sprintf(
            'Empresa %d | bridge %s | sistema %s',
            $empresaId,
            ApiAnita::urlBridge($bridge['servidor'] ?? null),
            $bridge['sistema'] ?? 'caja',
        ));

        $leidos = 0;
        $creados = 0;
        $actualizados = 0;
        $omitidos = 0;

        $cursor = $desde->copy()->startOfMonth();
        while ($cursor->lte($hasta)) {
            $mesDesde = $cursor->copy()->startOfMonth();
            $mesHasta = $cursor->copy()->endOfMonth();
            if ($mesDesde->lt($desde)) {
                $mesDesde = $desde->copy();
            }
            if ($mesHasta->gt($hasta)) {
                $mesHasta = $hasta->copy();
            }

            $reportar(sprintf(
                '  Leyendo cotiz_tes %s → %s…',
                $mesDesde->format('Y-m-d'),
                $mesHasta->format('Y-m-d'),
            ));

            $filas = $this->leerMes($empresaId, $mesDesde, $mesHasta);
            $reportar('    Filas leídas: '.count($filas));

            foreach ($filas as $fila) {
                $leidos++;
                $payload = $this->mapearFila($fila, $empresaId);
                if ($payload === null) {
                    $omitidos++;

                    continue;
                }

                $resultado = DB::transaction(function () use ($payload) {
                    return $this->upsert($payload);
                });

                if ($resultado === 'creado') {
                    $creados++;
                } elseif ($resultado === 'actualizado') {
                    $actualizados++;
                } else {
                    $omitidos++;
                }
            }

            $cursor->addMonthNoOverflow()->startOfMonth();
        }

        return [
            'leidos' => $leidos,
            'creados' => $creados,
            'actualizados' => $actualizados,
            'omitidos' => $omitidos,
        ];
    }

    /**
     * @return list<int>
     */
    public function empresasSyncOrden(): array
    {
        $orden = (array) config('caja.cotizacion_tesoreria_anita_empresas_sync', [1, 2, 3]);

        return array_values(array_filter(array_map('intval', $orden), fn (int $id) => $id > 0));
    }

    /**
     * @return array{servidor?:string,path_sistema?:string,sistema:string,ifx_server?:string}
     */
    private function parametrosBridge(int $empresaId): array
    {
        $params = GastronomiaTicketTarjetaAnitaBridgeSupport::parametrosBridge($empresaId);
        $sistema = trim((string) config('caja.cotizacion_tesoreria_anita_sistema', 'caja'));
        if ($sistema !== '') {
            $params['sistema'] = $sistema;
        }

        return $params;
    }

    /**
     * @return list<object>
     */
    private function leerMes(int $empresaId, Carbon $desde, Carbon $hasta): array
    {
        $desdeEntera = (int) $desde->format('Ymd');
        $hastaEntera = (int) $hasta->format('Ymd');

        $campos = ['cott_fecha', 'cott_fecha_alfa'];
        foreach (CotizacionTesoreriaMonedasSupport::CODIGOS as $codigo) {
            $campos[] = 'cott_cambio_com'.$codigo;
            $campos[] = 'cott_cambio_vta'.$codigo;
        }

        $where = ' WHERE cott_fecha >= '.$desdeEntera
            .' AND cott_fecha <= '.$hastaEntera;

        $tabla = (string) config('caja.cotizacion_tesoreria_anita_tabla', 'cotiz_tes');
        $payload = array_merge($this->parametrosBridge($empresaId), [
            'acc' => 'list',
            'tabla' => $tabla,
            'campos' => implode(', ', $campos),
            'whereArmado' => $where,
            'orderBy' => 'cott_fecha',
        ]);

        $parsed = ApiAnita::parsearRespuestaLista((new ApiAnita)->apiCall($payload));

        if ($parsed['error_lectura'] !== null) {
            throw new \RuntimeException(
                "No se pudo listar {$tabla} Anita (empresa {$empresaId}): ".$parsed['error_lectura']
            );
        }

        return $parsed['filas'];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapearFila(object $fila, int $empresaId): ?array
    {
        $fechaEntera = (int) preg_replace('/\D+/', '', (string) ($fila->cott_fecha ?? ''));
        if ($fechaEntera < 19000101 || $fechaEntera > 29991231) {
            return null;
        }

        $ymd = sprintf(
            '%04d-%02d-%02d',
            (int) substr((string) $fechaEntera, 0, 4),
            (int) substr((string) $fechaEntera, 4, 2),
            (int) substr((string) $fechaEntera, 6, 2),
        );

        try {
            $fecha = Carbon::createFromFormat('Y-m-d', $ymd);
            if ($fecha === false || $fecha->format('Y-m-d') !== $ymd) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        $fechaAlfa = trim((string) ($fila->cott_fecha_alfa ?? ''));
        if ($fechaAlfa === '') {
            $fechaAlfa = $fecha->format('d/m/Y');
        }

        $out = [
            'empresa_id' => $empresaId,
            'fecha' => $ymd,
            'fecha_anita' => $fechaEntera,
            'fecha_alfa' => mb_substr($fechaAlfa, 0, 10),
        ];

        foreach (CotizacionTesoreriaMonedasSupport::CODIGOS as $codigo) {
            $out[CotizacionTesoreriaMonedasSupport::columnaCompra($codigo)] = $this->nullableFloat(
                $fila->{'cott_cambio_com'.$codigo} ?? null
            );
            $out[CotizacionTesoreriaMonedasSupport::columnaVenta($codigo)] = $this->nullableFloat(
                $fila->{'cott_cambio_vta'.$codigo} ?? null
            );
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function upsert(array $payload): string
    {
        $empresaId = (int) $payload['empresa_id'];
        $existente = CotizacionTesoreria::query()
            ->where('empresa_id', $empresaId)
            ->where(function ($q) use ($payload) {
                $q->where('fecha', $payload['fecha'])
                    ->orWhere('fecha_anita', $payload['fecha_anita']);
            })
            ->first();

        if ($existente === null) {
            $payload['usuario_id'] = Auth::id();
            CotizacionTesoreria::query()->create($payload);

            return 'creado';
        }

        $existente->fill($payload);
        if (! $existente->isDirty()) {
            return 'omitido';
        }
        $existente->save();

        return 'actualizado';
    }

    private function nullableFloat(mixed $valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        $n = (float) $valor;
        if ($n <= 0) {
            return null;
        }

        return round($n, 6);
    }
}
