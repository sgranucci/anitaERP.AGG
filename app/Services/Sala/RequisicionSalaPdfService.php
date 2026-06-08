<?php

namespace App\Services\Sala;

use App\Repositories\Sala\RequisicionSalaRepositoryInterface;

class RequisicionSalaPdfService
{
    public function __construct(
        private RequisicionSalaRepositoryInterface $repository,
    ) {
    }

    public function html(int $id): string
    {
        $data = $this->repository->find($id);

        return view('sala.requisicion_sala.pdf', compact('data'))->render();
    }

    /**
     * @return array{contenido: string, nombre: string}
     */
    public function generarBytes(int $id): array
    {
        $data = $this->repository->find($id);
        $html = view('sala.requisicion_sala.pdf', compact('data'))->render();

        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('legal', 'landscape');
        $pdf->loadHTML($html, 'UTF-8');

        $nombre = 'Requisicion_sala_'.preg_replace('/[^\w\-]+/', '_', (string) $data->numerorequisicion).'.pdf';

        return [
            'contenido' => $pdf->output(),
            'nombre' => $nombre,
        ];
    }
}
