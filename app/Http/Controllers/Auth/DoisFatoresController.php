<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Services\Seguranca\DoisFatores;
use App\Services\Seguranca\VigiaDeAcesso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Acender, renovar e apagar a segunda chave da própria conta.
 *
 * O desenho segue a regra da casa: nada de arquivo no disco e nada de segredo voltando para a tela
 * depois de guardado. O QR é desenhado pela própria página em SVG, o segredo provisório mora na
 * sessão até a pessoa provar que o aplicativo está lendo o mesmo número, e os oito códigos de
 * recuperação são mostrados **uma** vez — quem perder, renova.
 *
 * A verificação do código de confirmação não é formalidade: sem ela, qualquer um que abrisse a tela
 * com o telefone do colega ativaria o dois fatores com o segredo *dele*, e a conta passaria a pedir
 * um código que só o atacante genera. É por isso que o segredo só toca o banco depois de um código
 * real batendo.
 */
class DoisFatoresController extends Controller
{
    /** Segredo em espera na sessão: ainda não é a chave de ninguém até o código bater. */
    private const PENDENTE = 'dois_fatores_pendente_na_configuracao';

    /** Os códigos de recuperação, mostrados uma única vez, direto da sessão. */
    private const CODIGOS = 'dois_fatores_codigos_mostrar';

    public function configurar(Request $request, DoisFatores $dois): RedirectResponse|View
    {
        $usuario = $request->user();

        if ($usuario->doisFatoresAtivo()) {
            return redirect()->route('admin.seguranca.index')
                ->with('info', 'A segunda chave desta conta já está acesa. Para mexer nela, use os botões da tela de Segurança.');
        }

        $segredo = $this->segredoPendente($request) ?? $dois->novoSegredo();
        $request->session()->put(self::PENDENTE, [
            'segredo' => $segredo,
            'desde' => now()->timestamp,
        ]);

        return view('admin.dois-fatores.configurar', [
            'email' => $usuario->email,
            'segredo' => $segredo,
            'endereco' => $dois->urlDeConfiguracao($usuario->email, $segredo),
            'qr' => $dois->qrEmSvg($usuario->email, $segredo),
            'validade' => max(1, (int) config('seguranca.desafio_minutos', 10)),
        ]);
    }

    public function confirmar(Request $request, DoisFatores $dois, VigiaDeAcesso $vigia): RedirectResponse
    {
        $usuario = $request->user();
        $pedido = $request->validate([
            'codigo' => ['required', 'string', 'min:6', 'max:10'],
        ]);

        $pendente = $this->segredoPendente($request);

        if ($pendente === null) {
            return redirect()->route('dois-fatores.configurar')
                ->with('warning', 'Esta chave expirou. Abra uma nova: o aplicativo precisa ler o código novo.');
        }

        if ($espera = $vigia->codigoBloqueadoPor($usuario)) {
            return back()->withErrors(['codigo' => 'Códigos demais tentados. Espere '.(int) ceil($espera / 60).' minuto(s).']);
        }

        if (! $dois->codigoValido($pendente['segredo'], $pedido['codigo'])) {
            $vigia->codigoFalhou($usuario);

            Activity::log('dois_fatores_configuracao_falhou', 'Código de confirmação do dois fatores não bateu.', $usuario);

            return back()->withErrors(['codigo' => 'O código não bateu. Confira o relógio do celular e digite os seis dígitos de agora.']);
        }

        $codigos = $dois->novosCodigosDeRecuperacao();

        $usuario->forceFill([
            'two_factor_secret' => $pendente['segredo'],
            'two_factor_recovery_codes' => $codigos,
            'two_factor_confirmed_at' => now(),
        ])->save();

        $vigia->codigoAcertou($usuario);
        $request->session()->forget(self::PENDENTE);
        $request->session()->put(self::CODIGOS, $codigos);

        Activity::log('dois_fatores_ativado', 'Verificação em duas etapas acesa nesta conta.', $usuario);

        return redirect()->route('dois-fatores.codigos');
    }

