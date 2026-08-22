<?php

namespace App\Exceptions\Contable;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PeriodoContableCerradoException extends Exception
{
    public function __construct(
        string $message,
        private readonly ?string $fechaOperacion = null,
        private readonly ?string $fechaCierre = null,
        private readonly ?string $alcance = null,
    ) {
        parent::__construct($message);
    }

    public function fechaOperacion(): ?string
    {
        return $this->fechaOperacion;
    }

    public function fechaCierre(): ?string
    {
        return $this->fechaCierre;
    }

    public function alcance(): ?string
    {
        return $this->alcance;
    }

    /**
     * Bloqueo por regla de negocio: alcanza una línea en el log, sin stack trace.
     */
    public function report(): bool
    {
        Log::warning('periodo_contable_cerrado.bloqueo', [
            'mensaje' => $this->getMessage(),
            'fecha_operacion' => $this->fechaOperacion,
            'fecha_cierre' => $this->fechaCierre,
            'alcance' => $this->alcance,
            'url' => request()?->fullUrl(),
            'usuario_id' => auth()->id(),
        ]);

        return false;
    }

    /**
     * Sin esto el operador ve un 500 sin motivo. Se responde con las claves que usan
     * los distintos JS del ERP (errores / error / message / mensaje) y `ok=false`.
     */
    public function render(Request $request): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => false,
                'periodo_cerrado' => true,
                'errores' => $this->getMessage(),
                'error' => $this->getMessage(),
                'message' => $this->getMessage(),
                'mensaje' => $this->getMessage(),
                'fecha_operacion' => $this->fechaOperacion,
                'fecha_cierre' => $this->fechaCierre,
                'alcance' => $this->alcance,
            ]);
        }

        return redirect()->back()
            ->withInput()
            ->with('error', $this->getMessage());
    }
}
