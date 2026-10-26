<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Assistente;
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
            'faltando' => Fabrica::faltando(),
            'ligado' => Fabrica::ligado(),
        ]);
    }

    public function atualizar(Request $request): RedirectResponse
    {
        $data = $this->validar($request);
        $ficha = Assistente::garanta();

        foreach (['ativo', 'no_site', 'no_funil', 'no_chamado'] as $canal) {
            $data[$canal] = $request->boolean($canal);
        }

        $anterior = $ficha->avatar;
        $ficha->update($data);

        if (array_key_exists('avatar', $data) && $anterior && $anterior !== $data['avatar']) {
            Envio::remover($anterior, Assistente::CAMPO_ENVIO);
        }

        Activity::log('assistente_atualizado', 'A ficha do assistente foi atualizada.', $ficha);

        return redirect()->route('admin.assistente.persona')->with('success', 'Ficha do assistente salva.');
    }

    private function validar(Request $request): array
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'cargo' => ['nullable', 'string', 'max:160'],
            'saudacao' => ['nullable', 'string', 'max:1000'],
            'tom' => ['required', 'string', 'in:' . implode(',', array_keys(Assistente::tons()))],
            'regras' => ['nullable', 'string', 'max:8000'],
            'avatar' => ['nullable', 'string', 'max:190'],
            'avatar_alt' => ['nullable', 'string', 'max:120'],
            'voz' => ['nullable', 'string', 'in:' . implode(',', array_keys(Fabrica::opcoesDeVoz()))],
        ], [
            'tom.in' => 'O tom escolhido não está na lista da ficha.',
            'voz.in' => 'A voz escolhida não está na lista dos provedores ligados.',
        ], [
            'nome' => 'nome',
            'cargo' => 'cargo',
            'saudacao' => 'saudação',
            'tom' => 'tom de voz',
            'regras' => 'regras do atendimento',
            'avatar' => 'avatar',
            'avatar_alt' => 'descrição do avatar',
            'voz' => 'voz das respostas',
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
