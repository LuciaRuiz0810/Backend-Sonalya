<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Redirigir el enlace de recuperación al frontend en lugar de buscar
        // una ruta Laravel (password.reset) que no existe en una API pura.
        ResetPassword::createUrlUsing(function ($usuario, string $token) {
            $frontend = config('app.frontend_url', 'http://localhost:5173');
            return $frontend . '/resetear-password?token=' . $token . '&email=' . urlencode($usuario->email);
        });
    }
}
