<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Erreur métier dont le message peut être affiché tel quel à l'utilisateur
 * (stock insuffisant, caisse fermée, limite de crédit...).
 */
class BusinessException extends RuntimeException
{
    public function render($request)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()], 422);
        }

        return back()->withInput()->with('error', $this->getMessage());
    }
}
