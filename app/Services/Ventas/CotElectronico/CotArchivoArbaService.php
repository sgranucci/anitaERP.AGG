<?php

namespace App\Services\Ventas\CotElectronico;

use App\Models\Configuracion\Empresa;
use App\Models\Ventas\Venta;
use App\Support\Ventas\IvaVentas\IvaVentasDesgloseSupport;
use Carbon\Carbon;

class CotArchivoArbaService
{
    /**
     * @param  list<array<string, mixed>>  $remitosSeleccionados
     * @return array{nombre:string,ruta:string,contenido:string,cantidad_remitos:int}
     */
    public function generarArchivo(Carbon $fecha, Empresa $empresa, array $remitosSeleccionados): array
    {
        $cuitEmpresa = $this->soloDigitos($this->cuitEmpresa($empresa));
        $secuencial = $this->proximoSecuencial($fecha, $cuitEmpresa);
        $planta = str_pad((string) config('arba_cot.planta', '000'), 3, '0', STR_PAD_LEFT);
        $puerta = str_pad((string) config('arba_cot.puerta', '002'), 3, '0', STR_PAD_LEFT);
        $fechaTxt = $fecha->format('Ymd');
        $nombre = sprintf(
            'TB_%s_%s%s_%s_%s.txt',
            $cuitEmpresa,
            $planta,
            $puerta,
            $fechaTxt,
            str_pad((string) $secuencial, 6, '0', STR_PAD_LEFT),
        );

        $lineas = [];
        $lineas[] = '01|'.$cuitEmpresa;

        $ventaIds = collect($remitosSeleccionados)->pluck('venta_id')->filter()->unique()->all();
        $ventas = Venta::query()
            ->whereIn('id', $ventaIds)
            ->with([
                'clientes.localidades',
                'clientes.provincias',
                'clientes.condicionivas',
                'clientes.tipodocumentos',
                'venta_emisiones.articulos.unidadesdemedidas',
                'venta_impuestos',
                'puntoventaremito',
            ])
            ->get()
            ->keyBy('id');

        $cantidadRemitos = 0;

        foreach ($remitosSeleccionados as $filaRemito) {
            $venta = $ventas->get((int) ($filaRemito['venta_id'] ?? 0));
            if (! $venta) {
                continue;
            }

            $productos = $this->armarProductos($venta);
            if ($productos === []) {
                continue;
            }

            $importe = (float) ($filaRemito['importe'] ?? $this->calcularImporte($venta));
            $lineas[] = $this->registroRemito($filaRemito, $venta, $importe);
            foreach ($productos as $producto) {
                $lineas[] = $this->registroProducto($producto);
            }
            $cantidadRemitos++;
        }

        $lineas[] = '04|'.$cantidadRemitos;
        $contenido = implode("\n", $lineas)."\n";

        $dir = (string) config('arba_cot.storage_path');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $ruta = $dir.DIRECTORY_SEPARATOR.$nombre;
        file_put_contents($ruta, $contenido);

        return [
            'nombre' => $nombre,
            'ruta' => $ruta,
            'contenido' => $contenido,
            'cantidad_remitos' => $cantidadRemitos,
        ];
    }