    /** A folha dos oito códigos: vista agora, perdida depois — renovar custa um clique. */
    public function codigos(Request $request): RedirectResponse|View
    {
        $codigos = $request->session()->get(self::CODIGOS);

        if (! is_array($codigos) || $codigos === []) {
            return redirect()->route('dois-fatores.configurar');
        }

        return view('admin.dois-fatores.codigos', ['codigos' => $codigos]);
    }

    /** "Guardei": a folha sai da sessão e não volta mais. */
    public function guardarCodigos(Request $request): RedirectResponse
    {
        $request->session()->forget(self::CODIGOS);

        return redirect()->route('admin.seguranca.index')->with('success', 'Códigos guardados.');
    }

    /** Nova fornada de códigos — a antiga perde o valor inteiro, não só o que foi usado. */
    public function renovarCodigos(Request $request, DoisFatores $dois): RedirectResponse
    {
        $usuario = $request->user();

        if (! $usuario->doisFatoresAtivo()) {
            return redirect()->route('dois-fatores.configurar');
        }

        $codigos = $dois->novosCodigosDeRecuperacao();
        $usuario->forceFill(['two_factor_recovery_codes' => $codigos])->save();

        $request->session()->forget(self::CODIGOS);
        $request->session()->put(self::CODIGOS, $codigos);

        Activity::log('dois_fatores_codigos_renovados', 'Nova fornada de códigos de recuperação: os oito anteriores perderam o valor.', $usuario);

        return redirect()->route('dois-fatores.codigos');
    }

    /**
     * Apaga a segunda chave desta conta.
     *
     * Só abre com o código de agora ou um de recuperação — quem tem o telefone na mão é quem desliga,
     * não quem deixou a aba aberta. E quando a política da casa exige o dois fatores para aquele
     * perfil, desligar não é uma escolha da pessoa: é escolha de quem administra, na tela de
     * Configurações.
     */
    public function desligar(Request $request, DoisFatores $dois, VigiaDeAcesso $vigia): RedirectResponse
    {
        $usuario = $request->user();

        if (DoisFatores::exige($usuario)) {
            return redirect()->route('admin.seguranca.index')->with('error',
                'A política da casa exige a verificação em duas etapas para o seu perfil. Para desligá-la, um administrador muda Configurações → Segurança do acesso.');
        }

        $pedido = $request->validate([
            'codigo' => ['required', 'string', 'max:32'],
        ]);

        $codigo = trim($pedido['codigo']);
        $ok = str_contains($codigo, '-')
            ? $dois->queimarCodigoDeRecuperacao($usuario, $codigo)
            : ((string) $usuario->two_factor_secret !== '' && $dois->codigoValido((string) $usuario->two_factor_secret, $codigo));

        if (! $ok) {
            $vigia->codigoFalhou($usuario);

            return back()->withErrors(['codigo' => 'Código inválido ou já usado.']);
        }

        $dois->desligar($usuario);
        $vigia->codigoAcertou($usuario);
        Activity::log('dois_fatores_desligado', 'Verificação em duas etapas apagada nesta conta.', $usuario);

        return redirect()->route('admin.seguranca.index')->with('info', 'A segunda chave foi desligada. Sua conta volta a abrir só com a senha.');
    }

    private function segredoPendente(Request $request): ?array
    {
        $pendente = $request->session()->get(self::PENDENTE);

        if (! is_array($pendente) || ! isset($pendente['segredo'], $pendente['desde'])) {
            return null;
        }

        $validade = max(1, (int) config('seguranca.desafio_minutos', 10)) * 60;

        if ((int) $pendente['desde'] + $validade < now()->timestamp) {
            $request->session()->forget(self::PENDENTE);

            return null;
        }

        return $pendente;
    }
}
