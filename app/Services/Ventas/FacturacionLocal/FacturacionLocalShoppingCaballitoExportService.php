<?php

declare(strict_types=1);

namespace App\Services\Ventas\FacturacionLocal;

use App\Mail\Ventas\FacturacionLocalShoppingCaballitoMail;
use App\Models\Ventas\FacturacionLocalShoppingEnvio;
use App\Models\Ventas\LocalVenta;
use App\Models\Ventas\Venta;
use App\Support\Configuracion\CotizacionVigenteSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalShoppingArchivoSupport;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalShoppingCaballitoSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Arma el TXT del shopping de Caballito, lo guarda y lo manda por correo.
 */
final class FacturacionLocalShoppingCaballitoExportService
{
    /**
     * @return array{ok:bool,estado:string,mensaje:string,envio_id:?int,ruta:?string,comprobantes:int,lineas:int}
     */
    public function ejecutar(string $fechaYmd, bool $forzar, bool $dryRun): array
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return $this->resultado(false, 'error', 'La exportación al shopping es solo de Ferli.', null, null, 0, 0);
        }

        $fecha = Carbon::parse($fechaYmd)->toDateString();
        $faltantes = FacturacionLocalShoppingCaballitoSupport::faltantesIdentificacion();
        $local = FacturacionLocalShoppingCaballitoSupport::local();
        if ($faltantes !== [] || $local === null) {
            $mensaje = implode(' ', $faltantes);
            $envioId = null;
            if (! $dryRun) {
                $envioId = $this->registrar($local?->id, $fecha, '', null, FacturacionLocalShoppingEnvio::ESTADO_ERROR, 0, 0, $mensaje, '')?->id;
            }

            return $this->resultado(false, 'error', $mensaje, $envioId, null, 0, 0);
        }

        if (! $forzar && ! $dryRun && $this->yaEnviado((int) $local->id, $fecha)) {
            return $this->resultado(
                true,
                'omitido',
                'El archivo del '.$this->fechaTexto($fecha).' ya se envió. Para mandarlo de nuevo, reenvialo desde el log.',
                null,
                null,
                0,
                0
            );
        }

        try {
            $armado = $this->armar($local, $fecha);
        } catch (Throwable $e) {
            $mensaje = 'No se pudo armar el archivo: '.$e->getMessage();
            $envioId = null;
            if (! $dryRun) {
                $envioId = $this->registrar((int) $local->id, $fecha, FacturacionLocalShoppingArchivoSupport::nombreArchivo($fecha), null, FacturacionLocalShoppingEnvio::ESTADO_ERROR, 0, 0, $mensaje, '')?->id;
            }

            return $this->resultado(false, 'error', $mensaje, $envioId, null, 0, 0);
        }

        $nombre = FacturacionLocalShoppingArchivoSupport::nombreArchivo($fecha);
        if ($dryRun) {
            $relativa = 'facturacion_local_shopping/dry-run/'.$nombre;
            Storage::disk('local')->put($relativa, $armado['contenido']);

            return $this->resultado(
                true,
                'omitido',
                'Dry-run: '.$armado['comprobantes'].' comprobantes, '.$armado['lineas'].' líneas. Archivo en storage/app/'.$relativa,
                null,
                storage_path('app/'.$relativa),
                $armado['comprobantes'],
                $armado['lineas']
            );
        }

        $emails = FacturacionLocalShoppingCaballitoSupport::emails();
        $destinatarios = implode(', ', $emails);
        $mensajeBase = $this->resumen($armado['comprobantes'], $armado['lineas'], $armado['advertencias']);
        $envio = $this->registrar(
            (int) $local->id,
            $fecha,
            $nombre,
            null,
            FacturacionLocalShoppingEnvio::ESTADO_ERROR,
            $armado['comprobantes'],
            $armado['lineas'],
            $mensajeBase,
            $destinatarios
        );
        $relativa = 'facturacion_local_shopping/'.$envio->id.'/'.$nombre;
        Storage::disk('local')->put($relativa, $armado['contenido']);
        $envio->ruta = $relativa;
        $envio->save();
        $absoluta = storage_path('app/'.$relativa);

        if ($emails === []) {
            $mensaje = 'Archivo generado, sin envío: falta el correo en Parámetros Facturación Local. '.$mensajeBase;
            $envio->mensaje = $mensaje;
            $envio->save();

            return $this->resultado(false, 'error', $mensaje, (int) $envio->id, $absoluta, $armado['comprobantes'], $armado['lineas']);
        }

        try {
            Mail::to($emails)->send(new FacturacionLocalShoppingCaballitoMail(
                $this->fechaTexto($fecha),
                $nombre,
                $absoluta,
                $armado['comprobantes'],
                $armado['lineas'],
            ));
        } catch (Throwable $e) {
            $mensaje = 'El archivo quedó guardado pero el correo falló: '.$e->getMessage();
            $envio->mensaje = $mensaje;
            $envio->save();

            return $this->resultado(false, 'error', $mensaje, (int) $envio->id, $absoluta, $armado['comprobantes'], $armado['lineas']);
        }

        $mensaje = 'Enviado a '.$destinatarios.'. '.$mensajeBase;
        $envio->estado = FacturacionLocalShoppingEnvio::ESTADO_ENVIADO;
        $envio->enviado_at = now();
        $envio->mensaje = $mensaje;
        $envio->save();

        return $this->resultado(true, FacturacionLocalShoppingEnvio::ESTADO_ENVIADO, $mensaje, (int) $envio->id, $absoluta, $armado['comprobantes'], $armado['lineas']);
    }

    /**
     * @return array{contenido:string,comprobantes:int,lineas:int,advertencias:list<string>}
     */
    private function armar(LocalVenta $local, string $fecha): array
    {
        $idsFac = DB::table('facturacion_local_emision as e')
            ->join('venta as v', 'v.id', '=', 'e.venta_id')
            ->where('e.local_venta_id', $local->id)
            ->where('v.fecha', $fecha)
            ->pluck('v.id');
        $idsNc = DB::table('facturacion_local_emision as e')
            ->join('venta as v', 'v.id', '=', 'e.venta_nc_id')
            ->where('e.local_venta_id', $local->id)
            ->where('v.fecha', $fecha)
            ->pluck('v.id');
        $ids = $idsFac->merge($idsNc)->map(fn ($id) => (int) $id)->unique()->filter(fn (int $id) => $id > 0)->values();

        $ventas = $ids->isEmpty()
            ? collect()
            : Venta::query()
                ->with(['venta_impuestos', 'tipotransacciones', 'puntoventas', 'condicionivas'])
                ->whereIn('id', $ids->all())
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();

        return FacturacionLocalShoppingArchivoSupport::armar(
            $ventas,
            $this->pagosPorVenta($ids->all(), $fecha),
            FacturacionLocalShoppingCaballitoSupport::nroLocal(),
            FacturacionLocalShoppingCaballitoSupport::nroContrato(),
            FacturacionLocalShoppingCaballitoSupport::nroCaja(),
        );
    }

    /**
     * @param  list<int>  $ventaIds
     * @return array<int, list<array{nombre:string,es_tarjeta:bool,monto:float}>>
     */
    private function pagosPorVenta(array $ventaIds, string $fecha): array
    {
        if ($ventaIds === []) {
            return [];
        }

        $filas = DB::table('caja_movimiento as m')
            ->join('caja_movimiento_cuentacaja as cc', 'cc.caja_movimiento_id', '=', 'm.id')
            ->join('cuentacaja as cj', 'cj.id', '=', 'cc.cuentacaja_id')
            ->whereIn('m.venta_id', $ventaIds)
            ->get([
                'm.venta_id',
                'cc.monto',
                'cc.cotizacion',
                'cc.moneda_id',
                'cj.nombre',
                'cj.es_tarjeta',
            ]);

        $porVenta = [];
        foreach ($filas as $fila) {
            $cotizacion = (float) $fila->cotizacion;
            $monedaId = (int) ($fila->moneda_id ?? 0);
            if ($cotizacion <= 0) {
                if ($monedaId <= 1) {
                    $cotizacion = 1.0;
                } else {
                    $cotizacion = CotizacionVigenteSupport::ventaValor($fecha, $monedaId);
                    if ($cotizacion <= 0) {
                        throw new \RuntimeException('Sin cotización vigente para la moneda '.$monedaId.' al '.$fecha.'.');
                    }
                }
            }
            $ventaId = (int) $fila->venta_id;
            $porVenta[$ventaId][] = [
                'nombre' => (string) ($fila->nombre ?? ''),
                'es_tarjeta' => (bool) $fila->es_tarjeta,
                'monto' => round((float) $fila->monto * $cotizacion, 2),
            ];
        }

        return $porVenta;
    }

    private function yaEnviado(int $localId, string $fecha): bool
    {
        return FacturacionLocalShoppingEnvio::query()
            ->where('local_venta_id', $localId)
            ->where('fecha_venta', $fecha)
            ->where('estado', FacturacionLocalShoppingEnvio::ESTADO_ENVIADO)
            ->exists();
    }

    private function registrar(
        ?int $localId,
        string $fecha,
        string $nombre,
        ?string $ruta,
        string $estado,
        int $comprobantes,
        int $lineas,
        string $mensaje,
        string $destinatarios,
    ): FacturacionLocalShoppingEnvio {
        return FacturacionLocalShoppingEnvio::query()->create([
            'local_venta_id' => $localId,
            'fecha_venta' => $fecha,
            'nombre_archivo' => $nombre,
            'ruta' => $ruta,
            'destinatarios' => $destinatarios,
            'estado' => $estado,
            'comprobantes' => $comprobantes,
            'lineas' => $lineas,
            'mensaje' => $mensaje,
        ]);
    }

    /**
     * @param  list<string>  $advertencias
     */
    private function resumen(int $comprobantes, int $lineas, array $advertencias): string
    {
        $texto = $comprobantes.' comprobantes, '.$lineas.' líneas.';
        if ($advertencias === []) {
            return $texto;
        }
        $extra = implode(' ', array_slice($advertencias, 0, 8));
        if (count($advertencias) > 8) {
            $extra .= ' …';
        }

        return $texto.' '.$extra;
    }

    private function fechaTexto(string $fecha): string
    {
        return Carbon::parse($fecha)->format('d/m/Y');
    }

    /**
     * @return array{ok:bool,estado:string,mensaje:string,envio_id:?int,ruta:?string,comprobantes:int,lineas:int}
     */
    private function resultado(bool $ok, string $estado, string $mensaje, ?int $envioId, ?string $ruta, int $comprobantes, int $lineas): array
    {
        return [
            'ok' => $ok,
            'estado' => $estado,
            'mensaje' => $mensaje,
            'envio_id' => $envioId,
            'ruta' => $ruta,
            'comprobantes' => $comprobantes,
            'lineas' => $lineas,
        ];
    }
}
