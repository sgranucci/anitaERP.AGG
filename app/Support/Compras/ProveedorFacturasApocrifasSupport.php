<?php

namespace App\Support\Compras;

use App\Models\Compras\Proveedor;
use App\Models\Compras\Tiposuspensionproveedor;
use App\Services\Arca\WsapocConsultaService;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Consulta WSAPOC (facturas apócrifas) y suspensión de proveedores.
 */
final class ProveedorFacturasApocrifasSupport
{
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
            && filter_var(config('arca_wsapoc.validar_proveedor_abm', true), FILTER_VALIDATE_BOOLEAN);
    }

    public function habilitadoParaComprobante(): bool
    {
        return $this->habilitado()
            && filter_var(config('arca_wsapoc.validar_comprobante_proveedor', true), FILTER_VALIDATE_BOOLEAN);
    }

    public function habilitadoParaPrecargaIa(): bool
    {
        return $this->habilitado()
            && filter_var(config('arca_wsapoc.validar_precarga_ia', true), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Hook para pagos futuros: re-consulta y bloquea si el proveedor está en APOC.
     *
     * @throws RuntimeException
     */
    public function validarProveedorAntesPago(int $proveedorId): void
    {
        if (! $this->habilitado()) {
            return;
        }

        $proveedor = Proveedor::query()->find($proveedorId);
        if (! $proveedor) {
            throw new RuntimeException('Proveedor inexistente.');
        }

        $eval = $this->evaluarProveedor($proveedor, suspenderSiApocrifo: true);
        if ($eval['es_apocrifo'] ?? false) {
            throw new RuntimeException($eval['mensaje'] ?? 'El proveedor figura en la base de facturas apócrifas de ARCA.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function evaluarProveedor(Proveedor $proveedor, bool $suspenderSiApocrifo = false): array
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

        $cuit = preg_replace('/\D+/', '', (string) $proveedor->nroinscripcion) ?? '';
        if (strlen($cuit) !== 11) {
            return [
                'aplica' => true,
                'ok' => false,
                'mensaje' => 'El proveedor no tiene una CUIT válida (11 dígitos) para consultar facturas apócrifas en ARCA.',
                'detalles' => [],
                'es_apocrifo' => false,
                'debe_suspender' => false,
                'suspendido' => false,
            ];
        }

        try {
            $ws = $this->wsapocService->getPublicacionApoc($cuit);
        } catch (Throwable $e) {
            Log::warning('WSAPOC proveedor: servicio no disponible', [
                'proveedor_id' => $proveedor->id,
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

        return $this->evaluarRespuestaWs($proveedor, $ws, $suspenderSiApocrifo);
    }

    /**
     * Procesa novedades APOC de un rango (GetAllByPublicacion) y marca/suspende proveedores y clientes del ERP.
     *
     * @return array<string, mixed>
     */
    public function procesarNovedadesPorRango(string $desde, string $hasta, bool $suspenderSiApocrifo = true): array
    {
        return app(\App\Support\Arca\WsapocAuditoriaNovedadesSupport::class)
            ->procesarNovedadesPorRango($desde, $hasta, $suspenderSiApocrifo);
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
        $proveedoresCoincidentes = 0;
        $cuitsSinProveedor = [];
        $proveedoresSuspendidos = [];

        foreach ($porCuit as $cuit => $pubsCuit) {
            $proveedores = $this->buscarProveedoresPorCuit($cuit);
            if ($proveedores->isEmpty()) {
                $cuitsSinProveedor[] = $cuit;

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

            foreach ($proveedores as $proveedor) {
                $proveedoresCoincidentes++;
                $eval = $this->evaluarRespuestaWs($proveedor, $wsCuit, $suspenderSiApocrifo);
                if ($eval['es_apocrifo'] ?? false) {
                    $apocrifos++;
                    if ($eval['suspendido'] ?? false) {
                        $suspendidos++;
                        $proveedoresSuspendidos[] = $this->filaProveedorSuspendido($proveedor, $cuit, $pubsCuit, $eval);
                    }
                } elseif (! ($eval['ok'] ?? true)) {
                    $errores++;
                }
            }
        }

        return [
            'proveedores_coincidentes' => $proveedoresCoincidentes,
            'apocrifos' => $apocrifos,
            'suspendidos' => $suspendidos,
            'errores' => $errores,
            'cuits_sin_proveedor' => $cuitsSinProveedor,
            'proveedores_suspendidos' => $proveedoresSuspendidos,
        ];
    }

    /**
     * @param  list<array{cuit: string, descripcion: string|null, fecha_condicion: string|null, fecha_publicacion: string|null}>  $publicaciones
     * @param  array<string, mixed>  $eval
     * @return array<string, mixed>
     */
    public function filaProveedorSuspendido(Proveedor $proveedor, string $cuit, array $publicaciones, array $eval): array
    {
        $pub = $publicaciones[0] ?? [];

        return [
            'id' => (int) $proveedor->id,
            'codigo' => (string) ($proveedor->codigo ?? ''),
            'nombre' => (string) ($proveedor->nombre ?? ''),
            'cuit' => $cuit,
            'estado' => 'Suspendido',
            'fecha_publicacion' => $pub['fecha_publicacion'] ?? null,
            'fecha_condicion' => $pub['fecha_condicion'] ?? null,
            'descripcion_arca' => $pub['descripcion'] ?? null,
            'mensaje' => $eval['mensaje'] ?? null,
            'url_editar' => route('editar_proveedor', ['id' => $proveedor->id]),
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Proveedor>
     */
    public function buscarProveedoresPorCuit(string $cuit): \Illuminate\Database\Eloquent\Collection
    {
        $digitos = preg_replace('/\D+/', '', $cuit) ?? '';

        return Proveedor::query()
            ->whereRaw(
                "REPLACE(REPLACE(REPLACE(nroinscripcion, '-', ''), '.', ''), ' ', '') = ?",
                [$digitos]
            )
            ->get();
    }

    /**
     * @param  array<string, mixed>  $ws
     * @return array<string, mixed>
     */
    public function evaluarRespuestaWs(Proveedor $proveedor, array $ws, bool $suspenderSiApocrifo = false): array
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
            ? $this->mensajeApocrifo($proveedor, $ws)
            : 'El proveedor no figura en la base de facturas apócrifas de ARCA (consulta '.now()->format('d/m/Y H:i').').';

        $debeSuspender = $esApocrifo
            && filter_var(config('arca_wsapoc.suspender_automatico', true), FILTER_VALIDATE_BOOLEAN);

        $suspendido = false;
        if ($debeSuspender && $suspenderSiApocrifo) {
            $suspendido = $this->aplicarSuspension($proveedor, $ws, $mensaje);
        } else {
            $this->persistirConsulta($proveedor->id, $esApocrifo, $ws);
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
    private function mensajeApocrifo(Proveedor $proveedor, array $ws): string
    {
        $nombre = trim((string) $proveedor->nombre);
        $cuit = preg_replace('/\D+/', '', (string) $proveedor->nroinscripcion) ?? '';
        $pub = ($ws['publicaciones'] ?? [])[0] ?? null;
        $fechaPub = is_array($pub) ? ($pub['fecha_publicacion'] ?? null) : null;

        $texto = 'El proveedor';
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
    private function aplicarSuspension(Proveedor $proveedor, array $ws, string $mensaje): bool
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
            'estado' => 'Suspendido',
            'semaforo' => 'Rojo',
        ];

        if ($tipoId > 0) {
            $update['tiposuspension_id'] = $tipoId;
        }

        Proveedor::query()->whereKey($proveedor->id)->update($update);

        return true;
    }

    /**
     * @param  array<string, mixed>  $ws
     */
    private function persistirConsulta(int $proveedorId, bool $esApocrifo, array $ws): void
    {
        Proveedor::query()->whereKey($proveedorId)->update([
            'facturas_apocrifas' => $esApocrifo,
            'facturas_apocrifas_consulta_at' => now(),
            'facturas_apocrifas_detalle' => $esApocrifo
                ? json_encode(['publicaciones' => $ws['publicaciones'] ?? []], JSON_UNESCAPED_UNICODE)
                : null,
        ]);
    }

    public function resolverTiposuspensionId(): int
    {
        $configId = (int) config('arca_wsapoc.tiposuspension_id', 0);
        if ($configId > 0) {
            return $configId;
        }

        $nombre = trim((string) config('arca_wsapoc.tiposuspension_nombre', 'Facturas apócrifas (ARCA APOC)'));
        if ($nombre === '') {
            return 0;
        }

        $existente = Tiposuspensionproveedor::query()->where('nombre', $nombre)->value('id');
        if ($existente) {
            return (int) $existente;
        }

        $creado = Tiposuspensionproveedor::query()->create(['nombre' => $nombre]);

        return (int) $creado->id;
    }
}
