<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\RedefinirSenhaEquipe;
use App\Models\Activity;
use App\Models\User;
use App\Services\Seguranca\DoisFatores;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
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

    /**
     * Recuperação alternativa para quando o usuário não se lembra do e-mail:
     * Pode informar o celular cadastrado e, se tiver 2FA ativo, o código TOTP de 6 dígitos
     * ou um código de recuperação de backup (XXXXX-XXXXX).
     */
    public function recoverByPhone(Request $request, DoisFatores $dois): RedirectResponse
    {
        $dados = $request->validate([
            'identificador' => ['required', 'string', 'max:190'],
            'codigo_2fa' => ['nullable', 'string', 'max:40'],
        ], [
            'identificador.required' => 'Informe o número de celular ou e-mail cadastrado na sua conta.',
        ]);

        $termo = trim((string) $dados['identificador']);
        $codigo2fa = trim((string) ($dados['codigo_2fa'] ?? ''));

        // Se o usuário digitou um e-mail válido no campo
        if (filter_var($termo, FILTER_VALIDATE_EMAIL)) {
            $usuario = User::where('email', mb_strtolower($termo))->where('status', 'ativo')->first();
        } else {
            // Busca por celular/telefone normalizado (apenas números)
            $apenasDigitos = preg_replace('/\D/', '', $termo);

            if (strlen($apenasDigitos) < 8) {
                return back()->withInput()->with('aba_ativa', 'celular')->withErrors([
                    'identificador' => 'Informe um número de telefone válido com DDD (mínimo de 8 dígitos).',
                ]);
            }

            $usuarios = User::whereNotNull('telefone')->where('status', 'ativo')->get();
            $usuario = $usuarios->first(function (User $u) use ($apenasDigitos): bool {
                $limpo = preg_replace('/\D/', '', (string) $u->telefone);
                if ($limpo === '') {
                    return false;
                }

                return $limpo === $apenasDigitos
                    || (strlen($limpo) >= 8 && str_ends_with($apenasDigitos, $limpo))
                    || (strlen($apenasDigitos) >= 8 && str_ends_with($limpo, $apenasDigitos));
            });
        }

        if ($usuario === null) {
            return back()->withInput()->with('aba_ativa', 'celular')->with(
                'error',
                'Nenhuma conta ativa foi localizada com esses dados. Verifique as informações ou contate o administrador.'
            );
        }

        // Se a conta tem Verificação em Duas Etapas (2FA) ativada:
        if ($usuario->doisFatoresAtivo()) {
            if ($codigo2fa === '') {
                return back()->withInput()->with('aba_ativa', 'celular')->withErrors([
                    'codigo_2fa' => 'Sua conta possui verificação em duas etapas ativada. Informe o código de 6 dígitos do app autenticador ou um código de recuperação para validar a posse da conta.',
                ]);
            }

            $recuperacao = Str::contains($codigo2fa, '-');
            $segredo = (string) $usuario->two_factor_secret;

            $valido = $recuperacao
                ? $dois->queimarCodigoDeRecuperacao($usuario, $codigo2fa)
                : ($segredo !== '' && $dois->codigoValido($segredo, $codigo2fa));

            if (! $valido) {
                Activity::log(
                    'dois_fatores_falhou',
                    'Tentativa de recuperação por segundo fator recusada para '.$usuario->email.'.',
                    $usuario,
                    ['ip' => $request->ip(), 'recuperacao' => $recuperacao]
                );

                return back()->withInput()->with('aba_ativa', 'celular')->withErrors([
                    'codigo_2fa' => 'Código de segurança do 2FA inválido ou já utilizado.',
                ]);
            }

            // Validado com sucesso via 2FA!
            Activity::log(
                $recuperacao ? 'recuperacao_senha_codigo_reserva' : 'recuperacao_senha_2fa',
                $recuperacao
                    ? 'Identidade confirmada com código reserva do 2FA para redefinição de senha.'
                    : 'Identidade confirmada com código do app 2FA para redefinição de senha.',
                $usuario,
                ['ip' => $request->ip()]
            );

            $link = URL::temporarySignedRoute(
                'password.reset',
                now()->addMinutes(self::VALIDADE_MINUTOS),
                ['user' => $usuario->id]
            );

            return redirect($link)->with(
                'info_email',
                "Identidade confirmada com sucesso pelo seu 2FA! O e-mail de login da sua conta é: {$usuario->email}."
            );
        }

        // Se a conta NÃO tem 2FA ativado:
        // Enviamos o link seguro por e-mail e exibimos uma dica com o e-mail parcialmente mascarado
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

        $emailMascarado = $this->mascararEmail($usuario->email);

        return back()->with('aba_ativa', 'celular')->with(
            'status',
            "Conta localizada pelo celular cadastrado! Como esta conta não tem 2FA ativo para liberação instantânea na tela, enviamos o link de redefinição para o e-mail: {$emailMascarado}. Acesse sua caixa postal."
        );
    }

    private function mascararEmail(string $email): string
    {
        $partes = explode('@', $email, 2);
        if (count($partes) !== 2) {
            return '***';
        }

        $nome = $partes[0];
        $dominio = $partes[1];

        $tamNome = mb_strlen($nome);
        if ($tamNome <= 2) {
            $nomeMasc = mb_substr($nome, 0, 1).'*';
        } else {
            $nomeMasc = mb_substr($nome, 0, 1).str_repeat('*', max(1, $tamNome - 2)).mb_substr($nome, -1);
        }

        $partesDom = explode('.', $dominio, 2);
        $domNome = $partesDom[0];
        $tld = $partesDom[1] ?? '';

        $tamDom = mb_strlen($domNome);
        if ($tamDom <= 2) {
            $domMasc = mb_substr($domNome, 0, 1).'*';
        } else {
            $domMasc = mb_substr($domNome, 0, 1).str_repeat('*', max(1, $tamDom - 2)).mb_substr($domNome, -1);
        }

        return $nomeMasc.'@'.$domMasc.($tld !== '' ? '.'.$tld : '');
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
