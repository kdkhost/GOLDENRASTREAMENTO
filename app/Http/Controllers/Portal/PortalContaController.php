<?php

namespace App\Http\Controllers\Portal;

use App\Models\Activity;
use App\Services\Whmcs\Cliente as Financeiro;
use App\Services\Whmcs\Veiculacao;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Meus dados: o cliente revisa e corrige o próprio cadastro, e troca a senha do portal.
 *
 * O documento fica fora da edição — é a chave que amarra a ficha ao pedido e ao contrato, e
 * reescrevê-lo pela tela do portal desmancharia o vínculo. Quem precisa mexer no CNPJ é a
 * equipe, na ficha em Admin → Clientes.
 */
class PortalContaController extends PortalBaseController
{
    public function dados(): View
    {
        return view('portal.dados', [
            'cliente' => $this->cliente(),
            'total_gasto_cents' => (int) $this->cliente()->orders()->reorder()->where('status', '!=', 'cancelado')->sum('total_cents'),
            'financeiro' => [
                'configurado' => $this->financeiro()->configurado(),
                'ligado' => (bool) $this->cliente()->whmcs_id,
                'faturas' => Veiculacao::abre('faturas'),
            ],
        ]);
    }

    public function salvarDados(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'telefone' => ['required', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:160'],
            'endereco' => ['nullable', 'string', 'max:160'],
            'cidade' => ['nullable', 'string', 'max:96'],
            'uf' => ['nullable', 'string', 'max:2'],
            'cep' => ['nullable', 'string', 'max:12'],
        ]);

        $dados['uf'] = strtoupper((string) ($dados['uf'] ?? ''));
        $dados['cep'] = preg_replace('/\D/', '', (string) ($dados['cep'] ?? '')) ?: null;

        $cliente = $this->cliente();
        $cliente->fill(array_map(fn ($v) => blank($v) ? null : trim((string) $v), $dados));
        $cliente->save();

        Activity::log('portal_dados_atualizados', 'Cadastro atualizado pelo portal.', $cliente, por: $cliente->nome);

        $espelhou = $this->espelharNoFinanceiro(
            'dados',
            fn (Financeiro $financeiro, int $id): bool => $financeiro->atualizarDados($id, $dados + ['tipo' => $cliente->tipo])
        );

        if ($espelhou === false) {
            return back()
                ->with('success', 'Cadastro atualizado.')
                ->with('warning', 'O financeiro não respondeu agora, então os dados de lá continuam como estavam. Tente de novo mais tarde ou fale com a equipe.');
        }

        return back()->with('success', 'Cadastro atualizado.');
    }

    public function salvarSenha(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'senha_atual' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $cliente = $this->cliente();

        if (! Hash::check($dados['senha_atual'], (string) $cliente->password)) {
            throw ValidationException::withMessages(['senha_atual' => 'A senha atual não confere.']);
        }

        $cliente->password = $dados['password'];
        $cliente->save();

        Activity::log('portal_senha_trocada', 'Senha do portal alterada na tela Meus dados.', $cliente, por: $cliente->nome);

        $espelhou = $this->espelharNoFinanceiro(
            'senha',
            fn (Financeiro $financeiro, int $id): bool => $financeiro->atualizarSenha($id, $dados['password'])
        );

        if ($espelhou === false) {
            return back()
                ->with('success', 'Senha nova em uso.')
                ->with('warning', 'A senha do financeiro não foi atualizada agora: por lá continua a anterior. Tente de novo mais tarde ou fale com a equipe.');
        }

        return back()->with('success', 'Senha nova em uso.');
    }
}
