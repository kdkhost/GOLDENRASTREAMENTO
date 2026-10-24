<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Customer;
use App\Models\Testimonial;
use App\Support\DepoimentosDoGoogle;
use App\Support\Envio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Depoimentos do site — a lista que o carrossel da home lê.
 *
 * Há três origens e elas se misturam na mesma tela e no mesmo carrossel: o texto digitado pela
 * equipe (`site`), a avaliação trazida do Google com a foto de quem escreveu (`google`) e o
 * depoimento que o próprio cliente manda pelo portal (`cliente`), esse sempre precisando de
 * aprovação aqui antes de aparecer no ar.
 */
class TestimonialController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.testimonials.index', [
            'depoimentos' => $this->query($request)->with('customer')->paginate(itens_por_pagina())->withQueryString(),
            'fontes' => Testimonial::FONTES,
            'google_configurado' => DepoimentosDoGoogle::configurado(),
            'pendentes' => Testimonial::where('aprovado', false)->count(),
        ]);
    }

    /** A mesma régua da listagem e do filtro: quem filtra e quem pagina lêem isto. */
    private function query(Request $request)
    {
        return Testimonial::query()
            ->when($request->filled('busca'), function ($q) use ($request) {
                $t = '%' . $request->busca . '%';
                $q->where(fn ($w) => $w->where('nome', 'like', $t)
                    ->orWhere('papel', 'like', $t)
                    ->orWhere('cidade', 'like', $t)
                    ->orWhere('texto', 'like', $t));
            })
            ->when($request->filled('nota'), fn ($q) => $q->where('nota', (int) $request->nota))
            ->when($request->filled('fonte'), fn ($q) => $q->where('fonte', $request->fonte))
            ->when($request->filled('situacao'), fn ($q) => $q->where('aprovado', $request->situacao === 'aprovado'))
            ->orderBy('ordem')
            ->latest();
    }

    public function create(): View
    {
        return view('admin.testimonials.form', [
            'depoimento' => new Testimonial(['aprovado' => true, 'nota' => 5, 'ordem' => 0, 'fonte' => 'site']),
            'clientes' => $this->clientes(),
        ]);
    }

    public function edit(Testimonial $depoimento): View
    {
        return view('admin.testimonials.form', ['depoimento' => $depoimento, 'clientes' => $this->clientes()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validar($request);
        $data['aprovado'] = $request->boolean('aprovado');
        $d = Testimonial::create($data);
        Activity::log('depoimento_criado', "Depoimento de {$d->nome} adicionado.", $d);

        return redirect()->route('admin.conteudo.depoimentos.index')->with('success', 'Depoimento adicionado.');
    }

    public function update(Request $request, Testimonial $depoimento): RedirectResponse
    {
        $data = $this->validar($request);
        $data['aprovado'] = $request->boolean('aprovado');
        $anterior = $depoimento->avatar;
        $depoimento->update($data);

        if (array_key_exists('avatar', $data) && $anterior && $anterior !== $data['avatar']) {
            Envio::remover($anterior, Testimonial::CAMPO_ENVIO);
        }

        Activity::log('depoimento_atualizado', "Depoimento de {$depoimento->nome} atualizado.", $depoimento);

        return redirect()->route('admin.conteudo.depoimentos.index')->with('success', 'Depoimento atualizado.');
    }

    private function validar(Request $request): array
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'cidade' => ['nullable', 'string', 'max:96'],
            'papel' => ['nullable', 'string', 'max:120'],
            'texto' => ['required', 'string', 'max:2000'],
            'nota' => ['required', 'integer', 'min:1', 'max:5'],
            'ordem' => ['nullable', 'integer', 'min:0'],
            'fonte' => ['required', 'string', 'in:' . implode(',', array_keys(Testimonial::FONTES))],
            'avatar' => ['nullable', 'string', 'max:500'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
        ]);

        // O dropzone só emite `avatar` quando há arquivo na fila — chave ausente é "deixa a foto
        // como está", não "apaga": sem isso cada edição sem tocar no campo perderia o avatar.
        if ($request->has('avatar')) {
            $data['avatar'] = $this->avatarValido($data['avatar'] ?? null);
        }
        $data['ordem'] = (int) ($data['ordem'] ?? 0);
        $data['customer_id'] = $data['customer_id'] ?? null;

        return $data;
    }

    /**
     * O avatar é o caminho gravado pelo dropzone (que devolve só a chave do disco) ou a URL
     * do Google aceita como está quando o download falha. Qualquer outro texto é rejeitado:
     * o campo é texto livre no formulário e ele vira atributo `src` no site público.
     */
    private function avatarValido(?string $avatar): ?string
    {
        $avatar = trim((string) $avatar) ?: null;

        if ($avatar === null) {
            return null;
        }

        if (str_starts_with($avatar, 'https://')) {
            $hospedeiro = (string) parse_url($avatar, PHP_URL_HOST);

            if (str_ends_with($hospedeiro, '.googleusercontent.com')) {
                return $avatar;
            }

            throw ValidationException::withMessages([
                'avatar' => 'Só o Google pode emprestar a foto por link. Baixe o avatar e solte aqui, ou deixe o campo vazio.',
            ]);
        }

        if (! Envio::caminhoConfere($avatar, Testimonial::CAMPO_ENVIO)) {
            throw ValidationException::withMessages([
                'avatar' => 'Este arquivo não foi carregado por aqui. Solte a foto de novo no campo.',
            ]);
        }

        return $avatar;
    }

    /**
     * Ficha com conta no portal é a única que pode assinar um depoimento: é por ela que a
     * equipe confere, na hora de aprovar, que quem escreveu é cliente da casa.
     */
    private function clientes()
    {
        return Customer::whereNotNull('email_portal')
            ->where('email_portal', '!=', '')
            ->orderBy('nome')
            ->pluck('nome', 'id');
    }

    /**
     * Botão "Atualizar do Google": mesmo caminho do `php artisan depoimentos:google`, só que
     * disparado da tela. Sem as variáveis do .env ele explica o que falta em vez de chamar a
     * API no escuro.
     */
    public function importar(Request $request): RedirectResponse
    {
        $limite = min(50, max(1, (int) config('golden.google_business.limite', 50)));

        if (! DepoimentosDoGoogle::configurado()) {
            return back()->with('error', 'A leitura do Google ainda não está configurada no .env: GOOGLE_BUSINESS_ACCOUNT, GOOGLE_BUSINESS_LOCATION e GOOGLE_BUSINESS_TOKEN.');
        }

        try {
            $resumo = DepoimentosDoGoogle::importar($limite);
        } catch (\Throwable $e) {
            Activity::log('depoimentos_google_recusados', 'Importação do Google falhou: '.$e->getMessage());

            return back()->with('error', 'O Google não devolveu as avaliações: '.$e->getMessage());
        }

        Activity::log('depoimentos_google_importados',
            "Importação do Google: {$resumo['novos']} novo(s), {$resumo['atualizados']} atualizado(s) de {$resumo['lidos']} leitura(s).");

        return back()->with('success', sprintf(
            '%d avaliação(es) lidas do Google: %d nova(s), %d atualizada(s)%s.',
            $resumo['lidos'], $resumo['novos'], $resumo['atualizados'],
            $resumo['sem_texto'] || $resumo['abaixo_da_minima']
                ? ", {$resumo['sem_texto']} sem texto e {$resumo['abaixo_da_minima']} abaixo da nota mínima"
                : ''
        ));
    }

    public function destroy(Testimonial $depoimento): RedirectResponse
    {
        $nome = $depoimento->nome;
        Envio::remover($depoimento->avatar, Testimonial::CAMPO_ENVIO);
        $depoimento->delete();
        Activity::log('depoimento_excluido', "Depoimento de {$nome} removido.");

        return back()->with('success', 'Depoimento removido.');
    }
}
