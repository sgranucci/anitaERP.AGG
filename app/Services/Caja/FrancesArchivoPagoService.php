<?php

declare(strict_types=1);

namespace App\Services\Caja;

use App\Models\Caja\Cuentacaja;
use App\Support\Caja\Frances\FrancesArchivoPagoAnitaReader;
use App\Support\Caja\Frances\FrancesArchivoPagoArmadoSupport;
use App\Support\Caja\Frances\FrancesArchivoPagoFiltros;

/**
 * Exportación de pagos al Banco Francés (p-enviafrances.c).
 */
class FrancesArchivoPagoService
{
    public function __construct(
        private readonly FrancesArchivoPagoAnitaReader $reader = new FrancesArchivoPagoAnitaReader,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function generar(array $filtros): array
    {
        if (! FrancesArchivoPagoFiltros::tieneCriteriosAplicados($filtros)) {
            return $this->vacio('Indique empresa, fechas y cuenta de caja.');
        }

        $cuenta = Cuentacaja::query()->find((int) $filtros['cuentacaja_id']);
        if ($cuenta === null) {
            return $this->vacio('Seleccione una cuenta de caja.');
        }

        $desde = (int) str_replace('-', '', (string) $filtros['fecha_desde']);
        $hasta = (int) str_replace('-', '', (string) $filtros['fecha_hasta']);
        $errores = [];
        $tipo = $this->reader->tipoAplicacionPorCodigo((string) $cuenta->codigo, $errores);
        if ($tipo === '') {
            return $this->vacio(
                'La cuenta '.$cuenta->codigo.' no tiene medio de transferencia en Anita.',
                $errores
            );
        }

        $pagosRaw = $this->reader->listarPagos($desde, $hasta, $errores);
        $auxRaw = $this->reader->listarAuxpag($desde, $hasta, $tipo, $errores);

        $pagos = [];
        foreach ($pagosRaw as $fila) {
            $pagos[] = [
                'tipo' => (string) ($fila->pag_tipo ?? ''),
                'numero' => (int) ($fila->pag_rec ?? 0),
                'sucursal' => (int) ($fila->pag_sucursal ?? 0),
                'proveedor' => (string) ($fila->pag_pro ?? ''),
                'fecha' => (string) ($fila->pag_fecha ?? ''),
                'mov_ext' => (string) ($fila->pag_mov_ext ?? ''),
                'letra' => (string) ($fila->pag_letra ?? ''),
            ];
        }

        $auxpag = [];
        $codigos = [];
        foreach ($auxRaw as $fila) {
            $auxpag[] = [
                'tipo' => (string) ($fila->axp_tipo ?? ''),
                'numero' => (int) ($fila->axp_rec ?? 0),
                'sucursal' => (int) ($fila->axp_sucursal ?? 0),
                'proveedor' => (string) ($fila->axp_pro ?? ''),
                'fecha' => (string) ($fila->axp_fecha ?? ''),
                'tipo_ap' => (string) ($fila->axp_tipo_ap ?? ''),
                'monto' => (float) ($fila->axp_monto_ap ?? 0),
                'letra' => (string) ($fila->axp_letra_comp ?? ''),
            ];
            $codigos[] = (string) ($fila->axp_pro ?? '');
        }

        $proveedores = $this->reader->mapaProveedores($codigos, $errores);
        $armado = FrancesArchivoPagoArmadoSupport::armar($pagos, $auxpag, $proveedores, $tipo);

        $mensaje = $armado['cantidad'] > 0
            ? 'Listo: '.$armado['cantidad'].' pago(s) por $'.number_format((float) $armado['total'], 2, ',', '.')
            : 'No hay pagos de la cuenta '.$cuenta->codigo.' en ese rango.';
        if ($errores !== []) {
            $mensaje .= ' '.implode(' ', array_slice($errores, 0, 3));
        }

        return [
            'ok' => $errores === [] || $armado['cantidad'] > 0,
            'mensaje' => $mensaje,
            'filas' => $armado['filas'],
            'omitidas' => $armado['omitidas'],
            'errores' => $errores,
            'archivo' => $armado['archivo'],
            'cantidad' => $armado['cantidad'],
            'total' => $armado['total'],
            'cuentacaja_id' => (int) $cuenta->id,
            'cuentacaja_codigo' => (string) $cuenta->codigo,
        ];
    }

    /**
     * @param  list<string>  $errores
     * @return array<string, mixed>
     */
    private function vacio(string $mensaje, array $errores = []): array
    {
        return [
            'ok' => false,
            'mensaje' => $mensaje,
            'filas' => [],
            'omitidas' => [],
            'errores' => $errores,
            'archivo' => '',
            'cantidad' => 0,
            'total' => 0.0,
            'cuentacaja_id' => 0,
            'cuentacaja_codigo' => '',
        ];
    }
}
