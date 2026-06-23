<?php

namespace App\Services\Sala;

use App\Models\Sala\RequisicionSala;
use App\Models\Stock\Depmae;
use App\Traits\Sala\RequisicionSalaArticuloEstadoParcialTrait;
use Illuminate\Support\Str;

class CumplirRequisicionSalaPdfService
{
    use RequisicionSalaArticuloEstadoParcialTrait;

    public const SESSION_KEY = 'cumple_requisicion_sala_pdf';

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
    public function generarBytes(?string $token = null): array
    {
        $data = $this->leerDesdeSesion($token);
        if ($data === null) {
            throw new \RuntimeException('No hay datos de cumplimiento para imprimir.');
        }

        $html = view('sala.cumplir_requisicion_sala.pdf', ['data' => $data])->render();
        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('legal', 'landscape');
        $pdf->loadHTML($html, 'UTF-8');

        $ref = (string) ($data['referencia'] ?? 'cumple');
        $nombre = 'Cumple_requisicion_sala_'.preg_replace('/[^\w\-]+/', '_', $ref).'.pdf';

        return [
            'contenido' => $pdf->output(),
            'nombre' => $nombre,
        ];
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
