<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Assistente;
use App\Models\Setting;
use App\Services\Assistente\Provedores\Fabrica;
use App\Support\Envio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * A ficha do assistente — quem ele é, como fala e onde atende.
 *
 * É uma tela de edição, nunca de cadastro: existe um atendente só. A linha nasce dos padrões de
 * `config('assistente.persona')` na primeira vez que alguém a salva ou abre a tela, e o POST
 * valida antes de criar qualquer coisa — um envio rejeitado não pode deixar ficha órfã no banco,
 * porque ela passaria a valer como a ficha da casa.
 *
 * O avatar entra pelo dropzone da casa (`partials.upload` + `config/envios.php`), e a voz é a
 * lista que a própria fábrica sabe usar — a tela não oferece o que o código não consegue falar.
 */
class AssistenteController extends Controller
{
    public function editar(): View
    {
        return view('admin.assistente.persona', [
            'assistente' => Assistente::garanta(),
            'tons' => Assistente::tons(),
            'vozes' => Fabrica::opcoesDeVoz(),
            'icones' => (array) config('assistente.icones_bolha'),
            'faltando' => Fabrica::faltando(),
            'ligado' => Fabrica::ligado(),
            'contato_whatsapp' => (string) Setting::get('contato_whatsapp', config('golden.whatsapp')),
            'contato_telefone' => (string) Setting::get('contato_telefone', implode(' · ', (array) config('golden.contato.telefones'))),
            'contato_email' => (string) Setting::get('contato_email', config('golden.email')),
            'assistente_destino_humano' => (string) Setting::get('assistente_destino_humano', 'whatsapp'),
            'assistente_msg_humano' => (string) Setting::get('assistente_msg_humano', 'Olá! Estava conversando com a :assistente no site e gostaria de falar com um atendente humano.'),
            'assistente_horario_humano' => (string) Setting::get('assistente_horario_humano', 'Segunda a Sexta das 08h às 18h'),
        ]);
    }

    public function atualizar(Request $request): RedirectResponse
    {
        $data = $this->validar($request);
        $ficha = Assistente::garanta();

        foreach (['ativo', 'no_site', 'no_funil', 'no_chamado'] as $canal) {
            $data[$canal] = $request->boolean($canal);
        }

        // Salva os dados de contato do atendimento humanizado nas configurações gerais do sistema
        if ($request->has('contato_whatsapp')) {
            $whatsLimpo = preg_replace('/\D/', '', (string) $request->input('contato_whatsapp'));
            Setting::set('contato_whatsapp', $whatsLimpo);
        }

        if ($request->has('contato_telefone')) {
            Setting::set('contato_telefone', trim((string) $request->input('contato_telefone')));
        }

        if ($request->has('contato_email')) {
            Setting::set('contato_email', trim((string) $request->input('contato_email')));
        }

        if ($request->has('assistente_destino_humano')) {
            Setting::set('assistente_destino_humano', (string) $request->input('assistente_destino_humano'));
        }

        if ($request->has('assistente_msg_humano')) {
            Setting::set('assistente_msg_humano', trim((string) $request->input('assistente_msg_humano')));
        }

        if ($request->has('assistente_horario_humano')) {
            Setting::set('assistente_horario_humano', trim((string) $request->input('assistente_horario_humano')));
        }

        // Remove chaves de Setting do array que vai para a tabela assistente
        $chavesSettings = ['contato_whatsapp', 'contato_telefone', 'contato_email', 'assistente_destino_humano', 'assistente_msg_humano', 'assistente_horario_humano'];
        foreach ($chavesSettings as $sKey) {
            unset($data[$sKey]);
        }

        $anterior = $ficha->avatar;
        $ficha->update($data);

        if (array_key_exists('avatar', $data) && $anterior && $anterior !== $data['avatar']) {
            Envio::remover($anterior, Assistente::CAMPO_ENVIO);
        }

        Activity::log('assistente_atualizado', 'A ficha do assistente e os dados de atendimento humanizado foram atualizados.', $ficha);

        return redirect()->route('admin.assistente.persona')->with('success', 'Ficha do assistente e dados do atendimento humanizado salvos com sucesso.');
    }

