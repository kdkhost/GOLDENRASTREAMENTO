<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Um aviso dentro do painel, endereçado a uma pessoa.
 *
 * A escolha mais importante desta tabela é a linha por destinatário, e não a linha por evento.
 * É ela que faz "cada leitura elimina a sua" funcionar de verdade: quando a equipe tem três
 * pessoas, o fato de uma já ter lido não pode apagar o aviso das outras duas. Um único
 * `lida_em` compartilhado mentiria para quem ainda não viu.
 *
 * O outro lado da mesma decisão: o aviso é uma cópia do fato, não o fato. A ficha do lead, do
 * chamado ou da tarefa continua sendo a verdade; aqui ficam o título, o resumo e o endereço para
 * onde o clique leva (`rota` + `rota_param`). Por isso uma página só serve a qualquer tipo — a
 * página dinâmica não conhece tipos, ela conhece rotas.
 *
 * O ciclo de vida é o que mantém o sino pequeno. `lida_em` tira a linha do sino no instante da
 * leitura; `expira_em` tira do banco a que ninguém abriu, na poda. Sem o segundo, quem volta de
 * férias encontra trezentas linhas e aprende a ignorar todas.
 */
class Notificacao extends Model
{
    use HasFactory;

    // O plural português não é o que o inflector da framework adivinha: ele faria
    // "notificacaos". Mesma razão do `conversas_mensagens` do assistente — nome de tabela se
    // declara, não se espera que alguém adivinhe.
    protected $table = 'notificacoes';

    protected $fillable = [
        'tipo', 'titulo', 'texto', 'rota', 'rota_param',
        'dados_json', 'user_id', 'chave', 'lida_em', 'expira_em',
    ];

