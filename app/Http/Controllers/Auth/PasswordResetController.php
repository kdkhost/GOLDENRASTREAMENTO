<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\RedefinirSenhaEquipe;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    private const VALIDADE_MINUTOS = 60;

    public function showLinkRequestForm(): View|RedirectResponse
    {
        if (Auth::guard('web')->check()) {
            return redirect()->route('admin.dashboard');
        }

        return view('auth.esqueci-senha');
    }

    public function sendResetLinkEmail(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'email' => ['required', 'email', 'max:190'],
        ], [
            'email.required' => 'Informe o e-mail corporativo cadastrado na sua conta.',
            'email.email' => 'Informe um formato de e-mail válido.',
        ]);

        $email = mb_strtolower(trim($dados['email']));
        $usuario = User::where('email', $email)->first();

        if ($usuario !== null && $usuario->status === 'ativo') {
            $link = URL::temporarySignedRoute(
                'password.reset',
                now()->addMinutes(self::VALIDADE_MINUTOS),
                ['user' => $usuario->id]
            );

            try {
                Mail::to($usuario->email)->queue(
                    new RedefinirSenhaEquipe($usuario, $link, self::VALIDADE_MINUTOS)
                );
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // Resposta neutra para mitigar enumeração de contas
        return back()->withInput($request->only('email'))->with(
            'status',
            'Se este e-mail estiver cadastrado na equipe, enviamos as instruções para redefinição. O link expira em '.self::VALIDADE_MINUTOS.' minutos.'
        );
    }

    public function showResetForm(Request $request, User $user): View|RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            return redirect()->route('password.request')->with(
                'error',
                'Este link de recuperação expirou ou é inválido. Por favor, solicite um novo link.'
            );
        }

        return view('auth.redefinir-senha', [
            'user' => $user,
        ]);
    }

    public function reset(Request $request, User $user): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            return redirect()->route('password.request')->with(
                'error',
                'Este link de recuperação expirou ou é inválido. Por favor, solicite um novo link.'
            );
        }

        $dados = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'password.required' => 'Informe uma nova senha.',
            'password.min' => 'A senha deve conter no mínimo :min caracteres.',
            'password.confirmed' => 'A confirmação de senha não confere.',
        ]);

        $user->password = $dados['password'];
        $user->save();

        Activity::log(
            'senha_redefinida',
            'Senha de acesso ao painel redefinida pelo link de recuperação.',
            $user,
            por: $user->name
        );

        return redirect()->route('login')->with(
            'success',
            'Sua nova senha foi cadastrada com sucesso. Entre com ela para acessar o painel.'
        );
    }
}
