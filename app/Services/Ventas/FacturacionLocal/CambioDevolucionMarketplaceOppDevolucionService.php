<?php

declare(strict_types=1);

namespace App\Services\Ventas\FacturacionLocal;

use App\Models\Caja\Caja_Movimiento;
use App\Models\Caja\Cuentacaja;
use App\Models\Caja\Tipotransaccion_Caja;
use App\Models\Ventas\CambioDevolucionMarketplace;
use App\Services\Caja\IngresoEgresoService;
use App\Support\Contable\CuentacontableEmpresaHomologacionSupport;
use App\Support\Ventas\FacturacionLocal\CambioDevolucionMarketplaceEstadosSupport;
use App\Support\Ventas\FacturacionLocal\CambioDevolucionMarketplaceLiquidacionSupport;
use App\Support\Ventas\FacturacionLocal\CambioDevolucionMarketplacePuenteSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Cierra el legajo emitiendo una OPP de Ingresos/Egresos sin proveedor
 * (reembolso al cliente) contra el puente NCD y el medio de pago elegido.
 */
final class CambioDevolucionMarketplaceOppDevolucionService
{
    public function __construct(
        private readonly IngresoEgresoService $ingresoEgresoService,
        private readonly CambioDevolucionMarketplaceService $cambioService,
    ) {
    }

