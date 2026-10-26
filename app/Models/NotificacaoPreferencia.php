<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * O gosto de uma pessoa em relação a um tipo de aviso.
 *
 * A linha só existe para o que foi desligado: o padrão é receber, e um cadastro por preferência
 * ativa multiplicaria linhas por tipo sem informar nada. Isso também faz a ausência ser a
 * resposta — o `default true` do `emPainel()` não é um chute de conveniência, é a regra.
 *
 * O que se desliga aqui é o sino da pessoa. O e-mail da equipe não passa por esta tabela e não
 * pode ser calado por ela: quem quiser mudar o endereço ou o que sai por e-mail decide na tela
 * de configurações, que é onde o canal inteiro mora.
 */
class NotificacaoPreferencia extends Model
{
    protected $fillable = ['user_id', 'tipo', 'no_painel'];

    protected $casts = ['no_painel' => 'boolean'];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** A pessoa recebe este tipo no painel? Sem linha gravada, a resposta é sim. */
    public static function emPainel(User $usuario, string $tipo): bool
    {
        $linha = static::where('user_id', $usuario->id)->where('tipo', $tipo)->first();

        return $linha === null ? true : (bool) $linha->no_painel;
    }

    /**
     * Os tipos que esta pessoa desligou. É a lista que a criação de aviso consulta uma vez por
     * disparo e aplica em memória — não um `whereNotIn` dentro de um loop por destinatário.
     */
    public static function desligadosPara(User $usuario): array
    {
        return static::where('user_id', $usuario->id)
            ->where('no_painel', false)
            ->pluck('tipo')
            ->all();
    }
}
