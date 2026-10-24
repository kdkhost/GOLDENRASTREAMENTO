<?php

namespace App\Models;

use App\Models\Concerns\CachesContent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Testimonial extends Model
{
    use CachesContent;

    /** Chave de config/envios.php que regula o campo de avatar do painel. */
    public const CAMPO_ENVIO = 'depoimento_avatar';

    /** De onde veio cada depoimento — é isto que a mesclagem do site expõe. */
    public const FONTES = [
        'site' => 'Escrito no painel',
        'google' => 'Avaliação do Google',
        'cliente' => 'Enviado pelo cliente',
    ];

    protected array $cacheKeys = ['golden.depoimentos_site'];

    protected $fillable = ['nome', 'cidade', 'papel', 'texto', 'nota', 'aprovado', 'ordem', 'fonte', 'externo_id', 'customer_id', 'avatar'];

    protected $casts = ['aprovado' => 'boolean', 'nota' => 'integer', 'ordem' => 'integer'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function scopeAprovados($q)
    {
        return $q->where('aprovado', true)->orderBy('ordem')->orderByDesc('created_at');
    }

    /** Depoimentos aprovados do carrossel, servidos do cache. */
    public static function aprovadosParaSite(int $limite = 6): array
    {
        $todos = Cache::rememberForever('golden.depoimentos_site', function () {
            return static::aprovados()->get(['nome', 'cidade', 'papel', 'texto', 'nota', 'fonte', 'avatar'])
                ->map(fn (self $t) => [
                    'nome' => $t->nome,
                    'cidade' => $t->cidade,
                    'papel' => $t->papel,
                    'texto' => $t->texto,
                    'nota' => $t->nota,
                    'fonte' => $t->fonte,
                    'fonte_label' => $t->fonteLabel(),
                    'avatar' => $t->avatarUrl(),
                    'iniciais' => $t->iniciais(),
                ])
                ->all();
        });

        return array_slice($todos, 0, max(1, $limite));
    }

    public function fonteLabel(): string
    {
        return self::FONTES[$this->fonte] ?? self::FONTES['site'];
    }

    /**
     * A foto do autor: um caminho do disco público (o que a importação do Google e o upload
     * do painel gravam) ou a URL devolvida pelo próprio Google. Sem foto, o carrossel desenha
     * as iniciais — é por isso que avatarUrl() devolve null em vez de adivinhar um arquivo.
     */
    public function avatarUrl(): ?string
    {
        $valor = trim((string) $this->avatar);

        if ($valor === '') {
            return null;
        }

        return str_starts_with($valor, 'https://') ? $valor : asset('storage/'.$valor);
    }

    public function iniciais(): string
    {
        $partes = preg_split('/\s+/', trim((string) $this->nome)) ?: [];

        return mb_strtoupper(mb_substr(($partes[0] ?? 'G'), 0, 1).mb_substr($partes[1] ?? '', 0, 1));
    }
}
