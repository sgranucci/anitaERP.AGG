<?php

namespace App\Console\Commands;

use App\Models\Configuracion\Cotizacion;
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

    protected $description = 'Lee cotización dólar y euro oficial BNA (ArgentinaDatos + fallback DolarApi) y la graba en cotizacion / cotizacion_moneda. En Interforming no escribe Anita; en el resto hace alta o actualización en shared, sin borrar el día.';

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
        $serieEuro = $this->leerSerieOficialArgentinaDatos($desde, $hasta, 'eur', 'argentinadatos-eur-oficial');
        if ($serieEuro === null) {
            $this->warn('No se pudo leer la serie del euro. El rango sigue solo con el dólar.');
            $serieEuro = [];
        }

        $diasRango = (int) $desde->diffInDays($hasta) + 1;
        $conDato = 0;
        $sinDato = 0;
        $yaOk = 0;
        $aCrear = 0;
        $aActualizar = 0;
        $conEuro = 0;
        $sinEuro = 0;
        $ok = 0;
        $fail = 0;
        $monedaId = $this->monedaIdDolar();
        $monedaEuroId = $this->monedaIdEuro();
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
            $payloadEuro = $serieEuro[$fechaSql] ?? null;
            if (is_array($payloadEuro) && (float) ($payloadEuro['venta'] ?? 0) > 0) {
                $conEuro++;
            } else {
                $sinEuro++;
                $payloadEuro = null;
            }
            $estadoPre = $this->estadoFilaCotizacion($fechaSql, $monedaId);
            if ($estadoPre === 'ok' && $monedaEuroId > 0 && $this->estadoFilaCotizacion($fechaSql, $monedaEuroId) !== 'ok') {
                $estadoPre = 'actualizar';
            }
            if ($estadoPre === 'ok') {
                $yaOk++;
            } elseif ($estadoPre === 'crear') {
                $aCrear++;
            } else {
                $aActualizar++;
            }

            if (! $soloInforme) {
                $resultado = $this->persistirCotizacion($cursor, $payload, $payloadEuro);
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
            ['Con cotización euro', (string) $conEuro],
            ['Dólar sin euro', (string) $sinEuro],
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
    private function leerSerieOficialArgentinaDatos(
        Carbon $desde,
        Carbon $hasta,
        string $ruta = 'dolares/oficial',
        string $fuente = 'argentinadatos-oficial',
    ): ?array {
        $url = 'https://api.argentinadatos.com/v1/cotizaciones/'.$ruta;
        $ctx = stream_context_create([
            'http' => $this->opcionesHttpProxy([
                'timeout' => 60,
                'header' => "Accept: application/json\r\nUser-Agent: anitaERP-cotizacion:leeapi\r\n",
            ]),
            'https' => $this->opcionesHttpProxy([
                'timeout' => 60,
                'header' => "Accept: application/json\r\nUser-Agent: anitaERP-cotizacion:leeapi\r\n",
            ]),
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
            $casa = strtolower(trim((string) ($fila['casa'] ?? 'oficial')));
            if ($casa !== '' && $casa !== 'oficial') {
                continue;
            }
            $venta = (float) ($fila['venta'] ?? 0);
            if ($venta <= 0) {
                continue;
            }
            $salida[$fecha] = [
                'venta' => $venta,
                'compra' => (float) ($fila['compra'] ?? 0),
                'fuente' => $fuente,
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

        $fechas = $this->fechasConMonedaVacia($desde->toDateString(), $hasta->toDateString(), $this->monedaIdDolar());
        $monedaEuroId = $this->monedaIdEuro();
        if ($monedaEuroId > 0 && $monedaEuroId !== $this->monedaIdDolar()) {
            $fechas = array_values(array_unique(array_merge(
                $fechas,
                $this->fechasConMonedaVacia($desde->toDateString(), $hasta->toDateString(), $monedaEuroId)
            )));
            sort($fechas);
        }

        if ($fechas === []) {
            $this->info('No hay cotizaciones vacías en el rango.');

            return self::SUCCESS;
        }

        $ok = 0;
        $sinDatoBna = 0;
        $fail = 0;
        foreach ($fechas as $fechaSql) {
            $fecha = Carbon::parse($fechaSql)->startOfDay();
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
            if (! $tolerarSinDato) {
                $this->error('Sin cotización BNA para '.$fecha->toDateString().' (finde, feriado o sin acceso a la API).');
            }

            return $tolerarSinDato ? null : false;
        }

        $payloadEuro = $this->leerCotizacionBna($fecha, 'eur', false);
        if (! is_array($payloadEuro) || ($payloadEuro['sin_dato'] ?? false) === true || (float) ($payloadEuro['venta'] ?? 0) <= 0) {
            $this->warn('Sin cotización euro BNA para '.$fecha->toDateString().'.');
            $payloadEuro = null;
        }

        return $this->persistirCotizacion($fecha, $payload, $payloadEuro) ? true : false;
    }

    /**
     * @param  array{venta: float|int|string, compra?: float|int|string, fuente?: string}  $payload
     * @param  array{venta: float|int|string, compra?: float|int|string, fuente?: string}|null  $payloadEuro
     */
    private function persistirCotizacion(Carbon $fecha, array $payload, ?array $payloadEuro = null): bool
    {
        $venta = (float) $payload['venta'];
        $compra = (float) ($payload['compra'] ?? 0);
        $fuente = (string) ($payload['fuente'] ?? '');
        $monedaId = $this->monedaIdDolar();
        $usuario = (int) config('cotizacion.usuarioIdCommand');
        $fechaSql = $fecha->toDateString();
        $euro = $this->normalizarEuro($payloadEuro);

        if ($venta <= 0) {
            return false;
        }

        DB::beginTransaction();
        try {
            $cotizacion = DB::table('cotizacion')->whereDate('fecha', $fechaSql)->first();
            $alta = $cotizacion === null;

            if ($alta) {
                // Alta local directa: create() del repositorio también escribe Anita y
                // esta corrida decide aparte si el bridge corresponde.
                $cotizacionModel = Cotizacion::query()->create([
                    'fecha' => $fechaSql,
                    'usuario_id' => $usuario,
                ]);
                $cotizacionId = (int) $cotizacionModel->id;
            } else {
                $cotizacionId = (int) $cotizacion->id;
            }

            $this->guardarMoneda($cotizacionId, $monedaId, $venta, $compra);
            if ($euro !== null) {
                $this->guardarMoneda($cotizacionId, $euro['moneda_id'], $euro['venta'], $euro['compra']);
            }

            $porCodigo = [
                $monedaId => ['venta' => $venta, 'compra' => $compra],
            ];
            if ($euro !== null) {
                $porCodigo[$euro['moneda_id']] = [
                    'venta' => $euro['venta'],
                    'compra' => $euro['compra'],
                ];
            }
            $this->cotizacionRepository->grabarDiaApiEnAnita($fechaSql, $porCodigo);

            $contexto = [
                'id' => $cotizacionId,
                'fecha' => $fechaSql,
                'venta' => $venta,
                'compra' => $compra,
                'fuente' => $fuente,
                'euro_venta' => $euro['venta'] ?? null,
                'euro_compra' => $euro['compra'] ?? null,
                'euro_fuente' => $euro['fuente'] ?? null,
            ];
            Log::info($alta ? 'grabo cotizacion diaria' : 'actualizo cotizacion diaria', $contexto);
            $this->info($this->lineaResultado($alta ? 'Alta' : 'Update', $fechaSql, $venta, $compra, $fuente, $euro));

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
     * @param  array{venta: float|int|string, compra?: float|int|string, fuente?: string}|null  $payloadEuro
     * @return array{moneda_id: int, venta: float, compra: float, fuente: string}|null
     */
    private function normalizarEuro(?array $payloadEuro): ?array
    {
        $monedaEuroId = $this->monedaIdEuro();
        if ($monedaEuroId <= 0 || $monedaEuroId === $this->monedaIdDolar() || ! is_array($payloadEuro)) {
            return null;
        }
        $venta = (float) ($payloadEuro['venta'] ?? 0);
        if ($venta <= 0) {
            return null;
        }

        return [
            'moneda_id' => $monedaEuroId,
            'venta' => $venta,
            'compra' => (float) ($payloadEuro['compra'] ?? 0),
            'fuente' => (string) ($payloadEuro['fuente'] ?? ''),
        ];
    }

    private function guardarMoneda(int $cotizacionId, int $monedaId, float $venta, float $compra): void
    {
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

            return;
        }

        $this->cotizacion_MonedaRepository->create([
            'cotizacion_ids' => [$cotizacionId],
            'moneda_ids' => [$monedaId],
            'cotizacioncompras' => [$compra],
            'cotizacionventas' => [$venta],
        ], $cotizacionId);
    }

    /**
     * @param  array{moneda_id: int, venta: float, compra: float, fuente: string}|null  $euro
     */
    private function lineaResultado(string $accion, string $fechaSql, float $venta, float $compra, string $fuente, ?array $euro): string
    {
        $linea = "{$accion} {$fechaSql}: USD venta={$venta} compra={$compra} fuente={$fuente}";
        if ($euro === null) {
            return $linea.' EUR=sin dato';
        }

        return $linea." EUR venta={$euro['venta']} compra={$euro['compra']} fuente={$euro['fuente']}";
    }

    /**
     * @return list<string>
     */
    private function fechasConMonedaVacia(string $desde, string $hasta, int $monedaId): array
    {
        return DB::table('cotizacion as c')
            ->leftJoin('cotizacion_moneda as cm', function ($join) use ($monedaId) {
                $join->on('cm.cotizacion_id', '=', 'c.id')
                    ->where('cm.moneda_id', '=', $monedaId);
            })
            ->whereBetween('c.fecha', [$desde, $hasta])
            ->where(function ($q) {
                $q->whereNull('cm.id')
                    ->orWhereNull('cm.cotizacionventa')
                    ->orWhere('cm.cotizacionventa', '<=', 0);
            })
            ->orderBy('c.fecha')
            ->pluck('c.fecha')
            ->map(fn ($fecha) => Carbon::parse($fecha)->toDateString())
            ->unique()
            ->values()
            ->all();
    }

    private function monedaIdDolar(): int
    {
        return (int) config('cotizacion.monedaIdCommand', 2);
    }

    private function monedaIdEuro(): int
    {
        return (int) config('cotizacion.monedaIdEuroCommand', 3);
    }

    /**
     * @return array{venta?: float, compra?: float, fuente?: string, fecha?: string, sin_dato?: bool}|null
     */
    private function leerCotizacionBna(Carbon $fecha, string $moneda = 'usd', bool $avisar = true): ?array
    {
        $moneda = $moneda === 'eur' ? 'eur' : 'usd';
        $script = $this->resolverScriptCotizacionBna();
        if ($script === null) {
            if ($avisar) {
                $this->error('No existe parser: scripts/cotizacionbna.py ni storage/cotizacionbna.py');
            }

            return null;
        }

        $cmd = sprintf(
            'python3 %s --json --fecha %s --moneda %s 2>&1',
            escapeshellarg($script),
            escapeshellarg($fecha->toDateString()),
            escapeshellarg($moneda)
        );
        $raw = $this->ejecutarParser($cmd);

        if ($raw === '') {
            if ($avisar) {
                $this->error('Parser BNA sin salida para '.$fecha->toDateString());
            }

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
            if ($avisar) {
                $this->error('Parser BNA respuesta inválida: '.$raw);
            }

            return null;
        }

        $venta = (float) $data['venta'];
        if ($venta <= 0) {
            if ($avisar) {
                $this->error('Cotización venta inválida (<=0) para '.$fecha->toDateString());
            }

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

    private function ejecutarParser(string $cmd): string
    {
        $descriptores = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proceso = proc_open($cmd, $descriptores, $pipes, null, $this->entornoProxy());
        if (! is_resource($proceso)) {
            return '';
        }
        $salida = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proceso);

        return trim((is_string($salida) ? $salida : '')."\n".(is_string($error) ? $error : ''));
    }

    /**
     * @param  array<string, int|string>  $base
     * @return array<string, int|string|bool>
     */
    private function opcionesHttpProxy(array $base): array
    {
        $host = trim((string) config('cotizacion.proxy_host', ''));
        if ($host === '') {
            return $base;
        }
        $port = max(1, (int) config('cotizacion.proxy_port', 3128));
        $user = (string) config('cotizacion.proxy_user', '');
        $password = (string) config('cotizacion.proxy_password', '');
        $header = (string) ($base['header'] ?? '');
        if ($user !== '') {
            $header = 'Proxy-Authorization: Basic '.base64_encode($user.':'.$password)."\r\n".$header;
        }
        $base['header'] = $header;
        $base['proxy'] = 'tcp://'.$host.':'.$port;
        $base['request_fulluri'] = true;

        return $base;
    }

    /**
     * @return array<string, string>
     */
    private function entornoProxy(): array
    {
        $entorno = [];
        foreach (array_merge($_ENV, $_SERVER) as $clave => $valor) {
            if (is_string($clave) && (is_string($valor) || is_numeric($valor))) {
                $entorno[$clave] = (string) $valor;
            }
        }
        $entorno['COTIZACION_PROXY_HOST'] = (string) config('cotizacion.proxy_host', '');
        $entorno['COTIZACION_PROXY_PORT'] = (string) config('cotizacion.proxy_port', 3128);
        $entorno['COTIZACION_PROXY_USER'] = (string) config('cotizacion.proxy_user', '');
        $entorno['COTIZACION_PROXY_PASSWORD'] = (string) config('cotizacion.proxy_password', '');

        return $entorno;
    }
}
