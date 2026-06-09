<?php

namespace App\Services\Ventas\Gastronomia;

use App\Models\Configuracion\Salida;
use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Venta;
use InvalidArgumentException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Diagnóstico de latencia del ticket térmico gastronomía (generación + red + impresora).
 */
final class GastronomiaTicketDiagnosticoService
{
    private const PUERTO_IMPRESORA_DEFAULT = 9100;

    private const UMBRAL_GENERACION_MS = 500.;

    private const UMBRAL_RED_MS = 500.;

    private const UMBRAL_IMPRESION_MS = 3000.;

    public function __construct(
        private readonly GastronomiaFacturaTicketService $ticketService,
    ) {
    }

    /**
     * @param  array{cfg_id?:int,identificador_pc?:string,puntoventa_codigo?:string,venta_id?:int,imprimir?:bool}  $opciones
     * @return array<string, mixed>
     */
    public function medir(array $opciones = []): array
    {
        $cfg = $this->resolverConfiguracion($opciones);
        $cfg->loadMissing(['salidaFactura', 'puntoventaCae', 'puntoventaCaea', 'empresa']);

        $salida = $cfg->salidaFactura;
        if (! $salida instanceof Salida) {
            throw new InvalidArgumentException('No hay salida de facturas configurada en el PV gastronomía.');
        }

        $comando = trim((string) $salida->comando);
        if ($comando === '' || ! str_contains($comando, '%s')) {
            throw new InvalidArgumentException('El comando de salida de facturas debe incluir %s (ruta del ticket).');
        }

        $hostImpresora = $this->extraerHostImpresora($comando);
        $pvCae = $cfg->puntoventaCae;
        $ventaId = (int) ($opciones['venta_id'] ?? 0);
        if ($ventaId <= 0 && $pvCae instanceof Puntoventa) {
            $ventaId = $this->ultimaVentaIdPorPuntoventa((int) $pvCae->id);
        }

        $latencias = [];
        $errores = [];
        $bytes = null;
        $rutaTemporal = null;

        if ($ventaId > 0) {
            $t0 = microtime(true);
            try {
                $venta = Venta::query()
                    ->with([
                        'puntoventas.empresas',
                        'puntoventas.localidades',
                        'tipotransacciones',
                        'clientes.condicionivas',
                        'venta_emisiones.articulos',
                        'venta_impuestos',
                    ])
                    ->find($ventaId);
                if (! $venta) {
                    throw new InvalidArgumentException('Venta '.$ventaId.' no encontrada.');
                }
                $bytes = $this->ticketService->generarBytesTicket($venta, null);
                $latencias['generar_bytes_ms'] = round((microtime(true) - $t0) * 1000, 2);
                $latencias['ticket_bytes'] = strlen($bytes);
            } catch (Throwable $e) {
                $latencias['generar_bytes_ms'] = round((microtime(true) - $t0) * 1000, 2);
                $errores[] = 'Generación ticket: '.$e->getMessage();
            }
        } else {
            $errores[] = 'Sin venta de referencia: omitida medición de generación de bytes.';
        }

        if ($hostImpresora !== '') {
            $dns = $this->medirResolucionHost($hostImpresora);
            $latencias['dns_ms'] = $dns['ms'];
            if ($dns['ip'] !== null) {
                $latencias['host_resuelto'] = $dns['ip'];
            }
            if ($dns['error'] !== null) {
                $errores[] = 'DNS: '.$dns['error'];
            }

            $tcp = $this->medirPuertoTcp($dns['ip'] ?? $hostImpresora, self::PUERTO_IMPRESORA_DEFAULT);
            $latencias['tcp_9100_ms'] = $tcp['ms'];
            $latencias['tcp_9100_ok'] = $tcp['ok'];
            if ($tcp['error'] !== null) {
                $errores[] = 'TCP 9100: '.$tcp['error'];
            }
        } else {
            $errores[] = 'No se pudo extraer host de impresora del comando de salida.';
        }

        $imprimir = (bool) ($opciones['imprimir'] ?? false);
        if ($imprimir && $bytes !== null && $bytes !== '') {
            $rutaTemporal = $this->guardarTemporal($ventaId, $bytes);
            $t0 = microtime(true);
            try {
                $this->ejecutarComandoSalida($comando, $rutaTemporal);
                $latencias['comando_impresion_ms'] = round((microtime(true) - $t0) * 1000, 2);
                $latencias['impresion_ejecutada'] = true;
            } catch (Throwable $e) {
                $latencias['comando_impresion_ms'] = round((microtime(true) - $t0) * 1000, 2);
                $latencias['impresion_ejecutada'] = false;
                $errores[] = 'Impresión: '.$e->getMessage();
            } finally {
                if ($rutaTemporal !== null) {
                    @unlink($rutaTemporal);
                }
            }
        } else {
            $latencias['impresion_ejecutada'] = false;
        }

        $latencias['total_medido_ms'] = round(array_sum(array_filter(
            [
                $latencias['generar_bytes_ms'] ?? 0.,
                $latencias['dns_ms'] ?? 0.,
                $latencias['tcp_9100_ms'] ?? 0.,
                $latencias['comando_impresion_ms'] ?? 0.,
            ],
            fn ($v) => is_numeric($v),
        )), 2);

        return [
            'cfg_id' => (int) $cfg->id,
            'descripcion' => (string) $cfg->descripcion,
            'identificador_pc' => (string) $cfg->identificador_pc,
            'empresa_id' => (int) $cfg->empresa_id,
            'empresa_nombre' => $cfg->empresa->nombre ?? null,
            'puntoventa_cae_codigo' => $pvCae->codigo ?? null,
            'puntoventa_cae_nombre' => $pvCae->nombre ?? null,
            'venta_referencia_id' => $ventaId > 0 ? $ventaId : null,
            'salida_factura' => [
                'id' => (int) $salida->id,
                'nombre' => (string) $salida->nombre,
                'comando' => $comando,
                'host_impresora' => $hostImpresora,
            ],
            'config_ticket' => [
                'impresion_automatica' => (bool) config('gastronomia.ticket_impresion_automatica', true),
                'impresion_async' => (bool) config('gastronomia.ticket_impresion_async', true),
                'comando_timeout_segundos' => (int) config('gastronomia.ticket_comando_timeout_segundos', 30),
            ],
            'latencias_ms' => $latencias,
            'errores' => $errores,
            'interpretacion' => $this->interpretar($latencias, $errores),
        ];
    }

