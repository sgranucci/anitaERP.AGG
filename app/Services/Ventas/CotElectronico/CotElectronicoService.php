<?php

namespace App\Services\Ventas\CotElectronico;

use App\Models\Ventas\CotRemitoEnvio;
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

        if (! $respuesta['ok']) {
            return [
                'ok' => false,
                'mensaje' => (string) ($respuesta['error_general'] ?? 'Error al enviar remitos a ARBA.'),
                'archivo' => $archivo,
                'respuesta' => $respuesta,
                'resultados' => [],
            ];
        }

        $resultados = $this->persistirResultados($fecha, $remitos, $archivo, $respuesta);

        return [
            'ok' => true,
            'mensaje' => 'Envío procesado. Comprobante ARBA '.($respuesta['numero_comprobante'] ?? ''),
            'archivo' => $archivo,
            'respuesta' => $respuesta,
            'resultados' => $resultados,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $repartosInput
     * @return list<array<string, mixed>>
     */
    private function normalizarRepartos(array $repartosInput): array
    {
        $repartos = [];

        foreach ($repartosInput as $row) {
            $transporteId = (int) ($row['transporte_id'] ?? 0);
            if ($transporteId < 1) {
                continue;
            }

            $repartos[] = [
                'transporte_id' => $transporteId,
                'codigo' => trim((string) ($row['codigo'] ?? '')),
                'nombre' => trim((string) ($row['nombre'] ?? '')),
                'patente' => trim((string) ($row['patente'] ?? '')),
                'cuit_chofer' => CuitFormatoValidacionSupport::formatear(trim((string) ($row['cuit_chofer'] ?? ''))),
            ];
        }

        return $repartos;
    }

    /**
     * @param  list<array<string, mixed>>  $remitos
     * @param  array<string, mixed>  $archivo
     * @param  array<string, mixed>  $respuesta
     * @return list<array<string, mixed>>
     */
    private function persistirResultados(Carbon $fecha, array $remitos, array $archivo, array $respuesta): array
    {
        $respuestasPorUnico = collect($respuesta['remitos'] ?? [])
            ->keyBy(fn ($item) => (string) ($item['numero_unico'] ?? ''));

        $resultados = [];

        DB::transaction(function () use ($remitos, $archivo, $respuesta, $respuestasPorUnico, &$resultados) {
            $respuestaLista = array_values($respuesta['remitos'] ?? []);

            foreach ($remitos as $indice => $fila) {
                $sucursal = (int) ($fila['sucursal'] ?? 1);
                $numero = (int) ($fila['numero_remito'] ?? 0);
                $fechaRemito = (string) ($fila['fecha_remito'] ?? now()->toDateString());
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

                CotRemitoEnvio::query()->updateOrCreate(
                    [
                        'tipo' => 'REM',
                        'letra' => 'R',
                        'sucursal' => $sucursal,
                        'numero_remito' => $numero,
                        'fecha_remito' => $fechaRemito,
                    ],
                    [
                        'venta_id' => (int) ($fila['venta_id'] ?? 0) ?: null,
                        'transporte_id' => (int) ($fila['transporte_id'] ?? 0) ?: null,
                        'cliente_id' => null,
                        'procesado' => $procesado,
                        'nro_unico' => (string) ($detalle['numero_unico'] ?? $codigoUnicoEsperado),
                        'cot' => (string) ($detalle['cot'] ?? ''),
                        'numero_comprobante_arba' => (string) ($respuesta['numero_comprobante'] ?? ''),
                        'nombre_archivo' => (string) ($archivo['nombre'] ?? ''),
                        'error' => $errorTxt,
                        'usuario_id' => Auth::id(),
                    ],
                );

                $resultados[] = [
                    'numero_remito' => $numero,
                    'cliente_nombre' => (string) ($fila['cliente_nombre'] ?? ''),
                    'transporte_codigo' => (string) ($fila['transporte_codigo'] ?? ''),
                    'procesado' => $procesado,
                    'nro_unico' => (string) ($detalle['numero_unico'] ?? ''),
                    'cot' => (string) ($detalle['cot'] ?? ''),
                    'error' => $errorTxt,
                ];
            }
        });

        return $resultados;
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
