<?php

namespace App\Services\Ventas\Gastronomia;

use App\Models\Ventas\CuentaGastronomia;
use App\Models\Ventas\JornadaGastronomia;
use App\Models\Ventas\TurnoOperativoGastronomia;
use App\Repositories\Ventas\JornadaGastronomiaRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Apertura/cierre de jornada operativa por empresa (solo gastronomía).
 * fecha factura = día calendario real; fecha jornada = día de turno abierto.
 */
final class GastronomiaJornadaService
{
    public function __construct(
        private readonly JornadaGastronomiaRepositoryInterface $jornadaRepository,
    ) {
    }

    public function jornadaAbierta(int $empresaId): ?JornadaGastronomia
    {
        if ($empresaId <= 0) {
            return null;
        }

        return $this->jornadaRepository->jornadaAbiertaPorEmpresa($empresaId);
    }

    public function exigirJornadaAbierta(int $empresaId): JornadaGastronomia
    {
        $jornada = $this->jornadaAbierta($empresaId);
        if ($jornada === null) {
            throw new InvalidArgumentException(
                'No hay jornada abierta para esta empresa. Abra la jornada en Ventas → Gastronomía → Jornada.'
            );
        }

        return $jornada;
    }

    /**
     * @return array{fechafactura:string,fechajornada:string,jornada_id:int}
     */
    public function fechasParaEmision(int $empresaId): array
    {
        $jornada = $this->exigirJornadaAbierta($empresaId);

        return [
            'fechafactura' => Carbon::today()->format('Y-m-d'),
            'fechajornada' => $jornada->fecha_jornada->format('Y-m-d'),
            'jornada_id' => (int) $jornada->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function aplicarFechasAlPayload(array $payload, int $empresaId): array
    {
        $fechas = $this->fechasParaEmision($empresaId);
        $payload['fechafactura'] = $fechas['fechafactura'];
        $payload['fechajornada'] = $fechas['fechajornada'];
        $payload['jornada_gastronomia_id'] = $fechas['jornada_id'];

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function estadoParaEmpresa(int $empresaId): array
    {
        $abierta = $this->jornadaAbierta($empresaId);
        $hoy = Carbon::today()->format('Y-m-d');

        return [
            'empresa_id' => $empresaId,
            'jornada_abierta' => $abierta !== null,
            'jornada_id' => $abierta?->id,
            'fecha_jornada' => $abierta?->fecha_jornada?->format('Y-m-d'),
            'fecha_factura_hoy' => $hoy,
            'apertura_en' => $abierta?->apertura_en?->format('Y-m-d H:i:s'),
            'usuario_apertura' => $abierta?->usuarioApertura?->nombre,
            'observacion_apertura' => $abierta?->observacion_apertura,
            'puede_abrir' => $abierta === null,
            'puede_cerrar' => $abierta !== null,
            'motivo_no_puede_abrir' => $abierta !== null
                ? $this->describirJornadaAbierta($abierta)
                : null,
            'errores_cierre' => $abierta !== null
                ? $this->erroresAntesDeCerrar($empresaId)
                : [],
        ];
    }

    /**
     * Valida reglas de negocio antes de abrir; lanza InvalidArgumentException con detalle.
     */
    public function validarApertura(int $empresaId, string $fechaJornada): string
    {
        if ($empresaId <= 0) {
            throw new InvalidArgumentException(
                'Debe seleccionar una empresa antes de abrir la jornada.'
            );
        }

        if (trim($fechaJornada) === '') {
            throw new InvalidArgumentException(
                'Debe indicar la fecha de jornada (día de turno).'
            );
        }

        $fecha = $this->normalizarFecha($fechaJornada, 'fecha de jornada');
        $fechaFmt = $this->formatearFechaHumana($fecha);

        $abierta = $this->jornadaAbierta($empresaId);
        if ($abierta !== null) {
            throw new InvalidArgumentException(
                'No puede abrir la jornada del '.$fechaFmt.': '.$this->describirJornadaAbierta($abierta)
                .' Cierre esa jornada antes de abrir otra (aunque sea de otra fecha).'
            );
        }

        if (config('gastronomia.requiere_habilitacion_turno', true)) {
            $turnosHabilitados = TurnoOperativoGastronomia::query()
                ->with('jornada')
                ->where('empresa_id', $empresaId)
                ->where('estado', TurnoOperativoGastronomia::ESTADO_HABILITADO)
                ->get();

            if ($turnosHabilitados->isNotEmpty()) {
                $detalle = $turnosHabilitados
                    ->map(function (TurnoOperativoGastronomia $t) {
                        $fj = $t->jornada?->fecha_jornada?->format('d/m/Y') ?? '?';

                        return $t->identificador_pc.' (jornada '.$fj.')';
                    })
                    ->implode(', ');

                throw new InvalidArgumentException(
                    'No puede abrir una jornada nueva: hay turno(s) habilitado(s) sin cerrar en: '.$detalle
                    .'. Cierre cada turno (especialmente turno noche) antes de abrir la jornada del día siguiente.'
                );
            }
        }

        return $fecha;
    }

    public function abrir(int $empresaId, string $fechaJornada, ?string $observacion = null): JornadaGastronomia
    {
        $fecha = $this->validarApertura($empresaId, $fechaJornada);

        if (Auth::id() === null) {
            throw new InvalidArgumentException(
                'No hay usuario autenticado. Vuelva a iniciar sesión e intente abrir la jornada.'
            );
        }

        try {
            return DB::transaction(function () use ($empresaId, $fecha, $observacion) {
                $existente = JornadaGastronomia::query()
                    ->with(['usuarioApertura', 'empresa'])
                    ->where('empresa_id', $empresaId)
                    ->where('estado', JornadaGastronomia::ESTADO_ABIERTA)
                    ->lockForUpdate()
                    ->first();

                if ($existente !== null) {
                    throw new InvalidArgumentException(
                        'No puede abrir la jornada del '.$this->formatearFechaHumana($fecha).': '
                        .$this->describirJornadaAbierta($existente)
                        .' (otro usuario pudo haberla abierto en este momento).'
                    );
                }

                return $this->jornadaRepository->create([
                    'empresa_id' => $empresaId,
                    'fecha_jornada' => $fecha,
                    'estado' => JornadaGastronomia::ESTADO_ABIERTA,
                    'usuario_apertura_id' => Auth::id(),
                    'apertura_en' => now(),
                    'observacion_apertura' => $this->limpiarObservacion($observacion),
                ]);
            });
        } catch (InvalidArgumentException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new InvalidArgumentException(self::mensajeDesdeExcepcion($e), 0, $e);
        }
    }

    public function cerrar(int $empresaId, ?string $observacion = null): JornadaGastronomia
    {
        $jornada = $this->exigirJornadaAbierta($empresaId);

        $errores = $this->erroresAntesDeCerrar($empresaId);
        if ($errores !== []) {
            throw new InvalidArgumentException(implode(' ', $errores));
        }

        $this->jornadaRepository->update([
            'estado' => JornadaGastronomia::ESTADO_CERRADA,
            'usuario_cierre_id' => Auth::id(),
            'cierre_en' => now(),
            'observacion_cierre' => $this->limpiarObservacion($observacion),
        ], (int) $jornada->id);

        return $this->jornadaRepository->findOrFail((int) $jornada->id);
    }

    /**
     * @return list<string>
     */
    public function erroresAntesDeCerrar(int $empresaId): array
    {
        $errores = [];

        $cuentasPendientes = CuentaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('estado', [
                CuentaGastronomia::ESTADO_ABIERTA,
                CuentaGastronomia::ESTADO_CERRADA,
            ])
            ->count();

        if ($cuentasPendientes > 0) {
            $errores[] = 'Hay '.$cuentasPendientes.' cuenta(s) de mesa sin facturar (abiertas o cerradas).';
        }

        $turnosSinCerrar = TurnoOperativoGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->where('estado', TurnoOperativoGastronomia::ESTADO_HABILITADO)
            ->count();

        if ($turnosSinCerrar > 0 && config('gastronomia.requiere_habilitacion_turno', true)) {
            $errores[] = 'Hay '.$turnosSinCerrar.' turno(s) habilitado(s) sin cerrar en alguna terminal.';
        }

        return $errores;
    }

    public function exigirJornadaSiConfigurada(int $empresaId): void
    {
        if (! config('gastronomia.jornada_obligatoria', true)) {
            return;
        }

        $this->exigirJornadaAbierta($empresaId);
    }

    public static function mensajeDesdeExcepcion(Throwable $e): string
    {
        if ($e instanceof InvalidArgumentException) {
            return $e->getMessage();
        }

        $prev = $e->getPrevious();
        if ($prev instanceof QueryException) {
            $sqlMsg = $prev->getMessage();

            if (str_contains($sqlMsg, 'foreign key constraint') || str_contains($sqlMsg, 'FOREIGN KEY')) {
                if (str_contains($sqlMsg, 'empresa_id')) {
                    return 'La empresa seleccionada no existe en el sistema o no puede usarse para jornadas.';
                }
                if (str_contains($sqlMsg, 'usuario_apertura_id')) {
                    return 'No se pudo asociar su usuario a la apertura. Cierre sesión, vuelva a entrar e intente de nuevo.';
                }

                return 'No se pudo grabar la jornada por un dato referenciado inválido (restricción de base de datos).';
            }

            if (str_contains($sqlMsg, 'Duplicate entry') || str_contains($sqlMsg, 'duplicate key')) {
                return 'Ya existe un registro de jornada con esos datos. Consulte el historial o cierre la jornada anterior.';
            }
        }

        $detalle = trim($e->getMessage());
        if ($detalle === '') {
            return 'Error inesperado al abrir la jornada. Revise el log del servidor o contacte soporte.';
        }

        return 'Error al abrir la jornada: '.$detalle;
    }

    private function describirJornadaAbierta(JornadaGastronomia $jornada): string
    {
        $jornada->loadMissing(['usuarioApertura', 'empresa']);

        $fecha = $jornada->fecha_jornada
            ? $this->formatearFechaHumana($jornada->fecha_jornada->format('Y-m-d'))
            : '(sin fecha)';
        $empresa = $jornada->empresa?->nombre ?? ('empresa #'.$jornada->empresa_id);
        $usuario = $jornada->usuarioApertura?->nombre ?? 'usuario desconocido';
        $cuando = $jornada->apertura_en?->format('d/m/Y H:i') ?? 'fecha no registrada';

        return 'ya hay una jornada abierta del '.$fecha.' ('.$empresa.', id '.$jornada->id
            .', abierta por '.$usuario.' el '.$cuando.').';
    }

    private function formatearFechaHumana(string $fechaYmd): string
    {
        try {
            return Carbon::parse($fechaYmd)->format('d/m/Y');
        } catch (Throwable) {
            return $fechaYmd;
        }
    }

    private function normalizarFecha(string $fecha, string $etiqueta): string
    {
        $fecha = trim($fecha);
        if ($fecha === '') {
            throw new InvalidArgumentException('La '.$etiqueta.' está vacía.');
        }

        try {
            $parsed = Carbon::parse($fecha);
        } catch (Throwable) {
            throw new InvalidArgumentException(
                'La '.$etiqueta.' "'.$fecha.'" no es válida. Use el selector de fecha (formato AAAA-MM-DD).'
            );
        }

        return $parsed->format('Y-m-d');
    }

    private function limpiarObservacion(?string $observacion): ?string
    {
        $txt = trim((string) $observacion);

        return $txt === '' ? null : mb_substr($txt, 0, 2000);
    }
}