    /**
     * @return array{ok:bool,caja_movimiento_id?:int,numerotransaccion?:string,error?:string,cambio?:CambioDevolucionMarketplace}
     */
    public function cerrarConOpp(CambioDevolucionMarketplace $cambio, int $cuentacajaMedioId, ?string $detalleExtra = null): array
    {
        $cambio->loadMissing(['ventaNc', 'ventaOriginal', 'ventaReemplazo', 'cliente', 'localVenta']);

        if ((string) $cambio->estado !== CambioDevolucionMarketplaceEstadosSupport::PENDIENTE_COMPENSACION) {
            throw new InvalidArgumentException('El legajo debe estar en pendiente de compensación para emitir la OPP de reembolso.');
        }
        if ((string) $cambio->diferencia_sentido !== CambioDevolucionMarketplaceLiquidacionSupport::SENTIDO_FERLI_ACREDITA) {
            throw new InvalidArgumentException(
                'Solo se emite OPP cuando Ferli acredita (reembolso). Sentido actual: '
                .CambioDevolucionMarketplaceLiquidacionSupport::etiquetaSentido($cambio->diferencia_sentido)
            );
        }

        $importe = round(abs((float) $cambio->diferencia_importe), 2);
        if ($importe < 0.01) {
            throw new InvalidArgumentException('No hay importe a reembolsar.');
        }
        if ((int) ($cambio->caja_movimiento_id ?? 0) > 0) {
            throw new InvalidArgumentException('El legajo ya tiene una OPP de reembolso asociada.');
        }

        $empresaId = (int) ($cambio->empresa_id ?: $cambio->localVenta->empresa_id ?? 0);
        if ($empresaId <= 0) {
            throw new InvalidArgumentException('El legajo no tiene empresa.');
        }

        $tipoOpp = Tipotransaccion_Caja::query()
            ->whereRaw('UPPER(TRIM(abreviatura)) = ?', ['OPP'])
            ->where('estado', 'A')
            ->orderBy('id')
            ->first();
        if (! $tipoOpp) {
            throw new RuntimeException('No existe el tipo de transacción de caja OPP activo.');
        }

        $medio = Cuentacaja::query()->paraEmpresa($empresaId)->whereKey($cuentacajaMedioId)->first();
        if (! $medio) {
            throw new InvalidArgumentException('La cuenta de caja (medio de pago) no existe o no pertenece a la empresa.');
        }
        $puente = CambioDevolucionMarketplacePuenteSupport::resolverCuentacaja();
        if (! $puente) {
            throw new RuntimeException(
                'No se encontró la cuenta puente NCD '
                .CambioDevolucionMarketplacePuenteSupport::codigoCuentacaja()
            );
        }
        if ((int) $medio->id === (int) $puente->id) {
            throw new InvalidArgumentException('Elegí un medio de pago distinto al puente NCD (Aplicación de crédito local).');
        }

        $ctaMedioId = CuentacontableEmpresaHomologacionSupport::idParaEmpresa(
            (int) $medio->cuentacontable_id,
            $empresaId
        );
        $ctaPuenteId = CuentacontableEmpresaHomologacionSupport::idParaEmpresa(
            (int) $puente->cuentacontable_id,
            $empresaId
        );
        if ($ctaMedioId <= 0 || $ctaPuenteId <= 0) {
            throw new RuntimeException('Falta cuenta contable en el medio de pago o en el puente NCD.');
        }

        $beneficiario = trim((string) ($cambio->receptor_nombre ?: $cambio->cliente->nombre ?? ''));
        if ($beneficiario === '') {
            $beneficiario = 'Cliente legajo '.$cambio->numero;
        }
        $detalle = sprintf(
            'Reembolso legajo CDM %d — %s',
            (int) $cambio->numero,
            $beneficiario
        );
        $extra = trim((string) $detalleExtra);
        if ($extra !== '') {
            $detalle .= ' — '.$extra;
        }
        $detalle = mb_substr($detalle, 0, 255);

        $fecha = date('Y-m-d');
        $monedaId = (int) ($medio->moneda_id ?: config('facturacion_local.moneda_id', 1));
        $obs = 'Legajo CDM '.(int) $cambio->numero;

        $payload = [
            'empresa_id' => $empresaId,
            'tipotransaccion_caja_id' => (int) $tipoOpp->id,
            'fecha' => $fecha,
            'detalle' => $detalle,
            'proveedor_id' => null,
            'cliente_id' => ((int) ($cambio->cliente_id ?? 0)) ?: null,
            'conceptogasto_id' => null,
            'usuario_id' => (int) (Auth::id() ?: $cambio->usuario_alta_id ?: 1),
            // Caja: medio de pago (el signo OPP lo firma al grabar)
            'cuentacaja_ids' => [(int) $medio->id],
            'montos' => [$importe],
            'moneda_ids' => [$monedaId],
            'cotizaciones' => [1],
            'observaciones' => [$obs],
            // Asiento: Debe puente NCD / Haber medio
            'cuentacontable_ids' => [$ctaPuenteId, $ctaMedioId],
            'debeasientos' => [$importe, ''],
            'haberasientos' => ['', $importe],
            'monedaasiento_ids' => [$monedaId, $monedaId],
            'cotizacionasientos' => [1, 1],
            'centrocostoasiento_ids' => [0, 0],
            'observacionasientos' => [$obs, $obs],
            'carga_cuentacontable_manuales' => ['N', 'N'],
        ];

        $request = Request::create('/caja/ingresoegreso', 'POST', $payload);
        $request->setUserResolver(static fn () => Auth::user());

        return DB::transaction(function () use ($cambio, $request, $importe, $medio) {
            $resultado = $this->ingresoEgresoService->guardaIngresoEgreso($request, 'cambio_devolucion_marketplace');
            if (! empty($resultado['errores'])) {
                throw new InvalidArgumentException((string) $resultado['errores']);
            }
            $cajaMovimientoId = (int) ($resultado['caja_movimiento_id'] ?? 0);
            if ($cajaMovimientoId <= 0) {
                throw new RuntimeException('No se obtuvo el id del movimiento de caja de la OPP.');
            }

            $numero = (string) (Caja_Movimiento::query()->whereKey($cajaMovimientoId)->value('numerotransaccion') ?? '');
            $cambio->caja_movimiento_id = $cajaMovimientoId;
            $cambio->save();

            $obsComp = sprintf(
                'OPP %s por $%s — medio %s %s',
                $numero !== '' ? $numero : ('#'.$cajaMovimientoId),
                number_format($importe, 2, ',', '.'),
                (string) $medio->codigo,
                (string) $medio->nombre
            );
            $cerrado = $this->cambioService->registrarCompensacion($cambio->fresh(), $obsComp);

            return [
                'ok' => true,
                'caja_movimiento_id' => $cajaMovimientoId,
                'numerotransaccion' => $numero,
                'cambio' => $cerrado,
            ];
        });
    }
}
