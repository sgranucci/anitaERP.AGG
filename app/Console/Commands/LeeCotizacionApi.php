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
                            {--importar-rango : Alta/actualiza cada día con cotización BNA entre --desde y --hasta}
                            {--solo-informe : Con --importar-rango, solo cuenta cuántos días tienen dato (no graba)}
                            {--desde= : Con --backfill-vacias o --importar-rango, desde YYYY-MM-DD}
                            {--hasta= : Con --backfill-vacias o --importar-rango, hasta YYYY-MM-DD}';

    protected $description = 'Lee cotización dólar oficial BNA (ArgentinaDatos + fallback DolarApi) y la graba en cotizacion / cotizacion_moneda';

    public function __construct(
        private CotizacionRepositoryInterface $cotizacionRepository,
        private Cotizacion_MonedaRepositoryInterface $cotizacion_MonedaRepository,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('importar-rango')) {
            return $this->importarRango();
        }

        if ($this->option('backfill-vacias')) {
            return $this->backfillVacias();
        }

        $fecha = $this->resolverFechaOption($this->option('fecha')) ?? Carbon::now()->startOfDay();

        return $this->grabarFecha($fecha) === true ? self::SUCCESS : self::FAILURE;
    }

    private function importarRango(): int
    {
        $hasta = $this->resolverFechaOption($this->option('hasta')) ?? Carbon::now()->startOfDay();
        $desde = $this->resolverFechaOption($this->option('desde'))
            ?? $hasta->copy()->startOfYear();
        if ($desde->gt($hasta)) {
            $this->error('El --desde no puede ser posterior al --hasta.');

            return self::FAILURE;
        }

        $soloInforme = (bool) $this->option('solo-informe');
        $this->line(sprintf(
            'Importar rango %s → %s · %s',
            $desde->toDateString(),
            $hasta->toDateString(),
            $soloInforme ? 'solo informe' : 'graba cotizacion / cotizacion_moneda'
        ));

        $serie = $this->leerSerieOficialArgentinaDatos($desde, $hasta);
        if ($serie === null) {
            $this->error('No se pudo leer la serie oficial de ArgentinaDatos.');

            return self::FAILURE;
        }

        $diasRango = (int) $desde->diffInDays($hasta) + 1;
        $conDato = 0;
        $sinDato = 0;
        $yaOk = 0;
        $aCrear = 0;
        $aActualizar = 0;
        $ok = 0;
        $fail = 0;
        $monedaId = (int) config('cotizacion.monedaIdCommand');
        $cursor = $desde->copy();

        while ($cursor->lte($hasta)) {
            $fechaSql = $cursor->toDateString();
            $payload = $serie[$fechaSql] ?? null;
            if ($payload === null) {
                $sinDato++;
                $cursor->addDay();

                continue;
            }

            $conDato++;
            $estadoPre = $this->estadoFilaCotizacion($fechaSql, $monedaId);
            if ($estadoPre === 'ok') {
                $yaOk++;
            } elseif ($estadoPre === 'crear') {
                $aCrear++;
            } else {
                $aActualizar++;
            }

            if (! $soloInforme) {
                $resultado = $this->persistirCotizacion($cursor, $payload);
                if ($resultado) {
                    $ok++;
                } else {
                    $fail++;
                }
            }

            $cursor->addDay();
        }

        $this->table(['Control', 'Valor'], [
            ['Días del rango', (string) $diasRango],
            ['Con cotización BNA', (string) $conDato],
            ['Sin dato (finde/feriado)', (string) $sinDato],
            ['Ya cargadas (>0)', (string) $yaOk],
            ['A crear', (string) $aCrear],
            ['A actualizar (vacías)', (string) $aActualizar],
            ['Grabadas en esta corrida', $soloInforme ? '—' : (string) $ok],
            ['Fallidas', $soloInforme ? '—' : (string) $fail],
        ]);

        if ($soloInforme) {
            $this->comment(sprintf(
                'Para grabar: php artisan cotizacion:leeapi --importar-rango --desde=%s --hasta=%s',
                $desde->toDateString(),
                $hasta->toDateString()
            ));

            return self::SUCCESS;
        }

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<string, array{venta: float, compra: float, fuente: string, fecha: string}>|null
     */
    private function leerSerieOficialArgentinaDatos(Carbon $desde, Carbon $hasta): ?array
    {
        $url = 'https://api.argentinadatos.com/v1/cotizaciones/dolares/oficial';
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 60,
                'header' => "Accept: application/json\r\nUser-Agent: anitaERP-cotizacion:leeapi\r\n",
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        if (! is_string($raw) || $raw === '') {
            return null;
        }
        $data = json_decode($raw, true);
        if (! is_array($data)) {
            return null;
        }

        $desdeSql = $desde->toDateString();
        $hastaSql = $hasta->toDateString();
        $salida = [];
        foreach ($data as $fila) {
            if (! is_array($fila)) {
                continue;
            }
            $fecha = substr(trim((string) ($fila['fecha'] ?? '')), 0, 10);
            if ($fecha === '' || $fecha < $desdeSql || $fecha > $hastaSql) {
                continue;
            }
            $venta = (float) ($fila['venta'] ?? 0);
            if ($venta <= 0) {
                continue;
            }
            $salida[$fecha] = [
                'venta' => $venta,
                'compra' => (float) ($fila['compra'] ?? 0),
                'fuente' => 'argentinadatos-oficial',
                'fecha' => $fecha,
            ];
        }

        return $salida;
    }

    /** @return 'ok'|'crear'|'actualizar' */
    private function estadoFilaCotizacion(string $fechaSql, int $monedaId): string
    {
        $cotizacion = DB::table('cotizacion')->whereDate('fecha', $fechaSql)->first();
        if (! $cotizacion) {
            return 'crear';
        }
        $existeMoneda = DB::table('cotizacion_moneda')
            ->where('cotizacion_id', (int) $cotizacion->id)
            ->where('moneda_id', $monedaId)
            ->first();
        if ($existeMoneda && (float) ($existeMoneda->cotizacionventa ?? 0) > 0) {
            return 'ok';
        }

        return 'actualizar';
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

        return $this->persistirCotizacion($fecha, $payload) ? true : false;
    }

    /**
     * @param  array{venta: float|int|string, compra?: float|int|string, fuente?: string}  $payload
     */
    private function persistirCotizacion(Carbon $fecha, array $payload): bool
    {
        $venta = (float) $payload['venta'];
        $compra = (float) ($payload['compra'] ?? 0);
        $fuente = (string) ($payload['fuente'] ?? '');
        $monedaId = (int) config('cotizacion.monedaIdCommand');
        $usuario = (int) config('cotizacion.usuarioIdCommand');
        $fechaSql = $fecha->toDateString();

        if ($venta <= 0) {
            return false;
        }

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
        $script = $this->resolverScriptCotizacionBna();
        if ($script === null) {
            $this->error('No existe parser: scripts/cotizacionbna.py ni storage/cotizacionbna.py');

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

    private function resolverScriptCotizacionBna(): ?string
    {
        $candidatos = [
            base_path('scripts/cotizacionbna.py'),
            storage_path('cotizacionbna.py'),
        ];
        foreach ($candidatos as $ruta) {
            if (is_file($ruta)) {
                return $ruta;
            }
        }

        return null;
    }
}