    /**
     * @param  array<string, mixed>  $filaRemito
     */
    private function registroRemito(array $filaRemito, Venta $venta, float $importe): string
    {
        $fechaFactura = Carbon::parse($venta->fecha)->startOfDay();
        $fechaTxt = $fechaFactura->format('Ymd');
        $horaSalida = Carbon::now()->format('Hi');
        $sucursal = (int) ($filaRemito['sucursal'] ?? config('facturacion.PUNTOVENTA_REMITO', 1));
        $numeroRemito = (int) ($filaRemito['numero_remito'] ?? 0);
        $codigoUnico = $this->codigoUnicoRemito($sucursal, $numeroRemito);

        $cliente = $venta->clientes;
        $esCf = $this->esConsumidorFinal($cliente?->condicionivas?->nombre ?? '');
        $cuitDest = $this->soloDigitos((string) ($cliente->numerodocumento ?? ''));
        $razonSocial = trim((string) ($venta->nombre ?: ($cliente->nombre ?? '')));
        if (! $esCf) {
            $razonSocial = trim((string) ($cliente->nombre ?? $razonSocial));
        }

        $destCalle = $this->partirCalleNumero((string) ($venta->domicilio ?: ($cliente->domicilio ?? '')));
        $destLocalidad = (string) optional($cliente?->localidades)->nombre ?: (string) ($venta->localidad ?? '');
        $destProvincia = $this->abreviaturaProvincia($cliente?->provincias?->abreviatura ?? 'B');
        $destCp = preg_replace('/\D+/', '', (string) ($venta->codigopostal ?: ($cliente->codigopostal ?? ''))) ?: '';

        $origen = config('arba_cot.origen', []);
        $origenCuit = $this->soloDigitos((string) ($origen['cuit'] ?: $this->cuitEmpresa(null)));
        $origenRazon = (string) ($origen['razon_social'] ?: config('app.name'));
        $origenCalle = (string) ($origen['calle'] ?: '');
        $origenNumero = (string) ($origen['numero'] ?: 'S/N');
        $origenLocalidad = (string) ($origen['localidad'] ?: '');
        $origenProvincia = (string) ($origen['provincia'] ?: 'B');
        $origenCp = preg_replace('/\D+/', '', (string) ($origen['codigo_postal'] ?? '')) ?: '';

        $patente = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) ($filaRemito['patente'] ?? '')));
        $cuitTransportista = $this->soloDigitos((string) ($filaRemito['cuit_chofer'] ?? ''));
        $tipoRecorrido = (string) config('arba_cot.tipo_recorrido_default', 'U');
        $importeCampo = $this->formatImporte($importe);

        $campos = [
            '02',
            $fechaTxt,
            $codigoUnico,
            $fechaTxt,
            $horaSalida,
            'E',
            $esCf ? '1' : '0',
            $esCf ? 'DNI' : '',
            $esCf ? $this->soloDigitos((string) ($cliente->numerodocumento ?? '')) : '',
            $esCf ? '' : $cuitDest,
            $razonSocial,
            '0',
            $destCalle['calle'],
            $destCalle['numero'],
            'S/N',
            '',
            '',
            '',
            $destCp,
            $destLocalidad,
            $destProvincia,
            '',
            'NO',
            $origenCuit,
            $origenRazon,
            '0',
            $origenCalle,
            $origenNumero,
            'S/N',
            '',
            '',
            '',
            $origenCp,
            $origenLocalidad,
            $origenProvincia,
            $cuitTransportista,
            $tipoRecorrido,
            '',
            '',
            '',
            $patente,
            '',
            '0',
            $importeCampo,
        ];

        return implode('|', $campos);
    }

    /**
     * @param  array<string, mixed>  $producto
     */
    private function registroProducto(array $producto): string
    {
        return implode('|', [
            '03',
            (string) $producto['codigo_nomenclador'],
            (string) $producto['codigo_umd'],
            $this->formatCantidad((float) $producto['cantidad']),
            (string) $producto['sku'],
            $this->truncar((string) $producto['descripcion'], 40),
            $this->truncar((string) $producto['umd_descripcion'], 20),
            $this->formatCantidad((float) $producto['cantidad']),
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function armarProductos(Venta $venta): array
    {
        $productos = [];

        foreach ($venta->venta_emisiones as $item) {
            $articulo = $item->articulos;
            if (! $articulo) {
                continue;
            }

            $sku = (string) ($articulo->sku ?? '');
            if ($sku === '' || $sku === 'texto' || $sku === '0000000000903') {
                continue;
            }

            $um = strtoupper((string) optional($articulo->unidadesdemedidas)->abreviatura);
            $cantidad = (float) $item->cantidad;
            if (str_starts_with($um, 'UN') && (float) ($articulo->coeficienteconversion ?? 0) > 0) {
                $cantidad *= (float) $articulo->coeficienteconversion;
            }

            if ($cantidad <= 0) {
                continue;
            }

            $codigoNomenclador = trim((string) ($articulo->nomenclador ?? ''));
            if ($codigoNomenclador === '') {
                $codigoNomenclador = '1';
            }

            $codigoUmd = trim((string) ($articulo->unidadmedidanomenclador ?? ''));
            if ($codigoUmd === '') {
                $codigoUmd = '3';
            }

            $clave = $sku.'|'.$codigoNomenclador;
            if (! isset($productos[$clave])) {
                $productos[$clave] = [
                    'sku' => $sku,
                    'descripcion' => (string) ($articulo->descripcion ?? $sku),
                    'codigo_nomenclador' => $codigoNomenclador,
                    'codigo_umd' => $codigoUmd,
                    'umd_descripcion' => (string) optional($articulo->unidadesdemedidas)->nombre ?: 'KILO',
                    'cantidad' => 0.0,
                ];
            }

            $productos[$clave]['cantidad'] += $cantidad;
        }

        return array_values($productos);
    }

    private function codigoUnicoRemito(int $sucursal, int $numero): string
    {
        $tipo = (string) config('arba_cot.codigo_comprobante_remito', '091');
        $digitosSucursal = (int) config('facturacion.DIGITOS_SUCURSAL', 5);
        $digitosComp = (int) config('facturacion.DIGITOS_COMPROBANTE', 8);

        return $tipo
            .str_pad((string) $sucursal, $digitosSucursal, '0', STR_PAD_LEFT)
            .str_pad((string) $numero, $digitosComp, '0', STR_PAD_LEFT);
    }

    private function calcularImporte(Venta $venta): float
    {
        $desglose = IvaVentasDesgloseSupport::columnasDesdeVenta($venta);
        $total = (float) ($desglose['neto_gravado'] ?? 0)
            + (float) ($desglose['exento'] ?? 0)
            + (float) ($desglose['no_gravado'] ?? 0);

        return $total > 0 ? $total : abs((float) ($venta->total ?? 0));
    }

    private function cuitEmpresa(?Empresa $empresa): string
    {
        $origen = (string) config('arba_cot.origen.cuit', '');
        if ($origen !== '') {
            return $origen;
        }

        if ($empresa) {
            return (string) ($empresa->nroinscripcion ?? '');
        }

        return (string) config('arca_wsfe.cuit_representada', '');
    }

    private function proximoSecuencial(Carbon $fecha, string $cuit): int
    {
        $dir = (string) config('arba_cot.storage_path');
        if (! is_dir($dir)) {
            return 1;
        }

        $patron = 'TB_'.$cuit.'_*_'.$fecha->format('Ymd').'_*.txt';
        $max = 0;
        foreach (glob($dir.DIRECTORY_SEPARATOR.$patron) ?: [] as $archivo) {
            if (preg_match('/_(\d{6})\.txt$/', $archivo, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return $max + 1;
    }

    private function formatCantidad(float $cantidad): string
    {
        return (string) (int) round($cantidad * 100);
    }

    private function formatImporte(float $importe): string
    {
        return (string) (int) round(abs($importe) * 100);
    }

    private function soloDigitos(string $valor): string
    {
        return preg_replace('/\D+/', '', $valor) ?? '';
    }

    private function esConsumidorFinal(string $condicionIva): bool
    {
        $condicionIva = strtoupper(trim($condicionIva));

        return str_contains($condicionIva, 'CONSUMIDOR');
    }

    /** @return array{calle:string,numero:string} */
    private function partirCalleNumero(string $domicilio): array
    {
        $domicilio = trim($domicilio);
        if ($domicilio === '') {
            return ['calle' => 'S/N', 'numero' => ''];
        }

        if (preg_match('/^(.+?)\s+(\d+[A-Za-z]?)$/', $domicilio, $m)) {
            return ['calle' => trim($m[1]), 'numero' => trim($m[2])];
        }

        return ['calle' => $domicilio, 'numero' => ''];
    }

    private function abreviaturaProvincia(?string $abreviatura): string
    {
        $abreviatura = strtoupper(trim((string) $abreviatura));

        return $abreviatura !== '' ? $abreviatura : 'B';
    }

    private function truncar(string $texto, int $max): string
    {
        $texto = trim($texto);
        if (strlen($texto) <= $max) {
            return $texto;
        }

        return substr($texto, 0, $max);
    }
}