    protected $casts = [
        // Texto no banco, array no PHP: o MySQL antigo e o SQLite tratam colunas JSON de
        // maneiras diferentes, e o corpo do aviso não pode depender de qual dos dois está no ar.
        'dados_json' => 'array',
        'lida_em' => 'datetime',
        'expira_em' => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** As que ainda estão no sino: não lidas e ainda dentro do prazo. */
    public function scopeNaovistas(Builder $query): Builder
    {
        return $query->whereNull('lida_em')
            ->where(fn (Builder $q) => $q->whereNull('expira_em')->orWhere('expira_em', '>', now()));
    }

    public function scopePara(Builder $query, User $usuario): Builder
    {
        return $query->where('user_id', $usuario->id);
    }

    public function scopeVistas(Builder $query): Builder
    {
        return $query->whereNotNull('lida_em');
    }

    /** O catálogo da config: rótulo, ícone, tom e audiência de cada tipo de aviso. */
    public static function catalogo(): array
    {
        return (array) config('notificacoes.tipos', []);
    }

    public static function rotuloTipo(string $tipo): string
    {
        $item = (array) (self::catalogo()[$tipo] ?? []);

        return (string) ($item['rotulo'] ?? $tipo);
    }

    public static function tiposParaFiltro(): array
    {
        return collect(self::catalogo())
            ->map(fn (array $item): string => (string) ($item['rotulo'] ?? ''))
            ->filter()
            ->all();
    }

    public function itemDoCatalogo(): array
    {
        return (array) (self::catalogo()[$this->tipo] ?? []);
    }

    /** O rótulo do tipo, em palavras — nunca o `tipo` cru na tela. */
    public function rotulo(): string
    {
        return self::rotuloTipo($this->tipo);
    }

    public function icone(): string
    {
        $item = $this->itemDoCatalogo();

        return (string) ($item['icone'] ?? 'fa-bell');
    }

    /**
     * O tom do selo. Vem do catálogo, que é a fonte única do tipo de aviso: o sino, a listagem
     * e a ficha mostram a mesma cor para o mesmo tipo. A view não decide cor.
     */
    public function tom(): string
    {
        return (string) ($this->itemDoCatalogo()['tom'] ?? 'neutral');
    }

    public function foiLida(): bool
    {
        return $this->lida_em !== null;
    }

    /** Marca como lida: é o gesto que tira a linha do sino. Duas leituras não reabrem nada. */
    public function marcarComoLida(): void
    {
        if ($this->lida_em !== null) {
            return;
        }

        $this->forceFill(['lida_em' => now()])->save();
    }

    /**
     * Para onde o clique leva. Devolve null quando o aviso não tem página própria — o sino e a
     * listagem então mostram só o texto, sem link.
     *
     * A rota é verificada antes de ser montada porque a string vem do banco: um tipo de aviso
     * pode muito bem ter sido escrito numa versão que depois tirou a rota do ar, e um
     * `Route::url()` quebrado no meio do sino derrubaria todas as telas do painel.
     *
     * O parâmetro vai na posição, nunca na chave `id`: as rotas do painel nomeiam o que carregam
     * (`{lead}`, `{chamado}`, `{conversa}`), e `route('admin.leads.show', ['id' => 7])` morre com
     * "faltando o parâmetro [lead]" exatamente na rota que o aviso mais aponta. Passando o valor
     * sozinho a framework entrega para o primeiro espaço da URL, seja ele qual for.
     */
    public function link(): ?string
    {
        if ($this->rota === null || $this->rota === '' || ! Route::has($this->rota)) {
            return null;
        }

        $parametro = trim((string) $this->rota_param);
        $temEspaco = (Route::getRoutes()->getByName($this->rota)?->parameterNames() ?? []) !== [];

        // Rota sem espaço na URL (as listagens) não tem o que receber: o parâmetro é descartado
        // em vez de virar `?7` no fim do endereço.
        if ($parametro === '' || ! $temEspaco) {
            $parametro = '';
        }

        try {
            return route($this->rota, $parametro === '' ? [] : [$parametro]);
        } catch (\Throwable) {
            return null;
        }
    }

    /** O contexto estruturado do aviso, para a página dinâmica montar o corpo. */
    public function dados(): array
    {
        return (array) ($this->dados_json ?? []);
    }

    /**
     * O item do sino no mesmo formato para a partial e para o JSON.
     *
     * As duas pontas mostram a mesma linha; ter o formato aqui é o que impede o JavaScript de
     * montar um texto e o servidor outro — quem paga por isso é a pessoa que vê a notificação
     * mudar de nome depois que o sino se atualiza.
     *
     * @return array{id:int,titulo:string,texto:string,rotulo:string,icone:string,tom:string,quando:string,ficha:string,destino:?string}
     */
    public function paraSino(): array
    {
        return [
            'id' => $this->id,
            'titulo' => $this->titulo,
            'texto' => $this->resumo(),
            'rotulo' => $this->rotulo(),
            'icone' => $this->icone(),
            'tom' => $this->tom(),
            'quando' => $this->created_at->diffForHumans(),
            'ficha' => route('admin.notificacoes.show', $this),
            'destino' => $this->link(),
        ];
    }

    /**
     * Como cada chave do contexto se chama na ficha.
     *
     * O aviso guarda o fato em chaves de código (`lead_id`, `utm_source`); a ficha é uma página
     * para gente ler. Este mapa é a tradução, e ele mora no modelo — não na view — porque a
     * página dinâmica serve a todos os tipos e não pode ter um `@switch` que envelhece sozinho.
     */
    public const ROTULOS_DADOS = [
        'lead_id' => 'Lead',
        'cliente_id' => 'Cliente',
        'pedido_id' => 'Pedido',
        'chamado_id' => 'Chamado',
        'depoimento_id' => 'Depoimento',
        'conversa_id' => 'Conversa',
        'tarefa_id' => 'Tarefa',
        'conta_id' => 'Conta de anúncio',
        'customer_id' => 'Conta no Google',
        'numero' => 'Número',
        'nome' => 'Nome',
        'email' => 'E-mail',
        'telefone' => 'Telefone',
        'documento' => 'Documento',
        'canal' => 'Canal',
        'utm_source' => 'Origem (UTM)',
        'campanha' => 'Campanha',
        'total' => 'Valor',
        'pagamento' => 'Pagamento',
        'assunto' => 'Assunto',
        'cliente' => 'Cliente',
        'prioridade' => 'Prioridade',
        'motivo' => 'Motivo',
        'visitante' => 'Visitante',
        'pagina' => 'Página',
        'nota' => 'Nota',
        'comando' => 'Comando',
        'expressao' => 'Expressão',
        'rodou_em' => 'Rodou em',
    ];

    /**
     * O contexto em pares prontos para a ficha: rótulo em português e valor em texto.
     *
     * Valor nulo e lista ficam de fora — uma linha "E-mail: —" só ocupa espaço. Chave que não
     * está no mapa ainda aparece, com o nome humanizado: aviso novo não pode sumir da ficha
     * só porque ninguém lembrou de registrar o rótulo.
     *
     * @return array<int,array{rotulo:string,valor:string}>
     */
    public function dadosEtiquetados(): array
    {
        $linhas = [];

        foreach ($this->dados() as $chave => $valor) {
            if ($valor === null || $valor === '' || is_array($valor)) {
                continue;
            }

            if ($chave === 'prioridade') {
                $valor = Ticket::PRIORIDADES[$valor] ?? $valor;
            }

            $linhas[] = [
                'rotulo' => self::ROTULOS_DADOS[$chave] ?? Str::headline((string) $chave),
                'valor' => is_bool($valor) ? ($valor ? 'sim' : 'não') : (string) $valor,
            ];
        }

        return $linhas;
    }

    /** O corpo truncado para a listagem; a ficha mostra o texto inteiro. */
    public function resumo(): string
    {
        $teto = (int) config('notificacoes.texto_maximo', 500);

        return Str::limit(trim((string) $this->texto), $teto);
    }

    /**
     * As lidas mais velhas que a retenção e as nunca lidas que venceram. É o varredor da
     * central: a mesma query para o comando de poda, para o contador da tela e para o teste.
     */
    public function scopeParaAposentar(Builder $query, ?int $dias = null): Builder
    {
        $limite = now()->subDays($dias ?? static::diasDeRetencao());

        return $query->where(function (Builder $q) use ($limite): void {
            $q->where(fn (Builder $lida) => $lida->whereNotNull('lida_em')->where('created_at', '<', $limite))
                ->orWhere(fn (Builder $vencida) => $vencida->whereNotNull('expira_em')->where('expira_em', '<', now()));
        });
    }

    /**
     * Quantos dias uma notificação lida aguenta antes da poda. O painel manda — a chave fica no
     * banco, na tela de preferências; o config é o plano de fundo de instalação nova.
     */
    public static function diasDeRetencao(): int
    {
        $dias = (int) Setting::get('notificacoes_retencao_dias', config('notificacoes.retencao_dias_padrao', 30));

        return $dias > 0 ? $dias : (int) config('notificacoes.retencao_dias_padrao', 30);
    }
}
