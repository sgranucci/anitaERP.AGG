<?php

namespace App\Services\Ventas\FacturacionLocal;

use App\Models\Caja\Cuentacaja;
use App\Models\Caja\Tipotransaccion_Caja;
use App\Models\Stock\Articulo;
use App\Models\Ventas\LocalVenta;
use App\Models\Ventas\TurnoOperativoLocal;
use App\Support\Ventas\FacturacionLocal\ArticuloCanalSupport;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalLimitesAfipSupport;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalMedioTarjetaSupport;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalVarianteArticuloSupport;

final class FacturacionLocalPreflightService
{
    public function __construct(
        private readonly FacturacionLocalTurnoService $turnoService,
    ) {
    }

    /**
     * @param  list<array<string,mixed>>  $lineas
     * @param  list<array{cuentacaja_id:int,moneda_id?:int,monto:float}>  $mediosPago
     * @return list<string>
     */
    public function erroresAntesDeEmitir(
        LocalVenta $local,
        ?TurnoOperativoLocal $turno,
        array $lineas,
        array $mediosPago,
        float $totalNeto,
        bool $tieneDatosCliente,
        bool $pagoConTarjeta = false,
        bool $esTicketRegalo = false,
    ): array {
        $errores = [];

        if (! $local->activo) {
            $errores[] = 'El local no está activo.';
        }
        if ((int) $local->puntoventa_id <= 0) {
            $errores[] = 'Configure el punto de venta del local.';
        }
        if ((int) $local->deposito_id <= 0) {
            $errores[] = 'Configure el depósito del local.';
        }

        $tipoCajaId = $local->tipoCajaId();
        if ($tipoCajaId <= 0 || ! Tipotransaccion_Caja::query()->whereKey($tipoCajaId)->exists()) {
            $errores[] = 'Configure un tipo de transacción de caja (Cobranza) válido en el local o en FACTURACION_LOCAL_TIPO_CAJA_ID.';
        }
        $tipoCajaDevId = $local->tipoCajaDevolucionId();
        if ($tipoCajaDevId <= 0 || ! Tipotransaccion_Caja::query()->whereKey($tipoCajaDevId)->exists()) {
            $errores[] = 'Configure un tipo de caja de devolución válido (FACTURACION_LOCAL_TIPO_CAJA_DEVOLUCION_ID).';
        }

        if (! $turno || ! $turno->estaAbierto()) {
            $errores[] = 'Debe abrir un turno antes de facturar.';
        } elseif ((int) $turno->local_venta_id !== (int) $local->id) {
            $errores[] = 'El turno abierto no corresponde al local seleccionado.';
        }

        if ($lineas === []) {
            $errores[] = 'Agregue al menos un artículo al carrito.';
        }

        foreach ($lineas as $i => $linea) {
            $n = $i + 1;
            $articuloId = (int) ($linea['articulo_id'] ?? 0);
            if ($articuloId <= 0) {
                $errores[] = "Línea {$n}: artículo inválido.";
                continue;
            }
            if (! ArticuloCanalSupport::articuloOperativoLocal($articuloId)) {
                $errores[] = "Línea {$n}: el artículo no está operativo en canal Local (sin canal o inactivo en locales).";
            }
            $articulo = Articulo::query()->find($articuloId);
            if ($articulo && ! empty($articulo->nofactura)) {
                $errores[] = "Línea {$n}: artículo marcado para no facturar.";
            }
            $var = FacturacionLocalVarianteArticuloSupport::validarLinea(
                $articulo ?? $articuloId,
                isset($linea['talle_id']) ? (int) $linea['talle_id'] : null,
                isset($linea['color_id']) ? (int) $linea['color_id'] : null,
                isset($linea['combinacion_id']) ? (int) $linea['combinacion_id'] : null,
            );
            if (! $var['ok']) {
                $errores[] = "Línea {$n}: ".$var['error'];
            }
            if (abs((float) ($linea['cantidad'] ?? 0)) < 0.000001) {
                $errores[] = "Línea {$n}: cantidad inválida.";
            }
        }

        if (! $esTicketRegalo && $totalNeto > 0.009) {
            $sumaMedios = 0.;
            foreach ($mediosPago as $m) {
                $sumaMedios += (float) ($m['monto'] ?? 0);
            }
            if ($mediosPago === []) {
                $errores[] = 'Indique al menos un medio de cobro.';
            } elseif (abs($sumaMedios - $totalNeto) > 0.05 && $sumaMedios + 0.05 < $totalNeto) {
                // permite exceso (vale/reintegro); no permite pago insuficiente
                $errores[] = 'La cobranza es insuficiente respecto del total a pagar.';
            }
        }

        $pagoConTarjeta = $pagoConTarjeta || $this->validarCuponesTarjeta($mediosPago, $errores);

        foreach (FacturacionLocalLimitesAfipSupport::erroresIdentificacion(
            max(0., $totalNeto),
            $tieneDatosCliente,
            $pagoConTarjeta
        ) as $e) {
            $errores[] = $e;
        }

        return $errores;
    }

    /**
     * @param  list<array<string,mixed>>  $mediosPago
     * @param  list<string>  $errores
     */
    private function validarCuponesTarjeta(array $mediosPago, array &$errores): bool
    {
        $ids = [];
        foreach ($mediosPago as $medio) {
            $id = (int) ($medio['cuentacaja_id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        if ($ids === []) {
            return false;
        }

        $cuentas = Cuentacaja::query()
            ->whereIn('id', array_values(array_unique($ids)))
            ->get(['id', 'nombre', 'codigo', 'es_tarjeta'])
            ->keyBy('id');

        $hayTarjeta = false;
        foreach ($mediosPago as $medio) {
            $cuenta = $cuentas->get((int) ($medio['cuentacaja_id'] ?? 0));
            if (! $cuenta) {
                continue;
            }
            if (! FacturacionLocalMedioTarjetaSupport::pideCupon($cuenta)) {
                continue;
            }
            $hayTarjeta = true;
            if (trim((string) ($medio['numerocupon'] ?? '')) === '') {
                $errores[] = 'Indique el número de cupón de '.$cuenta->nombre.'.';
            }
        }

        return $hayTarjeta;
    }
}
