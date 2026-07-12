<?php

namespace App\Services\Sala;

use App\Models\Sala\RequisicionSala;
use App\Models\Stock\Depmae;
use App\Services\Stock\TransferenciaMercaderiaPdfService;
use App\Traits\Sala\RequisicionSalaArticuloEstadoParcialTrait;
use Illuminate\Support\Str;
use Jurosh\PDFMerge\PDFMerger;

class CumplirRequisicionSalaPdfService
{
    use RequisicionSalaArticuloEstadoParcialTrait;

    public const SESSION_KEY = 'cumple_requisicion_sala_pdf';

    public function __construct(
        private TransferenciaMercaderiaPdfService $transferenciaPdfService,
        private CumplimientoRequisicionSalaImpresionService $impresionService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $impresion
     */
    public function guardarEnSesion(array $impresion): string
    {
        $token = (string) Str::uuid();
        session([self::SESSION_KEY => array_merge($impresion, [
            'token' => $token,
            'generado_en' => now()->format('d/m/Y H:i'),
        ])]);

        return $token;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function leerDesdeSesion(?string $token = null): ?array
    {
        $data = session(self::SESSION_KEY);
        if (! is_array($data) || ($data['filas'] ?? []) === []) {
            return null;
        }
        if ($token !== null && $token !== '' && ($data['token'] ?? '') !== $token) {
            return null;
        }

        return $data;
    }

    /**
     * @return array{contenido: string, nombre: string}
     */
    public function generarBytesDesdeCumplimientoId(int $cumplimientoId): array
    {
        $data = $this->impresionService->armarDesdeId($cumplimientoId);

        return $this->generarBytesDesdeDatos($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{contenido: string, nombre: string}
     */
    public function generarBytesDesdeDatos(array $data): array
    {
        $html = view('sala.cumplir_requisicion_sala.pdf', ['data' => $data])->render();
        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('legal', 'landscape');
        $pdf->loadHTML($html, 'UTF-8');

        $ref = (string) ($data['referencia'] ?? 'cumple');
        $nombre = 'Cumple_requisicion_sala_'.preg_replace('/[^\w\-]+/', '_', $ref).'.pdf';
        $cumpleBytes = $pdf->output();

        return [
            'contenido' => $cumpleBytes,
            'nombre' => $nombre,
        ];
    }

    /**
     * @return array{contenido: string, nombre: string}
     */
    public function generarBytes(?string $token = null): array
    {
        $data = $this->leerDesdeSesion($token);
        if ($data === null) {
            throw new \RuntimeException('No hay datos de cumplimiento para imprimir.');
        }

        return $this->generarBytesDesdeDatos($data);
    }

    /**
     * @param  list<int>  $transferenciaIds
     * @return array{contenido: string, nombre: string}
     */
    private function fusionarConTransferencias(string $cumpleBytes, array $transferenciaIds, string $nombre): array
    {
        $dir = $this->resolverDirectorioTemporal();

        $temporales = [];
        try {
            $cumpleTmp = $dir.'/cumple_'.uniqid('', true).'.pdf';
            if (file_put_contents($cumpleTmp, $cumpleBytes) === false) {
                throw new \RuntimeException('No se pudo escribir el PDF temporal en '.$dir.'. Verifique permisos de escritura.');
            }
            $temporales[] = $cumpleTmp;

            foreach ($transferenciaIds as $transferenciaId) {
                try {
                    $doc = $this->transferenciaPdfService->generarComPdf($transferenciaId);
                    $tmTmp = $dir.'/tm_'.uniqid('', true).'.pdf';
                    if (file_put_contents($tmTmp, $doc['bytes']) === false) {
                        throw new \RuntimeException('No se pudo escribir el PDF de transferencia en '.$dir.'.');
                    }
                    $temporales[] = $tmTmp;
                } catch (\Throwable) {
                    // Si falla un comprobante TM, se imprime igual el cumplimiento.
                }
            }

            if (count($temporales) === 1) {
                return [
                    'contenido' => file_get_contents($temporales[0]) ?: $cumpleBytes,
                    'nombre' => $nombre,
                ];
            }

            $merger = new PDFMerger;
            foreach ($temporales as $ruta) {
                $merger->addPDF($ruta, 'all', 'horizontal');
            }
            $mergedTmp = $dir.'/cumple_merged_'.uniqid('', true).'.pdf';
            $merger->merge('file', $mergedTmp);
            $temporales[] = $mergedTmp;

            return [
                'contenido' => (string) file_get_contents($mergedTmp),
                'nombre' => $nombre,
            ];
        } finally {
            foreach ($temporales as $ruta) {
                if (is_file($ruta)) {
                    @unlink($ruta);
                }
            }
        }
    }

    private function resolverDirectorioTemporal(): string
    {
        $dir = storage_path('pdf/tmp');
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new \RuntimeException('No se pudo crear el directorio temporal para PDF: '.$dir);
        }
        if (! is_writable($dir)) {
            throw new \RuntimeException('El directorio temporal de PDF no tiene permisos de escritura: '.$dir);
        }

        return $dir;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    private function idsTransferenciasDesdeImpresion(array $data): array
    {
        $ids = [];
        foreach ($data['transferencias'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  list<array<string, mixed>>  $cabeceras
     */
    public static function armarReferenciaImpresion(array $cabeceras): string
    {
        if ($cabeceras === []) {
            return 'cumple';
        }
        if (count($cabeceras) === 1) {
            return (string) ($cabeceras[0]['numerorequisicion'] ?? $cabeceras[0]['id'] ?? 'cumple');
        }

        return 'multi_'.count($cabeceras);
    }

    public static function armarCabeceraDesdeRequisicion(RequisicionSala $req, ?Depmae $depositoOrigen = null): array
    {
        return [
            'id' => $req->id,
            'numerorequisicion' => $req->numerorequisicion,
            'fecha' => optional($req->fecha)->format('d/m/Y'),
            'empresa' => $req->empresas?->nombre,
            'deposito_destino' => $req->depositos?->nombre,
            'deposito_destino_codigo' => $req->depositos?->codigo,
            'deposito_origen' => $depositoOrigen?->nombre,
            'deposito_origen_codigo' => $depositoOrigen?->codigo,
            'centrocosto' => trim(($req->centrocostos?->codigo ?? '').' '.($req->centrocostos?->nombre)),
        ];
    }
}
