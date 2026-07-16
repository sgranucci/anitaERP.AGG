<?php

namespace App\Console\Commands;

use App\Repositories\Configuracion\CotizacionRepositoryInterface;
use App\Repositories\Configuracion\Cotizacion_MonedaRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeeCotizacionApi extends Command
{
    protected $signature = 'cotizacion:leeapi
                            {--fecha= : Fecha YYYY-MM-DD (default: hoy)}
                            {--backfill-vacias : Actualiza registros con venta vacía/cero en el rango reciente}
                            {--desde= : Con --backfill-vacias, desde YYYY-MM-DD}
                            {--hasta= : Con --backfill-vacias, hasta YYYY-MM-DD}';

    protected $description = 'Lee cotización del dólar BNA (parser Python) y la graba en cotizacion / cotizacion_moneda';

    public function __construct(
        private CotizacionRepositoryInterface $cotizacionRepository,
        private Cotizacion_MonedaRepositoryInterface $cotizacion_MonedaRepository,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('backfill-vacias')) {
            return $this->backfillVacias();
        }

        $fecha = $this->resolverFechaOption($this->option('fecha')) ?? Carbon::now()->startOfDay();

        return $this->grabarFecha($fecha) === true ? self::SUCCESS : self::FAILURE;
    }

    private function backfillVacias(): int
    {
        $hasta = $this->resolverFechaOption($this->option('hasta')) ?? Carbon::now()->startOfDay();
        $desde = $this->resolverFechaOption($this->option('desde'))
            ?? $hasta->copy()->subDays(14);

        $monedaId = (int) config('cotizacion.monedaIdCommand');
        $filas = DB::table('cotizacion as c')
            ->join('cotizacion_moneda as cm', 'cm.cotizacion_id', '=', 'c.id')
            ->where('cm.moneda_id', $monedaId)
            ->whereBetween('c.fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->where(function ($q) {
                $q->whereNull('cm.cotizacionventa')
                    ->orWhere('cm.cotizacionventa', '<=', 0);
            })
            ->orderBy('c.fecha')
            ->get(['c.id', 'c.fecha', 'cm.id as cotizacion_moneda_id']);

        if ($filas->isEmpty()) {
            $this->info('No hay cotizaciones vacías en el rango.');

            return self::SUCCESS;
        }

        $ok = 0;
        $sinDatoBna = 0;
        $fail = 0;
        foreach ($filas as $fila) {
            $fecha = Carbon::parse($fila->fecha)->startOfDay();
            $this->line('Backfill '.$fecha->toDateString().'...');
            $resultado = $this->grabarFecha($fecha, true);
            if ($resultado === true) {
                $ok++;
            } elseif ($resultado === null) {
                $sinDatoBna++;
                $this->warn('Sin cotización BNA para '.$fecha->toDateString().' (finde/feriado o no publicada).');
            } else {
                $fail++;
            }
        }

        $this->info("Backfill terminado: ok={$ok} sin_dato_bna={$sinDatoBna} fail={$fail}");

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return bool|null true ok, false error, null sin dato BNA (solo si $tolerarSinDato)
     */
    private function grabarFecha(Carbon $fecha, bool $tolerarSinDato = false): ?bool
    {
        $payload = $this->leerCotizacionBna($fecha);
        if ($payload === null) {
            return $tolerarSinDato ? null : false;
        }
        if (($payload['sin_dato'] ?? false) === true) {
            return $tolerarSinDato ? null : false;
        }

        $venta = (float) $payload['venta'];
        $compra = (float) ($payload['compra'] ?? 0);
        $fuente = (string) ($payload['fuente'] ?? '');
        $monedaId = (int) config('cotizacion.monedaIdCommand');
        $usuario = (int) config('cotizacion.usuarioIdCommand');
        $fechaSql = $fecha->toDateString();

        DB::beginTransaction();
        try {
            $cotizacion = DB::table('cotizacion')->whereDate('fecha', $fechaSql)->first();

            if (! $cotizacion) {
                $cotizacionModel = $this->cotizacionRepository->create([
                    'fecha' => $fechaSql,
                    'usuario_id' => $usuario,
                ]);
                $cotizacionId = (int) $cotizacionModel->id;
                $this->cotizacion_MonedaRepository->create([
                    'cotizacion_ids' => [$cotizacionId],
                    'moneda_ids' => [$monedaId],
                    'cotizacioncompras' => [$compra],
                    'cotizacionventas' => [$venta],
                ], $cotizacionId);

                Log::info('grabo cotizacion diaria', [
                    'id' => $cotizacionId,
                    'fecha' => $fechaSql,
                    'venta' => $venta,
                    'compra' => $compra,
                    'fuente' => $fuente,
                ]);
                $this->info("Alta {$fechaSql}: venta={$venta} compra={$compra} fuente={$fuente}");
            } else {
                $cotizacionId = (int) $cotizacion->id;
                $existeMoneda = DB::table('cotizacion_moneda')
                    ->where('cotizacion_id', $cotizacionId)
                    ->where('moneda_id', $monedaId)
                    ->first();

                if ($existeMoneda) {
                    DB::table('cotizacion_moneda')
                        ->where('id', $existeMoneda->id)
                        ->update([
                            'cotizacionventa' => $venta,
                            'cotizacioncompra' => $compra,
                            'updated_at' => now(),
                        ]);
                } else {
                    $this->cotizacion_MonedaRepository->create([
                        'cotizacion_ids' => [$cotizacionId],
                        'moneda_ids' => [$monedaId],
                        'cotizacioncompras' => [$compra],
                        'cotizacionventas' => [$venta],
                    ], $cotizacionId);
                }

                Log::info('actualizo cotizacion diaria', [
                    'id' => $cotizacionId,
                    'fecha' => $fechaSql,
                    'venta' => $venta,
                    'compra' => $compra,
                    'fuente' => $fuente,
                ]);
                $this->info("Update {$fechaSql}: venta={$venta} compra={$compra} fuente={$fuente}");
            }

            DB::commit();

            return true;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('error cotizacion:leeapi', ['fecha' => $fechaSql, 'error' => $e->getMessage()]);
            $this->error($e->getMessage());

            return false;
        }
    }

    /**
     * @return array{venta?: float, compra?: float, fuente?: string, fecha?: string, sin_dato?: bool}|null
     */
    private function leerCotizacionBna(Carbon $fecha): ?array
    {
        $script = storage_path('cotizacionbna.py');
        if (! is_file($script)) {
            $this->error("No existe parser: {$script}");

            return null;
        }

        $cmd = sprintf(
            'python3 %s --json --fecha %s 2>&1',
            escapeshellarg($script),
            escapeshellarg($fecha->toDateString())
        );
        $raw = shell_exec($cmd);
        $raw = is_string($raw) ? trim($raw) : '';

        if ($raw === '') {
            $this->error('Parser BNA sin salida para '.$fecha->toDateString());

            return null;
        }

        if (str_contains($raw, 'No se pudo obtener cotización')) {
            return ['sin_dato' => true];
        }

        $data = json_decode($raw, true);
        if (! is_array($data) || ! isset($data['venta'])) {
            $lineas = preg_split("/\r\n|\n|\r/", $raw) ?: [];
            $ultima = trim((string) end($lineas));
            $data = json_decode($ultima, true);
        }

        if (! is_array($data) || ! isset($data['venta'])) {
            $this->error('Parser BNA respuesta inválida: '.$raw);

            return null;
        }

        $venta = (float) $data['venta'];
        if ($venta <= 0) {
            $this->error('Cotización venta inválida (<=0) para '.$fecha->toDateString());

            return null;
        }

        return [
            'venta' => $venta,
            'compra' => (float) ($data['compra'] ?? 0),
            'fuente' => (string) ($data['fuente'] ?? ''),
            'fecha' => (string) ($data['fecha'] ?? $fecha->toDateString()),
        ];
    }

    private function resolverFechaOption(mixed $valor): ?Carbon
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return Carbon::parse((string) $valor)->startOfDay();
    }
}
