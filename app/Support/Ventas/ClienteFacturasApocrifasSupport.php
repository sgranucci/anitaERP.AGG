<?php

namespace App\Support\Ventas;

use App\Models\Ventas\Cliente;
use App\Models\Ventas\Tiposuspensioncliente;
use App\Services\Arca\WsapocConsultaService;
use App\Traits\Ventas\ClienteTrait;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Consulta WSAPOC (facturas apócrifas) y suspensión de clientes.
 */
final class ClienteFacturasApocrifasSupport
{
    use ClienteTrait;

    public function __construct(
        private WsapocConsultaService $wsapocService,
    ) {}

    public function habilitado(): bool
    {
        return filter_var(config('arca_wsapoc.habilitado', true), FILTER_VALIDATE_BOOLEAN);
    }

    public function habilitadoParaAbm(): bool
    {
        return $this->habilitado()
            && filter_var(config('arca_wsapoc.validar_cliente_abm', true), FILTER_VALIDATE_BOOLEAN);
    }

    public function habilitadoParaFactura(): bool
    {
        return $this->habilitado()
            && filter_var(config('arca_wsapoc.validar_factura_cliente', true), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @return array<string, mixed>
     */
    public function evaluarCliente(Cliente $cliente, bool $suspenderSiApocrifo = false): array
    {
        if (! $this->habilitado()) {
            return [
                'aplica' => false,
                'ok' => true,
                'skipped' => true,
                'mensaje' => null,
                'detalles' => [],
                'es_apocrifo' => false,
                'debe_suspender' => false,
                'suspendido' => false,
            ];
        }

        $cuit = preg_replace('/\D+/', '', (string) $cliente->numerodocumento) ?? '';
        if (strlen($cuit) !== 11) {
            return [
                'aplica' => true,
                'ok' => false,
                'mensaje' => 'El cliente no tiene una CUIT válida (11 dígitos) para consultar facturas apócrifas en ARCA.',
                'detalles' => [],
                'es_apocrifo' => false,
                'debe_suspender' => false,
                'suspendido' => false,
            ];
        }

        try {
            $ws = $this->wsapocService->getPublicacionApoc($cuit);
        } catch (Throwable $e) {
            Log::warning('WSAPOC cliente: servicio no disponible', [
                'cliente_id' => $cliente->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'aplica' => true,
                'ok' => true,
                'error_servicio' => true,
                'mensaje' => WsapocConsultaService::mensajeAvisoNoDisponible(),
                'detalles' => [],
                'es_apocrifo' => false,
                'debe_suspender' => false,
                'suspendido' => false,
                'error' => $e->getMessage(),
            ];
        }

        return $this->evaluarRespuestaWs($cliente, $ws, $suspenderSiApocrifo);
    }

    /**
     * Procesa CUITs ya agrupados desde novedades WSAPOC (sin llamar al WS).
     *
     * @param  array<string, list<array<string, mixed>>>  $porCuit
     * @return array<string, mixed>
     */
    public function procesarCuitsDesdeNovedades(array $porCuit, bool $suspenderSiApocrifo = true): array
    {
        $apocrifos = 0;
        $suspendidos = 0;
        $errores = 0;
        $clientesCoincidentes = 0;
        $cuitsSinCliente = [];
        $clientesSuspendidos = [];

        foreach ($porCuit as $cuit => $pubsCuit) {
            $clientes = $this->buscarClientesPorCuit($cuit);
            if ($clientes->isEmpty()) {
                $cuitsSinCliente[] = $cuit;

                continue;
            }

            $wsCuit = [
                'codigo' => '0',
                'descripcion' => 'OK',
                'cuit_consultada' => $cuit,
                'publicaciones' => $pubsCuit,
                'es_apocrifo' => true,
                'ok' => true,
                'error_servicio' => false,
            ];

            foreach ($clientes as $cliente) {
                $clientesCoincidentes++;
                $eval = $this->evaluarRespuestaWs($cliente, $wsCuit, $suspenderSiApocrifo);
                if ($eval['es_apocrifo'] ?? false) {
                    $apocrifos++;
                    if ($eval['suspendido'] ?? false) {
                        $suspendidos++;
                        $clientesSuspendidos[] = $this->filaClienteSuspendido($cliente, $cuit, $pubsCuit, $eval);
                    }
                } elseif (! ($eval['ok'] ?? true)) {
                    $errores++;
                }
            }
        }

        return [
            'clientes_coincidentes' => $clientesCoincidentes,
            'apocrifos' => $apocrifos,
            'suspendidos' => $suspendidos,
            'errores' => $errores,
            'cuits_sin_cliente' => $cuitsSinCliente,
            'clientes_suspendidos' => $clientesSuspendidos,
        ];
    }

    /**
     * @param  list<array{cuit: string, descripcion: string|null, fecha_condicion: string|null, fecha_publicacion: string|null}>  $publicaciones
     * @param  array<string, mixed>  $eval
     * @return array<string, mixed>
     */
    public function filaClienteSuspendido(Cliente $cliente, string $cuit, array $publicaciones, array $eval): array
    {
        $pub = $publicaciones[0] ?? [];

        return [
            'id' => (int) $cliente->id,
            'codigo' => (string) ($cliente->codigo ?? ''),
            'nombre' => (string) ($cliente->nombre ?? ''),
            'cuit' => $cuit,
            'estado' => self::ESTADO_SUSPENDIDO,
            'fecha_publicacion' => $pub['fecha_publicacion'] ?? null,
            'fecha_condicion' => $pub['fecha_condicion'] ?? null,
            'descripcion_arca' => $pub['descripcion'] ?? null,
            'mensaje' => $eval['mensaje'] ?? null,
            'url_editar' => route('editar_cliente', ['id' => $cliente->id]),
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Cliente>
     */
    public function buscarClientesPorCuit(string $cuit): \Illuminate\Database\Eloquent\Collection
    {
        $digitos = preg_replace('/\D+/', '', $cuit) ?? '';

        return Cliente::query()
            ->whereRaw(
                "REPLACE(REPLACE(REPLACE(numerodocumento, '-', ''), '.', ''), ' ', '') = ?",
                [$digitos]
            )
            ->get();
    }

    /**
     * @param  array<string, mixed>  $ws
     * @return array<string, mixed>
     */
    public function evaluarRespuestaWs(Cliente $cliente, array $ws, bool $suspenderSiApocrifo = false): array
    {
        if ($ws['error_servicio'] ?? false) {
            return [
                'aplica' => true,
                'ok' => true,
                'error_servicio' => true,
                'mensaje' => WsapocConsultaService::mensajeAvisoNoDisponible(),
                'detalles' => [],
                'es_apocrifo' => false,
                'debe_suspender' => false,
                'suspendido' => false,
                'ws' => $ws,
            ];
        }

        $esApocrifo = (bool) ($ws['es_apocrifo'] ?? false);
        $detalles = $this->detallesDesdePublicaciones($ws['publicaciones'] ?? []);
        $mensaje = $esApocrifo
            ? $this->mensajeApocrifo($cliente, $ws)
            : 'El cliente no figura en la base de facturas apócrifas de ARCA (consulta '.now()->format('d/m/Y H:i').').';

        $debeSuspender = $esApocrifo
            && filter_var(config('arca_wsapoc.suspender_automatico', true), FILTER_VALIDATE_BOOLEAN);

        $suspendido = false;
        if ($debeSuspender && $suspenderSiApocrifo) {
            $suspendido = $this->aplicarSuspension($cliente, $ws, $mensaje);
        } else {
            $this->persistirConsulta($cliente->id, $esApocrifo, $ws);
        }

        return [
            'aplica' => true,
            'ok' => ! $esApocrifo,
            'mensaje' => $mensaje,
            'detalles' => $detalles,
            'es_apocrifo' => $esApocrifo,
            'debe_suspender' => $debeSuspender,
            'suspendido' => $suspendido,
            'tiposuspension_id' => $debeSuspender ? $this->resolverTiposuspensionId() : null,
            'ws' => $ws,
        ];
    }

    /**
     * @param  list<array{cuit: string, descripcion: string|null, fecha_condicion: string|null, fecha_publicacion: string|null}>  $publicaciones
     * @return list<string>
     */
    private function detallesDesdePublicaciones(array $publicaciones): array
    {
        $detalles = [];
        foreach ($publicaciones as $pub) {
            $partes = array_filter([
                isset($pub['fecha_publicacion']) ? 'Publicación: '.$pub['fecha_publicacion'] : null,
                isset($pub['fecha_condicion']) ? 'Condición: '.$pub['fecha_condicion'] : null,
                isset($pub['descripcion']) && $pub['descripcion'] !== '' ? $pub['descripcion'] : null,
            ]);
            if ($partes !== []) {
                $detalles[] = implode(' — ', $partes);
            }
        }

        return $detalles;
    }

    /**
     * @param  array<string, mixed>  $ws
     */
    private function mensajeApocrifo(Cliente $cliente, array $ws): string
    {
        $nombre = trim((string) $cliente->nombre);
        $cuit = preg_replace('/\D+/', '', (string) $cliente->numerodocumento) ?? '';
        $pub = ($ws['publicaciones'] ?? [])[0] ?? null;
        $fechaPub = is_array($pub) ? ($pub['fecha_publicacion'] ?? null) : null;

        $texto = 'El cliente';
        if ($nombre !== '') {
            $texto .= ' «'.$nombre.'»';
        }
        if ($cuit !== '') {
            $texto .= ' (CUIT '.$cuit.')';
        }
        $texto .= ' figura en la base de contribuyentes con facturas apócrifas de ARCA (WSAPOC).';
        if ($fechaPub) {
            $texto .= ' Fecha de publicación: '.$fechaPub.'.';
        }
        $texto .= ' Se suspendió automáticamente hasta regularizar la situación ante AFIP/ARCA.';

        return $texto;
    }

    /**
     * @param  array<string, mixed>  $ws
     */
    private function aplicarSuspension(Cliente $cliente, array $ws, string $mensaje): bool
    {
        $tipoId = $this->resolverTiposuspensionId();
        $update = [
            'facturas_apocrifas' => true,
            'facturas_apocrifas_consulta_at' => now(),
            'facturas_apocrifas_detalle' => json_encode([
                'mensaje' => $mensaje,
                'publicaciones' => $ws['publicaciones'] ?? [],
                'codigo' => $ws['codigo'] ?? null,
                'descripcion' => $ws['descripcion'] ?? null,
            ], JSON_UNESCAPED_UNICODE),
            'estado' => self::ESTADO_SUSPENDIDO,
        ];

        if ($tipoId > 0) {
            $update['tiposuspension_id'] = $tipoId;
        }

        Cliente::query()->whereKey($cliente->id)->update($update);

        return true;
    }

    /**
     * @param  array<string, mixed>  $ws
     */
    private function persistirConsulta(int $clienteId, bool $esApocrifo, array $ws): void
    {
        Cliente::query()->whereKey($clienteId)->update([
            'facturas_apocrifas' => $esApocrifo,
            'facturas_apocrifas_consulta_at' => now(),
            'facturas_apocrifas_detalle' => $esApocrifo
                ? json_encode(['publicaciones' => $ws['publicaciones'] ?? []], JSON_UNESCAPED_UNICODE)
                : null,
        ]);
    }

    public function resolverTiposuspensionId(): int
    {
        $configId = (int) config('arca_wsapoc.tiposuspension_cliente_id', 0);
        if ($configId > 0) {
            return $configId;
        }

        $nombre = trim((string) config('arca_wsapoc.tiposuspension_cliente_nombre', 'Facturas apócrifas (ARCA APOC)'));
        if ($nombre === '') {
            return 0;
        }

        $existente = Tiposuspensioncliente::query()->where('nombre', $nombre)->value('id');
        if ($existente) {
            return (int) $existente;
        }

        $creado = Tiposuspensioncliente::query()->create(['nombre' => $nombre]);

        return (int) $creado->id;
    }
}