    /**
     * @param  array{cfg_id?:int,identificador_pc?:string,puntoventa_codigo?:string}  $opciones
     */
    public function resolverConfiguracion(array $opciones): ConfiguracionPuntoventaGastronomia
    {
        $cfgId = (int) ($opciones['cfg_id'] ?? 0);
        if ($cfgId > 0) {
            $cfg = ConfiguracionPuntoventaGastronomia::query()->find($cfgId);
            if ($cfg) {
                return $cfg;
            }
            throw new InvalidArgumentException('Configuración PV gastronomía #'.$cfgId.' inexistente.');
        }

        $pc = trim((string) ($opciones['identificador_pc'] ?? ''));
        if ($pc !== '') {
            $cfg = ConfiguracionPuntoventaGastronomia::query()
                ->where('identificador_pc', $pc)
                ->first();
            if ($cfg) {
                return $cfg;
            }
            throw new InvalidArgumentException('Sin configuración PV gastronomía para identificador_pc: '.$pc);
        }

        $codigoPv = $this->normalizarCodigoPuntoventa((string) ($opciones['puntoventa_codigo'] ?? ''));
        if ($codigoPv !== '') {
            $pv = Puntoventa::query()->where('codigo', $codigoPv)->first();
            if (! $pv) {
                throw new InvalidArgumentException('Punto de venta con código '.$codigoPv.' inexistente.');
            }

            $cfg = ConfiguracionPuntoventaGastronomia::query()
                ->where(function ($q) use ($pv): void {
                    $q->where('puntoventa_cae_id', $pv->id)
                        ->orWhere('puntoventa_caea_id', $pv->id);
                })
                ->orderBy('id')
                ->first();

            if ($cfg) {
                return $cfg;
            }

            throw new InvalidArgumentException(
                'Sin terminal gastronomía con CAE/CAEA en punto de venta '.$codigoPv.'.',
            );
        }

        throw new InvalidArgumentException(
            'Indique --cfg-id, --identificador-pc o --pv-codigo (ej. 00014 para Kandiko PV 14).',
        );
    }

