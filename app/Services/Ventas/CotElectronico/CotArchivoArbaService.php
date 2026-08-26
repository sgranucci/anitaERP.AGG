<?php

namespace App\Services\Ventas\CotElectronico;

use App\Models\Configuracion\Empresa;
use App\Models\Ventas\Puntoventa;
use App\Support\Ventas\ArbaCotProvinciaSupport;
use App\Support\Ventas\CotImporteRemitoSupport;
use Carbon\Carbon;
use RuntimeException;

class CotArchivoArbaService
{
    public function __construct(
        private CotRemitoConsultaService $consultaService,
    ) {}

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

        $cantidadRemitos = 0;

        foreach ($remitosSeleccionados as $filaRemito) {
            $productos = $this->consultaService->productosParaArchivo($filaRemito);
            if ($productos === []) {
                continue;
            }

            $importe = (float) ($filaRemito['importe'] ?? 0);
            if (empty($filaRemito['importe_ok']) || ! CotImporteRemitoSupport::esValidoParaCot($importe)) {
                throw new RuntimeException(
                    'No se arma el archivo COT: el remito '
                    .(int) ($filaRemito['numero_remito'] ?? 0)
                    .' no tiene el neto gravado + exento de la factura.'
                );
            }
            $lineas[] = $this->registroRemito($filaRemito, $importe, $empresa);
            foreach ($productos as $producto) {
                $lineas[] = $this->registroProducto($producto);
            }
            $cantidadRemitos++;
        }

