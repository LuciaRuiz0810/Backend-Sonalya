<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ArtistaPerfil;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password as ReglaPassword;

/**
 * Gestión de autenticación: registro, login, logout y recuperación de contraseña.
 * Incluye protección contra fuerza bruta (bloqueo tras 5 intentos fallidos).
 *
 * @author  Lucia Ruiz Salvador
 * @version 1.0.0
 * @date    2025-09-01
 */
class AuthController extends Controller
{
    public function registro(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'nombre'                => ['required', 'string', 'max:100'],
            'email'                 => ['required', 'email', 'unique:users,email'],
            'nombre_usuario'        => ['required', 'string', 'max:50', 'unique:users,nombre_usuario', 'regex:/^@\S+$/'],
            'password'              => ['required', 'confirmed', ReglaPassword::min(8)->mixedCase()->numbers()],
            'tipo'                  => ['sometimes', 'in:oyente,artista'],
            'nombre_artista'        => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $usuario = User::create($datos);

        if (($datos['tipo'] ?? 'oyente') === 'artista') {
            ArtistaPerfil::create(['user_id' => $usuario->id]);
        }

        $token   = $usuario->createToken('token_auth')->plainTextToken;

        return response()->json([
            'success' => true,
            'data'    => ['user' => $usuario, 'token' => $token],
            'message' => 'Usuario registrado correctamente.',
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $usuario = User::where('email', $request->email)->first();

        if (!$usuario) {
            return response()->json([
                'success' => false,
                'message' => 'Credenciales incorrectas.',
                'errors'  => ['email' => ['No existe una cuenta con ese email.']],
            ], 401);
        }

        if ($usuario->isLocked()) {
            $segundos = now()->diffInSeconds($usuario->bloqueado_hasta);
            return response()->json([
                'success' => false,
                'message' => "Cuenta bloqueada temporalmente. Intenta de nuevo en {$segundos} segundos.",
            ], 429);
        }

        if (!Hash::check($request->password, $usuario->password)) {
            $intentos = $usuario->intentos_login + 1;
            $actualizar = ['intentos_login' => $intentos];

            if ($intentos >= 5) {
                $actualizar['bloqueado_hasta'] = now()->addMinutes(15);
                $actualizar['intentos_login']  = 0;
            }

            $usuario->update($actualizar);

            return response()->json([
                'success' => false,
                'message' => 'Credenciales incorrectas.',
                'errors'  => ['password' => ['Contraseña incorrecta.']],
            ], 401);
        }

        $usuario->update(['intentos_login' => 0, 'bloqueado_hasta' => null]);
        $token = $usuario->createToken('token_auth')->plainTextToken;

        return response()->json([
            'success' => true,
            'data'    => ['user' => $usuario, 'token' => $token],
            'message' => 'Sesión iniciada correctamente.',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada correctamente.',
        ]);
    }

    public function recuperarPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $estado = Password::sendResetLink($request->only('email'));

        if ($estado !== Password::RESET_LINK_SENT) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo enviar el email de recuperación.',
                'errors'  => ['email' => [__($estado)]],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Email de recuperación enviado correctamente.',
        ]);
    }

    public function resetearPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token'    => ['required', 'string'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'confirmed', ReglaPassword::min(8)->mixedCase()->numbers()],
        ]);

        $estado = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $usuario, string $contrasena) {
                $usuario->forceFill(['password' => $contrasena])->save();
                $usuario->tokens()->delete();
            }
        );

        if ($estado !== Password::PASSWORD_RESET) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo restablecer la contraseña.',
                'errors'  => ['token' => [__($estado)]],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Contraseña restablecida correctamente.',
        ]);
    }

    public function perfil(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $request->user(),
        ]);
    }

    public function actualizarPerfil(Request $request): JsonResponse
    {
        $usuario = $request->user();

        $datos = $request->validate([
            'nombre'         => ['sometimes', 'string', 'max:100'],
            'nombre_usuario' => ['sometimes', 'string', 'max:50', 'unique:users,nombre_usuario,' . $usuario->id, 'regex:/^@\S+$/'],
            'email'          => ['sometimes', 'email', 'unique:users,email,' . $usuario->id],
            'biografia'      => ['sometimes', 'nullable', 'string', 'max:500'],
            'avatar'         => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        if ($request->hasFile('avatar')) {
            if ($usuario->avatar) {
                Storage::disk('public')->delete($usuario->avatar);
            }
            $datos['avatar'] = $request->file('avatar')->store('avatares', 'public');
        }

        $usuario->update($datos);

        return response()->json([
            'success' => true,
            'data'    => $usuario->fresh(),
            'message' => 'Perfil actualizado correctamente.',
        ]);
    }

    public function cambiarPassword(Request $request): JsonResponse
    {
        $request->validate([
            'password_actual' => ['required', 'string'],
            'password'        => ['required', 'confirmed', ReglaPassword::min(8)->mixedCase()->numbers()],
        ]);

        $usuario = $request->user();

        if (!Hash::check($request->password_actual, $usuario->password)) {
            return response()->json([
                'success' => false,
                'message' => 'La contraseña actual no es correcta.',
                'errors'  => ['password_actual' => ['La contraseña actual no es correcta.']],
            ], 422);
        }

        $usuario->update(['password' => $request->password]);
        $usuario->tokens()->delete();

        $token = $usuario->createToken('token_auth')->plainTextToken;

        return response()->json([
            'success' => true,
            'data'    => ['token' => $token],
            'message' => 'Contraseña cambiada correctamente.',
        ]);
    }
}