    private function validar(Request $request): array
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'cargo' => ['nullable', 'string', 'max:160'],
            'saudacao' => ['nullable', 'string', 'max:1000'],
            'pergunta_nome' => ['nullable', 'string', 'max:500'],
            'confirmacao_nome' => ['nullable', 'string', 'max:500'],
            'pergunta_email' => ['nullable', 'string', 'max:500'],
            'confirmacao_email' => ['nullable', 'string', 'max:500'],
            'confirmacao_cadastro' => ['nullable', 'string', 'max:500'],
            'pedido_avaliacao' => ['nullable', 'string', 'max:500'],
            'agradecimento_avaliacao' => ['nullable', 'string', 'max:500'],
            'despedida' => ['nullable', 'string', 'max:500'],
            'tom' => ['required', 'string', 'in:'.implode(',', array_keys(Assistente::tons()))],
            'regras' => ['nullable', 'string', 'max:8000'],
            'avatar' => ['nullable', 'string', 'max:190'],
            'avatar_alt' => ['nullable', 'string', 'max:120'],
            'dica' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', 'string', 'max:60'],
            'placeholder' => ['nullable', 'string', 'max:120'],
            'humano' => ['nullable', 'string', 'max:120'],
            'icone' => ['nullable', 'string', 'max:60'],
            'voz' => ['nullable', 'string', empty(Fabrica::opcoesDeVoz()) ? 'nullable' : 'in:'.implode(',', array_keys(Fabrica::opcoesDeVoz()))],
            'contato_whatsapp' => ['nullable', 'string', 'max:32'],
            'contato_telefone' => ['nullable', 'string', 'max:60'],
            'contato_email' => ['nullable', 'email', 'max:160'],
            'assistente_destino_humano' => ['nullable', 'string', 'in:whatsapp,contato,portal'],
            'assistente_msg_humano' => ['nullable', 'string', 'max:500'],
            'assistente_horario_humano' => ['nullable', 'string', 'max:120'],
        ], [
            'tom.in' => 'O tom escolhido não está na lista da ficha.',
            'voz.in' => 'A voz escolhida não está na lista dos provedores ligados.',
            'assistente_destino_humano.in' => 'A ação de destino do atendimento humano selecionada é inválida.',
        ], [
            'nome' => 'nome',
            'cargo' => 'cargo',
            'saudacao' => 'saudação',
            'pergunta_nome' => 'pergunta pelo nome',
            'confirmacao_nome' => 'confirmação do nome',
            'pergunta_email' => 'pergunta pelo e-mail',
            'confirmacao_email' => 'confirmação do e-mail',
            'confirmacao_cadastro' => 'confirmação do cadastro',
            'pedido_avaliacao' => 'pedido de avaliação',
            'agradecimento_avaliacao' => 'agradecimento pela avaliação',
            'despedida' => 'despedida',
            'tom' => 'tom de voz',
            'regras' => 'regras do atendimento',
            'avatar' => 'avatar',
            'avatar_alt' => 'descrição do avatar',
            'dica' => 'dica da bolha',
            'status' => 'selo de status',
            'placeholder' => 'texto do campo de mensagem',
            'humano' => 'link para falar com uma pessoa',
            'icone' => 'ícone da bolha',
            'voz' => 'voz das respostas',
            'contato_whatsapp' => 'WhatsApp do atendimento humanizado',
            'contato_telefone' => 'telefone de suporte',
            'contato_email' => 'e-mail de suporte',
            'assistente_destino_humano' => 'ação ao clicar no atendimento humano',
            'assistente_msg_humano' => 'mensagem do WhatsApp',
            'assistente_horario_humano' => 'horário de atendimento',
        ]);

        // Chave ausente no POST é "não mexi no rosto", não "apague o rosto": o dropzone só emite
        // o campo quando há arquivo na fila.
        if ($request->has('avatar')) {
            $data['avatar'] = $this->avatarValido($data['avatar'] ?? null);
        } else {
            unset($data['avatar']);
        }

        return $data;
    }

    /** O caminho que o dropzone devolve, ou o texto que alguém colou por engano. */
    private function avatarValido(?string $avatar): ?string
    {
        $avatar = trim((string) $avatar) ?: null;

        if ($avatar === null) {
            return null;
        }

        if (! Envio::caminhoConfere($avatar, Assistente::CAMPO_ENVIO)) {
            throw ValidationException::withMessages([
                'avatar' => 'Este arquivo não foi carregado por aqui. Solte o rosto do assistente de novo no campo.',
            ]);
        }

        return $avatar;
    }
}