        $lineas[] = '04|'.$cantidadRemitos;
        // ARBA exige CRLF; con solo LF el servlet no reconoce el registro 01 HEADER.
        $contenido = implode("\r\n", $lineas)."\r\n";

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
    private function registroRemito(array $filaRemito, float $importe, Empresa $empresa): string
    {
        $fechaRemito = Carbon::parse((string) ($filaRemito['fecha_remito'] ?? now()->toDateString()))->startOfDay();
        $fechaTxt = $fechaRemito->format('Ymd');
        $horaSalida = Carbon::now()->format('Hi');
        $sucursal = (int) ($filaRemito['sucursal'] ?? config('facturacion.PUNTOVENTA_REMITO', 1));
        $numeroRemito = (int) ($filaRemito['numero_remito'] ?? 0);
        $codigoUnico = $this->codigoUnicoRemito($sucursal, $numeroRemito);

        $dest = is_array($filaRemito['destinatario'] ?? null) ? $filaRemito['destinatario'] : [];
        $esCf = (bool) ($dest['es_cf'] ?? false);
        $cuitDest = $this->soloDigitos((string) ($dest['cuit'] ?? ''));
        $razonSocial = $this->truncar((string) ($dest['razon_social'] ?? $filaRemito['cliente_nombre'] ?? ''), 50);
        // Diseño ARBA: NUMERO es N(5). Si COMPLE = S/N, NUMERO debe ser 0 o blanco.
        // p-cot: calle = domicilio completo (MAYOR IRUSTA 2921), comple = S/N.
        $destCalle = $this->calleCompletaAnita(
            (string) ($dest['calle'] ?? ''),
            (string) ($dest['numero'] ?? ''),
        );
        $destNumero = '0';
        $destLocalidad = $this->truncar((string) ($dest['localidad'] ?? ''), 50);
        $destProvincia = ArbaCotProvinciaSupport::codigo((string) ($dest['provincia'] ?? 'B'));
        $destCp = preg_replace('/\D+/', '', (string) ($dest['codigo_postal'] ?? '')) ?: '';

        $origen = $this->datosOrigen($empresa, $sucursal);
        $origenCuit = $origen['cuit'];
        $origenRazon = $origen['razon_social'];
        $origenCalle = $origen['calle'];
        $origenNumero = $origen['numero'];
        $origenLocalidad = $origen['localidad'];
        $origenProvincia = $origen['provincia'];
        $origenCp = $origen['codigo_postal'];

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
            $esCf ? $this->soloDigitos((string) ($dest['documento'] ?? '')) : '',
            $esCf ? '' : $cuitDest,
            $razonSocial,
            '0',
            $destCalle,
            $destNumero,
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

    private function codigoUnicoRemito(int $sucursal, int $numero): string
    {
        $tipo = (string) config('arba_cot.codigo_comprobante_remito', '091');
        $digitosSucursal = (int) config('facturacion.DIGITOS_SUCURSAL', 5);
        $digitosComp = (int) config('facturacion.DIGITOS_COMPROBANTE', 8);

        return $tipo
            .str_pad((string) $sucursal, $digitosSucursal, '0', STR_PAD_LEFT)
            .str_pad((string) $numero, $digitosComp, '0', STR_PAD_LEFT);
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

    /**
     * Origen como p-cot (sucursal / punto de venta), no el domicilio partido de empresa.
     * En El Bierzo el PV tiene Bragado 6759 / BRAGADO / C (CABA) / 1407.
     *
     * @return array{cuit:string,razon_social:string,calle:string,numero:string,localidad:string,provincia:string,codigo_postal:string}
     */
    private function datosOrigen(Empresa $empresa, int $sucursalRemito): array
    {
        $config = config('arba_cot.origen', []);
        $puntoventa = $this->resolverPuntoventaOrigen($sucursalRemito);
        $empresa->loadMissing(['provincia', 'localidad']);

        $origenExplicit = trim((string) ($config['calle'] ?? '')) !== '';
        $cuit = $this->soloDigitos((string) ($config['cuit'] ?: $this->cuitEmpresa($empresa)));
        $razon = trim((string) ($config['razon_social'] ?: $empresa->nombre ?: config('app.name')));
        $calle = trim((string) ($origenExplicit ? $config['calle'] : ($puntoventa?->domicilio ?: $empresa->domicilio ?: 'S/N')));
        $localidad = trim((string) (
            ($origenExplicit && ($config['localidad'] ?? '') !== '')
                ? $config['localidad']
                : (optional($puntoventa?->localidades)->nombre
                    ?: optional($empresa->localidad)->nombre
                    ?: '')
        ));
        $provinciaPv = trim((string) (optional($puntoventa?->provincias)->abreviatura ?? ''));
        $provincia = ArbaCotProvinciaSupport::codigo((string) (
            ($origenExplicit && ($config['provincia'] ?? '') !== '')
                ? $config['provincia']
                : ($provinciaPv
                    ?: optional($empresa->provincia)->abreviatura
                    ?: 'C')
        ));
        $cp = preg_replace(
            '/\D+/',
            '',
            (string) (
                ($origenExplicit && ($config['codigo_postal'] ?? '') !== '')
                    ? $config['codigo_postal']
                    : ($puntoventa?->codigopostal ?: $empresa->codigopostal ?: '')
            )
        ) ?: '';

        return [
            'cuit' => $cuit,
            'razon_social' => $this->truncar($razon, 50),
            'calle' => $this->truncar($calle, 40),
            'numero' => '0',
            'localidad' => $this->truncar($localidad, 50),
            'provincia' => $provincia,
            'codigo_postal' => $cp,
        ];
    }

    private function resolverPuntoventaOrigen(int $sucursalRemito): ?Puntoventa
    {
        $codigos = [];
        if ($sucursalRemito > 0) {
            $codigos[] = str_pad((string) $sucursalRemito, 5, '0', STR_PAD_LEFT);
            $codigos[] = (string) $sucursalRemito;
        }
        $codigos[] = '00001';
        $codigos[] = '1';

        $filas = Puntoventa::query()
            ->with(['localidades', 'provincias'])
            ->whereIn('codigo', array_unique($codigos))
            ->get();

        return $filas->first(function (Puntoventa $pv) {
            return trim((string) $pv->domicilio) !== ''
                && trim((string) $pv->codigopostal) !== ''
                && (int) ($pv->localidad_id ?? 0) > 0;
        }) ?? $filas->first();
    }

    private function calleCompletaAnita(string $calle, string $numero): string
    {
        $calle = trim($calle);
        $numero = trim($numero);
        if ($numero !== '' && strtoupper($numero) !== 'S/N' && ! str_ends_with($calle, $numero)) {
            $calle = trim($calle.' '.$numero);
        }

        return $this->truncar($calle !== '' ? $calle : 'S/N', 40);
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
