<?php

namespace App\Services\Ventas\CotElectronico;

use App\Models\Ventas\CotRemitoEnvio;
use App\Models\Ventas\CotSesionEnvio;
use App\Support\Ventas\CuitFormatoValidacionSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CotElectronicoService
{
    public function __construct(
        private CotRemitoConsultaService $consultaService,
        private CotArchivoArbaService $archivoService,
        private ArbaCotPresentacionService $presentacionService,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $repartosInput
     * @return array{repartos:list<array<string, mixed>>,remitos:list<array<string, mixed>>}
     */
    public function preview(Carbon $fecha, array $repartosInput): array
    {
        $repartos = $this->normalizarRepartos($repartosInput);

        return [
            'repartos' => $repartos,
            'remitos' => $this->consultaService->listarRemitosDelDia($fecha, $repartos),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $repartosInput
     * @param  list<string>  $clavesSeleccionadas
     * @return array<string, mixed>
     */
    public function procesar(Carbon $fecha, array $repartosInput, array $clavesSeleccionadas): array
    {
        $preview = $this->preview($fecha, $repartosInput);
        $repartos = $preview['repartos'];
        $remitos = collect($preview['remitos'])
            ->filter(fn ($fila) => in_array((string) ($fila['clave'] ?? ''), $clavesSeleccionadas, true))
            ->filter(fn ($fila) => empty($fila['ya_enviado']))
            ->values()
            ->all();

        if ($remitos === []) {
            return [
                'ok' => false,
                'mensaje' => 'No hay remitos seleccionados para procesar.',
                'resultados' => [],
            ];
        }

        $empresa = $this->consultaService->resolverEmpresaEmisora();
        if (! $empresa) {
            return [
                'ok' => false,
                'mensaje' => 'No se encontró empresa emisora en el ERP.',
                'resultados' => [],
            ];
        }

        $archivo = $this->archivoService->generarArchivo($fecha, $empresa, $remitos);
        if ((int) ($archivo['cantidad_remitos'] ?? 0) < 1) {
            return [
                'ok' => false,
                'mensaje' => 'No se pudo armar el archivo ARBA (sin productos válidos).',
                'resultados' => [],
            ];
        }

        $respuesta = $this->presentacionService->presentarRemitos(
            (string) $archivo['ruta'],
            (string) $archivo['nombre'],
        );

        $sesion = $this->crearSesion($fecha, $repartos, $remitos, $archivo, $respuesta);

        if (! $respuesta['ok']) {
            $resultados = $this->persistirRemitosFallo($sesion, $remitos, $archivo, $respuesta);

            return [
                'ok' => false,
                'mensaje' => (string) ($respuesta['error_general'] ?? 'Error al enviar remitos a ARBA.'),
                'archivo' => $archivo,
                'respuesta' => $respuesta,
                'sesion_id' => $sesion->id,
                'resultados' => $resultados,
            ];
        }

        $resultados = $this->persistirResultados($sesion, $remitos, $archivo, $respuesta);
        $this->actualizarContadoresSesion($sesion, $resultados);

        return [
            'ok' => true,
            'mensaje' => 'Envío procesado. Comprobante ARBA '.($respuesta['numero_comprobante'] ?? ''),
            'archivo' => $archivo,
            'respuesta' => $respuesta,
            'sesion_id' => $sesion->id,
            'resultados' => $resultados,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $repartosInput
     * @return list<array<string, mixed>>
     */
    private function normalizarRepartos(array $repartosInput): array
    {
        /** @var array<int, array<string, mixed>> $porTransporteId */
        $porTransporteId = [];

        foreach ($repartosInput as $row) {
            $transporteId = (int) ($row['transporte_id'] ?? 0);
            if ($transporteId < 1) {
                continue;
            }

            $fila = [
                'transporte_id' => $transporteId,
                'codigo' => trim((string) ($row['codigo'] ?? '')),
                'nombre' => trim((string) ($row['nombre'] ?? '')),
                'patente' => trim((string) ($row['patente'] ?? '')),
                'cuit_chofer' => CuitFormatoValidacionSupport::formatear(trim((string) ($row['cuit_chofer'] ?? ''))),
            ];

            if (! isset($porTransporteId[$transporteId])) {
                $porTransporteId[$transporteId] = $fila;

                continue;
            }

            foreach (['codigo', 'nombre', 'patente', 'cuit_chofer'] as $campo) {
                if (($porTransporteId[$transporteId][$campo] ?? '') === '' && ($fila[$campo] ?? '') !== '') {
                    $porTransporteId[$transporteId][$campo] = $fila[$campo];
                }
            }
        }

        return array_values($porTransporteId);
    }

    /**
     * @param  list<array<string, mixed>>  $repartos
     * @param  list<array<string, mixed>>  $remitos
     * @param  array<string, mixed>  $archivo
     * @param  array<string, mixed>  $respuesta
     */
    private function crearSesion(
        Carbon $fecha,
        array $repartos,
        array $remitos,
        array $archivo,
        array $respuesta,
    ): CotSesionEnvio {
        return CotSesionEnvio::query()->create([
            'fecha_facturas' => $fecha->toDateString(),
            'fecha_envio' => now(),
            'ambiente' => (string) config('arba_cot.ambiente', 'test'),
            'nombre_archivo' => (string) ($archivo['nombre'] ?? $respuesta['nombre_archivo'] ?? ''),
            'numero_comprobante_arba' => (string) ($respuesta['numero_comprobante'] ?? ''),
            'cuit_empresa' => (string) ($respuesta['cuit_empresa'] ?? ''),
            'codigo_integridad' => (string) ($respuesta['codigo_integridad'] ?? ''),
            'ok' => (bool) ($respuesta['ok'] ?? false),
            'error_general' => $respuesta['ok'] ? null : (string) ($respuesta['error_general'] ?? ''),
            'cantidad_remitos' => count($remitos),
            'cantidad_ok' => 0,
            'cantidad_error' => 0,
            'repartos_json' => $repartos,
            'usuario_id' => Auth::id(),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $remitos
     * @param  array<string, mixed>  $archivo
     * @param  array<string, mixed>  $respuesta
     * @return list<array<string, mixed>>
     */
    private function persistirRemitosFallo(
        CotSesionEnvio $sesion,
        array $remitos,
        array $archivo,
        array $respuesta,
    ): array {
        $errorGeneral = (string) ($respuesta['error_general'] ?? 'Error al enviar remitos a ARBA.');
        $resultados = [];

        DB::transaction(function () use ($sesion, $remitos, $archivo, $errorGeneral, &$resultados) {
            foreach ($remitos as $fila) {
                $registro = $this->crearRegistroRemito($sesion, $fila, $archivo, [
                    'procesado' => 'NO',
                    'nro_unico' => $this->codigoUnicoEsperado(
                        (int) ($fila['sucursal'] ?? 1),
                        (int) ($fila['numero_remito'] ?? 0),
                    ),
                    'cot' => '',
                    'error' => $errorGeneral,
                    'numero_comprobante_arba' => '',
                ]);

                $resultados[] = $this->mapearResultadoFila($fila, $registro);
            }

            $sesion->update([
                'cantidad_ok' => 0,
                'cantidad_error' => count($remitos),
            ]);
        });

        return $resultados;
    }

    /**
     * @param  list<array<string, mixed>>  $remitos
     * @param  array<string, mixed>  $archivo
     * @param  array<string, mixed>  $respuesta
     * @return list<array<string, mixed>>
     */
    private function persistirResultados(
        CotSesionEnvio $sesion,
        array $remitos,
        array $archivo,
        array $respuesta,
    ): array {
        $respuestasPorUnico = collect($respuesta['remitos'] ?? [])
            ->keyBy(fn ($item) => (string) ($item['numero_unico'] ?? ''));

        $resultados = [];

        DB::transaction(function () use ($sesion, $remitos, $archivo, $respuesta, $respuestasPorUnico, &$resultados) {
            $respuestaLista = array_values($respuesta['remitos'] ?? []);

            foreach ($remitos as $indice => $fila) {
                $sucursal = (int) ($fila['sucursal'] ?? 1);
                $numero = (int) ($fila['numero_remito'] ?? 0);
                $codigoUnicoEsperado = $this->codigoUnicoEsperado($sucursal, $numero);
                $detalle = $respuestasPorUnico->get($codigoUnicoEsperado);

                if (! $detalle && isset($respuestaLista[$indice])) {
                    $detalle = $respuestaLista[$indice];
                }

                if (! $detalle) {
                    $detalle = $respuestasPorUnico->first();
                }

                $procesado = strtoupper((string) ($detalle['procesado'] ?? 'NO'));
                $errores = $detalle['errores'] ?? [];
                $errorTxt = is_array($errores) && $errores !== [] ? implode("\n", $errores) : null;

                $registro = $this->crearRegistroRemito($sesion, $fila, $archivo, [
                    'procesado' => $procesado,
                    'nro_unico' => (string) ($detalle['numero_unico'] ?? $codigoUnicoEsperado),
                    'cot' => (string) ($detalle['cot'] ?? ''),
                    'error' => $errorTxt,
                    'numero_comprobante_arba' => (string) ($respuesta['numero_comprobante'] ?? ''),
                ]);

                $resultados[] = $this->mapearResultadoFila($fila, $registro);
            }
        });

        return $resultados;
    }

    /**
     * @param  list<array<string, mixed>>  $resultados
     */
    private function actualizarContadoresSesion(CotSesionEnvio $sesion, array $resultados): void
    {
        $ok = 0;
        $error = 0;

        foreach ($resultados as $fila) {
            $procesado = strtoupper((string) ($fila['procesado'] ?? ''));
            if ($procesado === 'SI' || (($fila['cot'] ?? '') !== '' && empty($fila['error']))) {
                $ok++;
            } else {
                $error++;
            }
        }

        $sesion->update([
            'ok' => $error === 0,
            'cantidad_ok' => $ok,
            'cantidad_error' => $error,
        ]);
    }

    /**
     * @param  array<string, mixed>  $fila
     * @param  array<string, mixed>  $detalle
     */
    private function crearRegistroRemito(
        CotSesionEnvio $sesion,
        array $fila,
        array $archivo,
        array $detalle,
    ): CotRemitoEnvio {
        return CotRemitoEnvio::query()->create([
            'cot_sesion_envio_id' => $sesion->id,
            'tipo' => (string) ($fila['tipo'] ?? 'REM'),
            'letra' => (string) ($fila['letra'] ?? 'R'),
            'sucursal' => (int) ($fila['sucursal'] ?? 1),
            'numero_remito' => (int) ($fila['numero_remito'] ?? 0),
            'fecha_remito' => (string) ($fila['fecha_remito'] ?? now()->toDateString()),
            'venta_id' => (int) ($fila['venta_id'] ?? 0) ?: null,
            'transporte_id' => (int) ($fila['transporte_id'] ?? 0) ?: null,
            'cliente_id' => (int) ($fila['cliente_id'] ?? 0) ?: null,
            'cliente_nombre' => trim((string) ($fila['cliente_nombre'] ?? '')),
            'procesado' => (string) ($detalle['procesado'] ?? 'NO'),
            'nro_unico' => (string) ($detalle['nro_unico'] ?? ''),
            'cot' => (string) ($detalle['cot'] ?? ''),
            'numero_comprobante_arba' => (string) ($detalle['numero_comprobante_arba'] ?? ''),
            'nombre_archivo' => (string) ($archivo['nombre'] ?? ''),
            'error' => $detalle['error'] ?? null,
            'usuario_id' => Auth::id(),
        ]);
    }

    /** @param  array<string, mixed>  $fila */
    private function mapearResultadoFila(array $fila, CotRemitoEnvio $registro): array
    {
        return [
            'numero_remito' => (int) ($fila['numero_remito'] ?? 0),
            'cliente_nombre' => (string) ($fila['cliente_nombre'] ?? $registro->cliente_nombre ?? ''),
            'transporte_codigo' => (string) ($fila['transporte_codigo'] ?? ''),
            'procesado' => (string) ($registro->procesado ?? ''),
            'nro_unico' => (string) ($registro->nro_unico ?? ''),
            'cot' => (string) ($registro->cot ?? ''),
            'error' => $registro->error,
        ];
    }

    private function codigoUnicoEsperado(int $sucursal, int $numero): string
    {
        $tipo = (string) config('arba_cot.codigo_comprobante_remito', '091');
        $digitosSucursal = (int) config('facturacion.DIGITOS_SUCURSAL', 5);
        $digitosComp = (int) config('facturacion.DIGITOS_COMPROBANTE', 8);

        return $tipo
            .str_pad((string) $sucursal, $digitosSucursal, '0', STR_PAD_LEFT)
            .str_pad((string) $numero, $digitosComp, '0', STR_PAD_LEFT);
    }
}
