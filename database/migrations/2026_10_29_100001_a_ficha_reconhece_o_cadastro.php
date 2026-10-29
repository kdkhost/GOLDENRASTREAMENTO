<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A fala de quem acabou de ser reconhecido pelo e-mail.
 *
 * O e-mail dito no chat deixou de ser só uma anotação: ele localiza a ficha do cliente no CRM. Quem
 * é reconhecido ouve isso na hora — e essa frase é da casa, não da IA, como todas as outras que a
 * ficha edita. Em branco na coluna, vale o padrão de `config('assistente.persona')`.
 *
 * A migration também reescreve os textos padrão antigos da ficha, e só eles: quem já trocou uma
 * frase no painel continua com a frase que escreveu. O que muda é a Ana de quem nunca mexeu — a
 * mesma pessoa, falando de um jeito mais próximo e sem repetir saudação a cada resposta.
 */
return new class extends Migration
{
    /**
     * A impressão digital do texto de regras de antes desta versão. Comparar o texto inteiro seria
     * repetir aqui a frase que já vive na config; a impressão diz o mesmo e não convida ninguém a
     * "consertar" a cópia.
     */
    private const REGRAS_ANTIGAS = 'bf47d8c0d5a0f61a178841975a9018b9f8324c99d7a1a0682a0bc13f34152901';

    /** Os padrões de antes desta versão; só o que ainda estiver assim é atualizado. */
    private const ANTIGOS = [
        'saudacao' => 'Oi! Sou a Ana, da Golden Rastreamento. Fala comigo como fala com gente: me conta se é sobre rastreador, plano, plataforma ou fatura que eu te levo direto ao ponto, sem enrolação.',
        'pergunta_nome' => 'Antes de qualquer coisa: como você se chama? Assim eu te atendo pelo nome.',
        'confirmacao_nome' => 'Prazer, :nome! Pode dizer — o que eu resolvo por aqui hoje?',
        'pergunta_email' => 'E me diz o e-mail do seu cadastro? É por ele que eu encontro a sua conta — e nada seu sai para outro endereço.',
        'confirmacao_email' => 'Anotado. Pode seguir — o que eu resolvo por aqui?',
        'pedido_avaliacao' => 'Antes de encerrar: como foi falar comigo? Toque nas estrelas ou me escreva a nota de 1 a 5.',
        'agradecimento_avaliacao' => 'Valeu pela nota! Qualquer coisa por aqui, é só chamar.',
        'despedida' => 'Foi um prazer ajudar, :nome. Qualquer coisa por aqui, é só chamar — e boa estrada!',
    ];

    public function up(): void
    {
        Schema::table('assistente', function (Blueprint $table) {
            $table->text('confirmacao_cadastro')->nullable()->after('confirmacao_email');
        });

        $ficha = DB::table('assistente')->first();

        if ($ficha === null) {
            return;
        }

        $novos = [];

        foreach (self::ANTIGOS as $coluna => $antigo) {
            if (trim((string) ($ficha->{$coluna} ?? '')) === $antigo) {
                $novos[$coluna] = (string) config('assistente.persona.'.$coluna);
            }
        }

        $novos['regras'] = (string) config('assistente.persona.regras');

        if (hash('sha256', trim((string) $ficha->regras)) !== self::REGRAS_ANTIGAS) {
            unset($novos['regras']);
        }

        if ($novos !== []) {
            DB::table('assistente')->where('id', $ficha->id)->update($novos);
        }
    }

    public function down(): void
    {
        Schema::table('assistente', function (Blueprint $table) {
            $table->dropColumn('confirmacao_cadastro');
        });
    }
};
