<?php

namespace App\Services\Uif;

use App\Support\Uif\ClienteUifInformeReportablesSupport;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ClienteUifExportacionXmlService
{
    /**
     * @return array{cantidad: int, total: float, directorio: string, archivos: list<string>}
     */
    public function exportar(string $periodo, int $empresaId, Collection $premios): array
    {
        $directorioRelativo = ClienteUifInformeReportablesSupport::directorioExportacionXml($periodo, $empresaId);
        $this->prepararDirectorioExportacion($directorioRelativo);

        $disk = Storage::disk('local');
        $archivos = [];
        $total = 0.0;
        $secuencia = 0;

        foreach ($premios as $premio) {
            $secuencia++;
            $clienteId = ClienteUifInformeReportablesSupport::idClienteInforme($premio);
            $nombreArchivo = $secuencia.'_'.$clienteId.'.xml';
            $rutaRelativa = $directorioRelativo.'/'.$nombreArchivo;
            $contenido = $this->renderOperacionXml($premio);

            if (! $disk->put($rutaRelativa, $contenido)) {
                throw new RuntimeException('No se pudo grabar el archivo XML: '.$nombreArchivo);
            }

            $archivos[] = $nombreArchivo;
            $total += (float) ($premio->monto ?? 0);
        }

        return [
            'cantidad' => count($archivos),
            'total' => $total,
            'directorio' => $directorioRelativo,
            'archivos' => $archivos,
        ];
    }

    private function prepararDirectorioExportacion(string $directorioRelativo): void
    {
        $disk = Storage::disk('local');
        $base = trim((string) config('uif.EXPORTACION_XML_PATH', 'tmp/exportacion_uif'), '/');
        $baseAbsoluta = storage_path('app/'.$base);

        $this->asegurarDirectorioEscritura($baseAbsoluta);

        $rutaAbsoluta = storage_path('app/'.$directorioRelativo);

        if ($disk->exists($directorioRelativo)) {
            foreach ($disk->files($directorioRelativo) as $archivo) {
                $disk->delete($archivo);
            }
        }

        $this->asegurarDirectorioEscritura($rutaAbsoluta);

        if (! File::isDirectory($rutaAbsoluta) || ! is_writable($rutaAbsoluta)) {
            throw new RuntimeException(
                'No se pudo crear el directorio de exportacion UIF: storage/app/'.$directorioRelativo
                .'. Verifique permisos de escritura del webserver (www-data) en storage/app/'.$base.'.'
            );
        }
    }

    private function asegurarDirectorioEscritura(string $rutaAbsoluta): void
    {
        if (! File::isDirectory($rutaAbsoluta)) {
            try {
                File::makeDirectory($rutaAbsoluta, 0777, true);
            } catch (\Throwable) {
                if (! File::isDirectory($rutaAbsoluta)) {
                    throw new RuntimeException('No se pudo crear el directorio: '.$rutaAbsoluta);
                }
            }
        }

        if (! is_writable($rutaAbsoluta)) {
            @chmod($rutaAbsoluta, 0777);
        }
    }

    public function renderOperacionXml(object $premio): string
    {
        $lineas = [];
        $lineas[] = '<?xml version="1.0" encoding="utf-8"?>';
        $lineas[] = '<Operacion>';
        $lineas[] = "\t<Apostadores_cobranza_de_premios_mayores_a_50000 Version=\"1.1\">";

        $partesNombre = explode(' ', trim((string) ($premio->nombrecliente ?? '')), 2);
        $apellido = $partesNombre[0] ?? '';
        $nombre = $partesNombre[1] ?? '';

        $lineas[] = "\t\t<Apellido>".$this->escaparXml($apellido).'</Apellido>';
        $lineas[] = "\t\t<Nombre>".$this->escaparXml($nombre).'</Nombre>';
        $lineas[] = "\t\t<Nacionalidad>".$this->escaparXml(ClienteUifInformeReportablesSupport::paisInforme($premio->nombrepais ?? '')).'</Nacionalidad>';
        $lineas[] = "\t\t<Tipo_Documento>".$this->escaparXml($this->tipoDocumentoXml($premio)).'</Tipo_Documento>';
        $lineas[] = "\t\t<N94mero_Documento>".$this->escaparXml((string) ($premio->numerodocumento ?? '')).'</N94mero_Documento>';
        $lineas[] = "\t\t<Calle>".$this->escaparXml((string) ($premio->domicilio ?? '')).'</Calle>';
        $lineas[] = "\t\t<Nro>0</Nro>";
        $lineas[] = "\t\t<Piso>".$this->escaparXml((string) ($premio->piso ?? '')).'</Piso>';
        $lineas[] = "\t\t<Departamento>".$this->escaparXml((string) ($premio->departamento ?? '')).'</Departamento>';
        $lineas[] = "\t\t<Localidad>".$this->escaparXml((string) ($premio->nombrelocalidad ?? '')).'</Localidad>';
        $lineas[] = "\t\t<Provincia>".$this->escaparXml((string) ($premio->nombreprovincia ?? '')).'</Provincia>';
        $lineas[] = "\t\t<Pa92s>".$this->escaparXml(ClienteUifInformeReportablesSupport::paisInforme($premio->nombrepais ?? '')).'</Pa92s>';

        $lineas[] = "\t\t<Radicada_en_el_Exterior>false</Radicada_en_el_Exterior>";
        $lineas[] = "\t\t<Radicada_en_Para92so_Fiscal>false</Radicada_en_Para92so_Fiscal>";
        $lineas[] = "\t\t<Es_Peps>false</Es_Peps>";

        $fechaOperacion = $this->formatFechaOperacionXml($premio->fechaentrega ?? null);
        $lineas[] = "\t\t<Fecha_de_Operaci93n>".$fechaOperacion.'</Fecha_de_Operaci93n>';
        $lineas[] = "\t\t<Tipo_de_Moneda>Peso Argentino</Tipo_de_Moneda>";

        $monto = (float) ($premio->monto ?? 0);
        $lineas[] = "\t\t<Monto_Total>".floor($monto).'</Monto_Total>';
        $lineas[] = "\t\t<Monto_Total_en_Pesos>".floor($monto).'</Monto_Total_en_Pesos>';
        $lineas[] = "\t\t<Pago_en_favor_de_Terceros>false</Pago_en_favor_de_Terceros>";
        $lineas[] = "\t\t<Pago>";
        $lineas[] = "\t\t\t<Forma_de_Pago>Efectivo</Forma_de_Pago>";
        $lineas[] = "\t\t\t<Porcentaje_del_pago_total>100</Porcentaje_del_pago_total>";
        $lineas[] = "\t\t\t<Fecha_de_pago>".$fechaOperacion.'</Fecha_de_pago>';
        $lineas[] = "\t\t</Pago>";
        $lineas[] = "\t</Apostadores_cobranza_de_premios_mayores_a_50000>";
        $lineas[] = '</Operacion>';

        return implode("\n", $lineas)."\n";
    }

    private function tipoDocumentoXml(object $premio): string
    {
        $abreviatura = strtoupper(trim((string) ($premio->abreviaturatipodocumento ?? '')));
        $esArgentina = ClienteUifInformeReportablesSupport::esArgentina(
            isset($premio->pais_uif_id) ? (int) $premio->pais_uif_id : null,
            $premio->nombrepais ?? null
        );

        return match ($abreviatura) {
            'DNI' => $esArgentina ? 'Documento Nacional de Identidad' : 'Documento EXT',
            'LE' => 'Libreta de Enrolamiento',
            'LC' => 'Libreta Cívica',
            'CDI' => 'Documento EXT',
            'PAS' => $esArgentina ? 'Pasaporte EXT' : 'Pasaporte',
            default => (string) ($premio->nombretipodocumento ?? $abreviatura),
        };
    }

    private function formatFechaOperacionXml($fechaentrega): string
    {
        if ($fechaentrega === null || $fechaentrega === '') {
            return '';
        }

        return Carbon::parse($fechaentrega)->format('Y-m-d').'T00:00:00-03:00';
    }

    private function escaparXml(string $valor): string
    {
        return htmlspecialchars($valor, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
