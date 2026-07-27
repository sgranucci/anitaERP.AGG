<?php

namespace App\Console\Commands;

use App\Models\Compras\Proveedor;
use App\Models\Ventas\Cliente;
use App\Services\Compras\ProveedorFacturasApocrifasNotificacionService;
use App\Support\Ai\AiAgenteEventoDispatcherSupport;
use App\Support\Ai\AiAgenteOperativoSupport;
use App\Support\Compras\ProveedorFacturasApocrifasSupport;
use App\Support\Ventas\ClienteFacturasApocrifasSupport;
use App\Traits\Compras\ProveedorTrait;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AuditarProveedoresFacturasApocrifasCommand extends Command
{
    use ProveedorTrait;

    protected $signature = 'arca:auditar-proveedores-facturas-apocrifas
                            {--proveedor-id= : Auditar un proveedor puntual (GetPublicacionAPOC)}
                            {--modo= : novedades|completo (default: config arca_wsapoc.auditoria_nocturna.modo)}
                            {--desde= : Fecha desde DD/MM/YYYY (solo modo novedades)}
                            {--hasta= : Fecha hasta DD/MM/YYYY (solo modo novedades)}
                            {--limit=0 : Máximo de proveedores a procesar en modo completo (0 = sin límite)}
                            {--sin-mail : No envía correo aunque haya suspensiones}';

    protected $description = 'Consulta WSAPOC: novedades por fecha (nocturno) o barrido completo por proveedor/cliente';

    public function handle(
        ProveedorFacturasApocrifasSupport $support,
        ProveedorFacturasApocrifasNotificacionService $notificacion,
    ): int {
        if (! filter_var(config('arca_wsapoc.auditoria_nocturna.habilitada', true), FILTER_VALIDATE_BOOLEAN)) {
            $this->line('Auditoría WSAPOC deshabilitada (ARCA_WSAPOC_AUDITORIA_NOCTURNA=false).');

            return self::SUCCESS;
        }

        if (! $support->habilitado()) {
            $this->line('WSAPOC deshabilitado (ARCA_WSAPOC_HABILITADO=false).');

            return self::SUCCESS;
        }

        $proveedorId = (int) $this->option('proveedor-id');
        if ($proveedorId > 0) {
            return $this->auditarProveedorPuntual($support, $proveedorId);
        }

        $modo = strtolower(trim((string) ($this->option('modo') ?: config('arca_wsapoc.auditoria_nocturna.modo', 'novedades'))));
        if ($modo === 'completo') {
            return $this->auditarModoCompleto($support, $notificacion);
        }

        return $this->auditarModoNovedades($support, $notificacion);
    }

    private function auditarProveedorPuntual(ProveedorFacturasApocrifasSupport $support, int $proveedorId): int
    {
        $proveedor = Proveedor::query()->find($proveedorId);
        if (! $proveedor) {
            $this->error("Proveedor #{$proveedorId} inexistente.");

            return self::FAILURE;
        }

        $eval = $support->evaluarProveedor($proveedor, suspenderSiApocrifo: true);
        if ($eval['es_apocrifo'] ?? false) {
            $this->warn("APOC proveedor #{$proveedor->id} {$proveedor->nombre}");
        } elseif (! ($eval['ok'] ?? true)) {
            $this->error($eval['mensaje'] ?? 'Error en consulta WSAPOC.');

            return self::FAILURE;
        } else {
            $this->info("Proveedor #{$proveedor->id} sin registro APOC.");
        }

        return self::SUCCESS;
    }

    private function auditarModoNovedades(
        ProveedorFacturasApocrifasSupport $support,
        ProveedorFacturasApocrifasNotificacionService $notificacion,
    ): int {
        [$desde, $hasta] = $this->resolverRangoFechas();

        $this->line("Modo novedades WSAPOC: {$desde} → {$hasta} (GetAllByPublicacion)");

        $resultado = $support->procesarNovedadesPorRango($desde, $hasta, suspenderSiApocrifo: true);
        $resultado['modo'] = 'novedades';

        if (! ($resultado['ok'] ?? false)) {
            $this->error($resultado['mensaje'] ?? 'Fallo al consultar novedades WSAPOC.');

            return self::FAILURE;
        }

        if ($resultado['skipped'] ?? false) {
            $this->line('WSAPOC omitido (deshabilitado).');

            return self::SUCCESS;
        }

        $this->informarResumenNovedades($resultado, $desde, $hasta);
        $this->enviarMailSuspensiones($notificacion, $resultado);
        $this->registrarEventoIaApocrifas($resultado);

        return self::SUCCESS;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolverRangoFechas(): array
    {
        $desdeOpt = trim((string) $this->option('desde'));
        $hastaOpt = trim((string) $this->option('hasta'));

        if ($desdeOpt !== '' && $hastaOpt !== '') {
            return [$desdeOpt, $hastaOpt];
        }

        $diasVentana = max(1, (int) config('arca_wsapoc.auditoria_nocturna.dias_ventana', 2));
        $hasta = Carbon::yesterday();
        $desde = Carbon::yesterday()->subDays($diasVentana - 1);

        return [$desde->format('d/m/Y'), $hasta->format('d/m/Y')];
    }

    private function auditarModoCompleto(
        ProveedorFacturasApocrifasSupport $support,
        ProveedorFacturasApocrifasNotificacionService $notificacion,
    ): int {
        $this->line('Modo completo: GetPublicacionAPOC por cada proveedor y cliente (reconciliación).');

        $clienteSupport = app(ClienteFacturasApocrifasSupport::class);
        $limit = max(0, (int) $this->option('limit'));
        $pausaMs = max(0, (int) config('arca_wsapoc.auditoria_nocturna.pausa_ms', 250));
        $soloActivos = filter_var(config('arca_wsapoc.auditoria_nocturna.solo_activos', true), FILTER_VALIDATE_BOOLEAN);

        $procesados = 0;
        $apocrifos = 0;
        $suspendidos = 0;
        $errores = 0;
        $proveedoresSuspendidos = [];
        $clientesSuspendidos = [];

        $queryProveedor = Proveedor::query()
            ->whereNotNull('nroinscripcion')
            ->whereRaw("LENGTH(REPLACE(REPLACE(REPLACE(nroinscripcion, '-', ''), '.', ''), ' ', '')) = 11");

        if ($soloActivos) {
            $queryProveedor->whereIn('estado', self::$estadosHabilitadosOperacion);
        }

        $queryProveedor->orderBy('id')->chunkById(50, function ($proveedores) use (
            $support,
            $pausaMs,
            $limit,
            &$procesados,
            &$apocrifos,
            &$suspendidos,
            &$errores,
            &$proveedoresSuspendidos
        ) {
            foreach ($proveedores as $proveedor) {
                if ($limit > 0 && $procesados >= $limit) {
                    return false;
                }

                $eval = $support->evaluarProveedor($proveedor, suspenderSiApocrifo: true);
                $procesados++;

                if ($eval['es_apocrifo'] ?? false) {
                    $apocrifos++;
                    if ($eval['suspendido'] ?? false) {
                        $suspendidos++;
                        $cuit = preg_replace('/\D+/', '', (string) $proveedor->nroinscripcion) ?? '';
                        $pubs = ($eval['ws']['publicaciones'] ?? []);
                        $proveedoresSuspendidos[] = $support->filaProveedorSuspendido($proveedor, $cuit, $pubs, $eval);
                    }
                    $this->warn("APOC proveedor #{$proveedor->id} {$proveedor->nombre}");
                } elseif (! ($eval['ok'] ?? true)) {
                    $errores++;
                    $this->error("Error proveedor #{$proveedor->id}: ".($eval['mensaje'] ?? 'sin detalle'));
                }

                if ($pausaMs > 0) {
                    usleep($pausaMs * 1000);
                }
            }

            return true;
        });

        $procesadosClientes = 0;
        $queryCliente = Cliente::query()
            ->whereNotNull('numerodocumento')
            ->whereRaw("LENGTH(REPLACE(REPLACE(REPLACE(numerodocumento, '-', ''), '.', ''), ' ', '')) = 11");

        if ($soloActivos) {
            $queryCliente->whereIn('estado', Cliente::estadosHabilitadosFacturacion());
        }

        $queryCliente->orderBy('id')->chunkById(50, function ($clientes) use (
            $clienteSupport,
            $pausaMs,
            $limit,
            &$procesadosClientes,
            &$apocrifos,
            &$suspendidos,
            &$errores,
            &$clientesSuspendidos
        ) {
            foreach ($clientes as $cliente) {
                if ($limit > 0 && $procesadosClientes >= $limit) {
                    return false;
                }

                $eval = $clienteSupport->evaluarCliente($cliente, suspenderSiApocrifo: true);
                $procesadosClientes++;

                if ($eval['es_apocrifo'] ?? false) {
                    $apocrifos++;
                    if ($eval['suspendido'] ?? false) {
                        $suspendidos++;
                        $cuit = preg_replace('/\D+/', '', (string) $cliente->numerodocumento) ?? '';
                        $pubs = ($eval['ws']['publicaciones'] ?? []);
                        $clientesSuspendidos[] = $clienteSupport->filaClienteSuspendido($cliente, $cuit, $pubs, $eval);
                    }
                    $this->warn("APOC cliente #{$cliente->id} {$cliente->nombre}");
                } elseif (! ($eval['ok'] ?? true)) {
                    $errores++;
                    $this->error("Error cliente #{$cliente->id}: ".($eval['mensaje'] ?? 'sin detalle'));
                }

                if ($pausaMs > 0) {
                    usleep($pausaMs * 1000);
                }
            }

            return true;
        });

        $resumen = "WSAPOC completo: proveedores={$procesados} clientes={$procesadosClientes} apocrifos={$apocrifos} suspendidos={$suspendidos} errores={$errores}";
        $this->info($resumen);
        Log::info('arca:auditar-proveedores-facturas-apocrifas — '.$resumen);

        $informeCompleto = [
            'modo' => 'completo',
            'desde' => now()->format('d/m/Y'),
            'hasta' => now()->format('d/m/Y'),
            'publicaciones_ws' => $apocrifos,
            'cuits_novedad' => $apocrifos,
            'proveedores_coincidentes' => $procesados,
            'clientes_coincidentes' => $procesadosClientes,
            'apocrifos' => $apocrifos,
            'suspendidos' => $suspendidos,
            'errores' => $errores,
            'proveedores_suspendidos' => $proveedoresSuspendidos,
            'clientes_suspendidos' => $clientesSuspendidos,
        ];
        $this->enviarMailSuspensiones($notificacion, $informeCompleto);
        $this->registrarEventoIaApocrifas($informeCompleto);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $resultado
     */
    private function informarResumenNovedades(array $resultado, string $desde, string $hasta): void
    {
        $resumen = sprintf(
            'WSAPOC novedades %s→%s: publicaciones=%d cuits=%d proveedores=%d clientes=%d apocrifos=%d (P:%d C:%d) suspendidos=%d (P:%d C:%d) errores=%d sin_proveedor=%d sin_cliente=%d',
            $desde,
            $hasta,
            (int) ($resultado['publicaciones_ws'] ?? 0),
            (int) ($resultado['cuits_novedad'] ?? 0),
            (int) ($resultado['proveedores_coincidentes'] ?? 0),
            (int) ($resultado['clientes_coincidentes'] ?? 0),
            (int) ($resultado['apocrifos'] ?? 0),
            (int) ($resultado['apocrifos_proveedores'] ?? 0),
            (int) ($resultado['apocrifos_clientes'] ?? 0),
            (int) ($resultado['suspendidos'] ?? 0),
            (int) ($resultado['suspendidos_proveedores'] ?? 0),
            (int) ($resultado['suspendidos_clientes'] ?? 0),
            (int) ($resultado['errores'] ?? 0),
            count($resultado['cuits_sin_proveedor'] ?? []),
            count($resultado['cuits_sin_cliente'] ?? [])
        );

        $this->info($resumen);
        Log::info('arca:auditar-proveedores-facturas-apocrifas — '.$resumen);

        $sinProveedor = $resultado['cuits_sin_proveedor'] ?? [];
        if ($sinProveedor !== []) {
            $this->warn('CUITs en novedades APOC sin proveedor en ERP: '.implode(', ', array_slice($sinProveedor, 0, 20))
                .(count($sinProveedor) > 20 ? '…' : ''));
        }

        $sinCliente = $resultado['cuits_sin_cliente'] ?? [];
        if ($sinCliente !== []) {
            $this->warn('CUITs en novedades APOC sin cliente en ERP: '.implode(', ', array_slice($sinCliente, 0, 20))
                .(count($sinCliente) > 20 ? '…' : ''));
        }
    }

    /**
     * @param  array<string, mixed>  $informe
     */
    private function enviarMailSuspensiones(
        ProveedorFacturasApocrifasNotificacionService $notificacion,
        array $informe,
    ): void {
        if ($this->option('sin-mail')) {
            $this->comment('Correo omitido (--sin-mail).');

            return;
        }

        $mail = $notificacion->enviarSiCorresponde($informe);
        if ($mail['enviado'] ?? false) {
            $this->info('Correo enviado a '.implode(', ', $mail['destinatarios'] ?? []));

            return;
        }

        $motivo = (string) ($mail['motivo'] ?? '');
        if ($motivo === 'sin_suspensiones') {
            return;
        }

        if ($motivo === 'sin_destinatarios') {
            $this->warn('Sin destinatario (ARCA_WSAPOC_MAIL_DESTINATARIOS).');

            return;
        }

        if ($motivo === 'deshabilitado') {
            $this->comment('Mail WSAPOC deshabilitado (ARCA_WSAPOC_MAIL_HABILITADO=false).');

            return;
        }

        if ($motivo === 'error_envio') {
            $this->error('Fallo al enviar correo: '.($mail['error'] ?? ''));
        }
    }

    /**
     * Puente HITL: si hubo apócrifos/suspensiones, deja plan en ai_agente_evento.
     *
     * @param  array<string, mixed>  $informe
     */
    private function registrarEventoIaApocrifas(array $informe): void
    {
        $apocrifos = (int) ($informe['apocrifos'] ?? 0);
        $suspendidos = (int) ($informe['suspendidos'] ?? 0);
        if ($apocrifos <= 0 && $suspendidos <= 0) {
            return;
        }

        $codigoEjemplo = null;
        $prov = $informe['proveedores_suspendidos'][0] ?? null;
        if (is_array($prov)) {
            $codigoEjemplo = $prov['codigo'] ?? $prov['proveedor_codigo'] ?? null;
        }

        $evento = AiAgenteEventoDispatcherSupport::registrar([
            'evento' => AiAgenteOperativoSupport::EVENTO_FACTURA_APOCRIFA,
            'origen' => 'arca:auditar-proveedores-facturas-apocrifas',
            'severidad' => $suspendidos > 0 ? 'alta' : 'media',
            'resumen' => sprintf(
                'WSAPOC %s: apócrifos=%d suspendidos=%d',
                (string) ($informe['modo'] ?? 'novedades'),
                $apocrifos,
                $suspendidos
            ),
            'payload' => [
                'modo' => $informe['modo'] ?? null,
                'apocrifos' => $apocrifos,
                'suspendidos' => $suspendidos,
                'errores' => (int) ($informe['errores'] ?? 0),
            ],
            'plan_params' => [
                'codigo' => $codigoEjemplo,
                'valor' => $codigoEjemplo,
            ],
        ]);

        if ($evento) {
            $this->comment('Evento IA HITL #'.$evento->id.' registrado (factura_apocrifa).');
        }
    }
}
