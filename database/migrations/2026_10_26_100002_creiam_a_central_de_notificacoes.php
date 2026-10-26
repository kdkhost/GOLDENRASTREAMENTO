<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A central de notificações: o aviso interno, independente do e-mail.
 *
 * O e-mail continua saindo exatamente como estava — ele é o aviso para quem está fora do
 * painel. Esta tabela é o aviso para quem está dentro: o sino no topo, a página dedicada e a
 * ficha de cada aviso. Os dois canais nascem do mesmo evento de propósito; um não substitui
 * o outro porque o e-mail se perde na caixa de entrada e o sino não alcança quem está em
 * viagem.
 *
 * Uma linha por destinatário, e não uma linha por evento compartilhada entre todos. É isto
 * que faz o "cada leitura elimina a sua" funcionar: quando a equipe é formada por três
 * pessoas, o fato de uma já ter lido não pode apagar o aviso das outras duas — e sem a
 * duplicação por destinatário não haveria onde guardar o `lida_em` de cada uma.
 *
 * `lida_em` é o único relógio que importa para a tela e `expira_em` é o que a poda usa para o
 * que nunca foi lido: sem ele, a pessoa que não abre o painel em três meses entra num sino com
 * trezentas linhas, que é o jeito certo de fazer ninguém mais abrir o painel.
 *
 * `chave` é o freio de repetição. Um cron que falha a cada quinze minutos não pode gerar
 * noventa e seis avisos idênticos por dia, e um lead que já foi avisado não pode ser avisado
 * de novo porque alguém atualizou a ficha dele. Quem cria o aviso escolhe a chave, e o banco
 * garante: duplicata para a mesma pessoa simplesmente não entra.
 *
 * A segunda tabela é o gosto de cada um. Notificação que chega de tudo é notificação que se
 * cala: a pessoa desliga o que não é da função dela e o sino continua dizendo o que importa.
 * A linha só existe para o tipo que foi desligado — o padrão é receber, e um cadastro por
 * preferência ativa multiplicaria linhas por tipo sem informar nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificacoes', function (Blueprint $table) {
            $table->id();
            // A chave do catálogo em config/notificacoes.php: rótulo, ícone, tom e retenção
            // vêm de lá. Nada de cor nem de ícone decidido dentro da view.
            $table->string('tipo', 40);
            $table->string('titulo', 190);
            $table->text('texto')->nullable();
            // Para onde o clique leva. Rota nomeada + parâmetro em texto, porque o parâmetro
            // pode ser um id, um slug ou nada — e a tela não precisa saber qual.
            $table->string('rota', 190)->nullable();
            $table->string('rota_param', 190)->nullable();
            // O contexto estruturado do aviso (id do lead, nome, valor do pedido): é o que a
            // página dinâmica lê para montar o corpo sem precisar de uma tabela por tipo.
            $table->text('dados_json')->nullable();
            // Dono do aviso. Cascata e não nullOnDelete porque a linha É o estado de leitura
            // daquela pessoa: apagado o usuário, o aviso não tem mais a quem pertencer.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('chave', 190)->nullable();
            $table->timestamp('lida_em')->nullable();
            $table->timestamp('expira_em')->nullable();
            $table->timestamps();

            // A consulta do sino e da listagem: as minhas não lidas, da mais nova para a mais
            // velha. Sem este índice o painel contaria notificação a cada requisição em tabela cheia.
            $table->index(['user_id', 'lida_em', 'created_at']);
            $table->index(['user_id', 'expira_em']);
            // Deduplicação. `chave` nula fica de fora por definição do MySQL: dois NULL não
            // colidem, então o aviso sem chave (evento único, sempre novo) nunca é bloqueado.
            $table->unique(['chave', 'user_id']);
        });

        Schema::create('notificacao_preferencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tipo', 40);
            // Uma coluna só, e ela não encosta no e-mail: o que a pessoa desliga aqui é o sino
            // dela. Tirar aviso por e-mail do endereço da equipe é outra decisão, tomada na
            // tela de configurações, e nada neste cadastro a afeta.
            $table->boolean('no_painel')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificacao_preferencias');
        Schema::dropIfExists('notificacoes');
    }
};
