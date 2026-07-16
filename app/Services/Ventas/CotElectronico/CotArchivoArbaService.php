<?php

namespace App\Services\Ventas\CotElectronico;

use App\Models\Configuracion\Empresa;
use Carbon\Carbon;

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
            $lineas[] = $this->registroRemito($filaRemito, $importe);
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
    private function registroRemito(array $filaRemito, float $importe): string
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
        $razonSocial = trim((string) ($dest['razon_social'] ?? $filaRemito['cliente_nombre'] ?? ''));
        $destCalle = (string) ($dest['calle'] ?? 'S/N');
        $destNumero = (string) ($dest['numero'] ?? '');
        $destLocalidad = (string) ($dest['localidad'] ?? '');
        $destProvincia = $this->abreviaturaProvincia((string) ($dest['provincia'] ?? 'B'));
        $destCp = preg_replace('/\D+/', '', (string) ($dest['codigo_postal'] ?? '')) ?: '';

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
