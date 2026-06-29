<?php

namespace App\Services\Configuracion\Handlers;

use App\Contracts\Configuracion\ModuloAvisoHandlerInterface;
use App\Repositories\Sala\RequisicionSalaRepositoryInterface;
use App\Services\Sala\RequisicionSalaArbolIntegracionService;
use App\Services\Sala\RequisicionSalaPdfService;
use App\Support\Navegacion\ModoConsultaUrlSupport;

class SalaRequisicionSalaCreacionAvisoHandler implements ModuloAvisoHandlerInterface
{
    public function __construct(
        private RequisicionSalaRepositoryInterface $repository,
        private RequisicionSalaPdfService $pdfService,
    ) {
    }

    public function contextoFiltro(int $entityId): array
    {
        $req = $this->repository->find($entityId);

        return [
            'empresa_id' => $req->empresa_id ? (int) $req->empresa_id : null,
            'centrocosto_id' => $req->centrocosto_id ? (int) $req->centrocosto_id : null,
        ];
    }

    public function placeholders(int $entityId): array
    {
        $req = $this->repository->find($entityId);
        $cc = $req->centrocostos;

        return [
            'numero' => (string) ($req->numerorequisicion ?? $entityId),
            'solicitante' => (string) (optional($req->solicitante)->nombre ?? optional($req->usuarios)->nombre ?? '—'),
            'empresa' => (string) (optional($req->empresas)->nombre ?? '—'),
            'centro_costo' => trim((optional($cc)->codigo ?? '').' '.(optional($cc)->nombre ?? '')) ?: '—',
            'fecha' => $req->fecha ? date('d/m/Y', strtotime($req->fecha)) : '—',
            'estado' => (string) ($req->estado ?? '—'),
            'deposito' => (string) (optional($req->depositos)->nombre ?? '—'),
            'zona_sala' => (string) (optional($req->zona_salas)->nombre ?? '—'),
            'prioridad' => (string) (optional($req->prioridad_salas)->nombre ?? '—'),
        ];
    }

    public function linkConsulta(int $entityId): ?string
    {
        $movs = app(RequisicionSalaArbolIntegracionService::class)->findPorRequisicionSala($entityId);
        foreach ($movs as $mov) {
            if (filled($mov->hashvisualizar ?? null)) {
                return ModoConsultaUrlSupport::urlVisualizarRequisicionSala(
                    $entityId,
                    (string) $mov->hashvisualizar
                );
            }
        }

        return ModoConsultaUrlSupport::urlAbsolutaConConsulta('sala/requisicion-sala/'.$entityId.'/editar');
    }

    public function generarPdf(int $entityId): ?array
    {
        return $this->pdfService->generarBytes($entityId);
    }
}
