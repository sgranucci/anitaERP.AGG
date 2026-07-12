<?php

namespace App\Services\Compras;

use App\Models\Compras\Requisicion;
use App\Models\Stock\Depmae;
use Illuminate\Support\Str;

class CumplirRequisicionCompraPdfService
{
    public const SESSION_KEY = 'cumple_requisicion_compra_pdf';

    public function __construct(
        private CumplimientoRequisicionCompraImpresionService $impresionService,
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
        $html = view('compras.cumplir_requisicion_compra.pdf', ['data' => $data])->render();
        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('legal', 'landscape');
        $pdf->loadHTML($html, 'UTF-8');

        $ref = (string) ($data['referencia'] ?? 'cumple');
        $nombre = 'Cumple_requisicion_compra_'.preg_replace('/[^\w\-]+/', '_', $ref).'.pdf';

        return [
            'contenido' => $pdf->output(),
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

    /**
     * @return array<string, mixed>
     */
    public static function armarCabeceraDesdeRequisicion(
        Requisicion $req,
        ?Depmae $depositoOrigen = null,
        ?Depmae $depositoDestino = null
    ): array {
        return [
            'id' => $req->id,
            'numerorequisicion' => $req->numerorequisicion,
            'fecha' => $req->fecha ? \Carbon\Carbon::parse($req->fecha)->format('d/m/Y') : null,
            'empresa' => $req->empresas?->nombre,
            'deposito_origen' => $depositoOrigen?->nombre,
            'deposito_origen_codigo' => $depositoOrigen?->codigo,
            'deposito_destino' => $depositoDestino?->nombre,
            'deposito_destino_codigo' => $depositoDestino?->codigo,
            'centrocosto' => trim(($req->centrocostos?->codigo ?? '').' '.($req->centrocostos?->nombre)),
        ];
    }
}