    /**
     * @param  array<string, mixed>  $latencias
     * @param  list<string>  $errores
     */
    private function interpretar(array $latencias, array $errores): string
    {
        if ($errores !== []) {
            return 'Hay fallos en la cadena de impresión: '.implode(' ', $errores)
                .' Revise salida_factura, /etc/hosts y conectividad al puerto 9100.';
        }

        $gen = (float) ($latencias['generar_bytes_ms'] ?? 0.);
        $tcp = (float) ($latencias['tcp_9100_ms'] ?? 0.);
        $imp = (float) ($latencias['comando_impresion_ms'] ?? 0.);
        $async = (bool) config('gastronomia.ticket_impresion_async', true);

        $partes = [];
        if ($gen > self::UMBRAL_GENERACION_MS) {
            $partes[] = 'generación de ticket lenta ('.$gen.' ms)';
        }
        if ($tcp > self::UMBRAL_RED_MS) {
            $partes[] = 'red/impresora lenta ('.$tcp.' ms hasta TCP 9100)';
        }
        if ($imp > self::UMBRAL_IMPRESION_MS) {
            $partes[] = 'comando de impresión lento ('.$imp.' ms)';
        }

        if ($partes === []) {
            $notaAsync = $async
                ? ' El POS responde antes de imprimir (ticket_impresion_async=true).'
                : ' El ticket bloquea la respuesta al POS (ticket_impresion_async=false).';

            return 'Tiempos dentro de lo esperado.'.$notaAsync;
        }

        return 'Cuello de botella probable: '.implode('; ', $partes).'.'
            .($async
                ? ' Con impresión async el POS no debería notar la lentitud de la impresora.'
                : ' Con impresión sincrónica esto retrasa la respuesta al POS.');
    }

    private function normalizarCodigoPuntoventa(string $codigo): string
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return '';
        }
        if (ctype_digit($codigo)) {
            return str_pad($codigo, 5, '0', STR_PAD_LEFT);
        }

        return $codigo;
    }

    private function ultimaVentaIdPorPuntoventa(int $puntoventaId): int
    {
        return (int) (Venta::query()
            ->where('puntoventa_id', $puntoventaId)
            ->orderByDesc('id')
            ->value('id') ?? 0);
    }

    private function extraerHostImpresora(string $comando): string
    {
        if (preg_match('/ncjetdirect\s+"%s"\s+(\S+)/i', $comando, $m)) {
            return trim($m[1], '"\'');
        }
        if (preg_match('/gastronomia-print-ticket\.sh\s+"%s"\s+(\S+)/i', $comando, $m)) {
            return trim($m[1], '"\'');
        }

        $tokens = preg_split('/\s+/', $comando) ?: [];
        $ultimo = trim((string) end($tokens), '"\'');

        return $ultimo === '%s' ? '' : $ultimo;
    }

    /**
     * @return array{ms:float,ip:?string,error:?string}
     */
    private function medirResolucionHost(string $host): array
    {
        $t0 = microtime(true);
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [
                'ms' => round((microtime(true) - $t0) * 1000, 2),
                'ip' => $host,
                'error' => null,
            ];
        }

        $ip = gethostbyname($host);
        $ms = round((microtime(true) - $t0) * 1000, 2);
        if ($ip === $host) {
            return ['ms' => $ms, 'ip' => null, 'error' => 'Host no resuelve: '.$host];
        }

        return ['ms' => $ms, 'ip' => $ip, 'error' => null];
    }

    /**
     * @return array{ms:float,ok:bool,error:?string}
     */
    private function medirPuertoTcp(string $host, int $puerto): array
    {
        $t0 = microtime(true);
        $errno = 0;
        $errstr = '';
        $fp = @fsockopen($host, $puerto, $errno, $errstr, 3.);
        $ms = round((microtime(true) - $t0) * 1000, 2);
        if ($fp === false) {
            return [
                'ms' => $ms,
                'ok' => false,
                'error' => trim($errstr) !== '' ? $errstr : 'errno '.$errno,
            ];
        }
        fclose($fp);

        return ['ms' => $ms, 'ok' => true, 'error' => null];
    }

    private function guardarTemporal(int $ventaId, string $bytes): string
    {
        $dir = storage_path('app/gastronomia/tickets');
        if (! is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        if (! is_dir($dir) || ! is_writable($dir)) {
            $dir = rtrim(sys_get_temp_dir(), '/');
        }

        $ruta = $dir.'/diag-ticket-'.$ventaId.'-'.time().'.bin';
        if (@file_put_contents($ruta, $bytes) === false) {
            throw new InvalidArgumentException('No se pudo escribir el archivo de ticket de prueba en '.$dir.'.');
        }

        return $ruta;
    }

    private function ejecutarComandoSalida(string $comandoPlantilla, string $rutaArchivo): void
    {
        $comando = sprintf($comandoPlantilla, $rutaArchivo);
        $process = Process::fromShellCommandline($comando);
        $process->setTimeout((int) config('gastronomia.ticket_comando_timeout_segundos', 30));
        $process->run();

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }
}
