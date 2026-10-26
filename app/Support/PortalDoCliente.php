<?php

namespace App\Support;

use App\Models\Customer;
use Illuminate\Support\Collection;

/**
 * Identidade do portal do cliente.
 *
 * Cadastro no portal não cria "um usuário" separado do CRM: ele procura a ficha que já
 * existe (primeiro pelo documento, depois pelo e-mail) e entrega a chave de acesso a ela.
 * É isso que faz o cliente ver, na primeira tela, os pedidos que fez como convidado e o
 * histórico que o vendedor já tem no painel — sem segunda tabela e sem pedido órfão.
 */
class PortalDoCliente
{
    /** Só dígitos: o CRM aceita "123.456.789-00" e o portal precisa bater com "12345678900". */
    public static function documento(?string $valor): string
    {
        return preg_replace('/\D/', '', (string) $valor);
    }

    /**
     * As fichas que esta pessoa pode ser, na ordem de uso. Documento é a prova mais forte;
     * e-mail sozinho vem depois. Fichas que já têm conta ficam de fora — ninguém abre a
     * segunda porta para a mesma casa. Entre as candidatas, a que tem mais pedidos ganha,
     * porque é ela que o cliente reconhece ao entrar.
     *
     * @return Collection<int, Customer>
     */
    public static function candidatas(?string $documento, string $email): Collection
    {
        $d = self::documento($documento);

        return Customer::whereNull('password')
            ->withCount('orders')
            ->where(function ($q) use ($d, $email) {
                if ($d !== '') {
                    $q->orWhereRaw("REPLACE(REPLACE(documento, '.', ''), '-', '') = ?", [$d]);
                }

                $q->orWhereRaw('LOWER(email) = ?', [mb_strtolower($email)]);
            })
            ->orderByDesc('orders_count')
            ->orderBy('id')
            ->get();
    }

    /**
     * Entrega a chave de acesso à ficha escolhida — reaproveitando o cadastro do CRM ou
     * criando a ficha quando a pessoa nunca passou por aqui.
     */
    public static function vincular(array $dados, ?Customer $ficha): Customer
    {
        $email = mb_strtolower($dados['email']);
        $documento = self::documento($dados['documento'] ?? null);

        if ($ficha === null) {
            $ficha = new Customer([
                'nome' => $dados['nome'],
                'email' => $email,
                'telefone' => $dados['telefone'] ?? null,
                'documento' => $documento ?: null,
                'status' => 'ativo',
            ]);
        }

        $ficha->documento ??= $documento ?: null;
        $ficha->tipo = Customer::tipoPorDocumento($ficha->documento);
        $ficha->email_portal = $email;

        // A primeira chave é o que interessa ao painel: quem já tinha conta e só entrou de novo
        // não abre chamado nenhum na central. Por isso a leitura acontece antes da senha nova.
        $primeiraChave = $ficha->password === null;
        $ficha->password = $dados['password'];

        if (! $ficha->telefone && ! empty($dados['telefone'])) {
            $ficha->telefone = $dados['telefone'];
        }

        $ficha->save();

        if ($primeiraChave) {
            Notifica::disparar(
                'conta_portal',
                'Conta do portal aberta: '.$ficha->nome,
                $email.($ficha->documento ? ' · documento '.$ficha->documento : '')
                    .' — a ficha já aparece em Clientes.',
                (string) $ficha->id,
                [
                    'cliente_id' => $ficha->id,
                    'nome' => $ficha->nome,
                    'email' => $email,
                    'documento' => $ficha->documento,
                ],
                'conta_portal:'.$ficha->id,
            );
        }

        return $ficha;
    }

    /** Este e-mail de acesso já tem conta no portal? */
    public static function emailEmUso(string $email, ?int $ignorarId = null): bool
    {
        return Customer::whereNotNull('password')
            ->when($ignorarId, fn ($q) => $q->whereKeyNot($ignorarId))
            ->whereRaw('LOWER(email_portal) = ?', [mb_strtolower($email)])
            ->exists();
    }

    /** A ficha que pode receber a recuperação de senha deste e-mail (só quem já tem conta). */
    public static function porEmailDeAcesso(string $email): ?Customer
    {
        return Customer::whereNotNull('password')
            ->whereRaw('LOWER(email_portal) = ?', [mb_strtolower($email)])
            ->first();
    }
}
