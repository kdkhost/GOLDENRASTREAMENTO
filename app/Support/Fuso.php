<?php

namespace App\Support;

use App\Models\Setting;
use Throwable;

/**
 * O fuso horário em que o sistema relógia.
 *
 * A hospedagem acorda em UTC, e o `now()` do framework segue o fuso da aplicação — deixá-lo em UTC
 * fazia o relógio ao lado de cada fala da Ana sair três horas adiantado para quem escreve do
 * Brasil, do mesmo jeito que atrasa a data de um chamado lido no portal. Por isso o fuso é escolha
 * da casa, declarada uma vez só em `config('golden.fusos')`, e não um efeito colateral do servidor.
 *
 * A regra é a mesma das credenciais: **o banco manda, o `.env` é plano de fundo**. `APP_TIMEZONE`
 * vale até alguém escolher na tela Sistema → Configurações → Contato e endereço; a partir daí é o
 * que está gravado em `golden_settings` que conduz, e limpar o campo na tela devolve o valor ao
 * ambiente.
 *
 * Aplicar não é só escrever em `config()`: o framework definiu o fuso do PHP no bootstrap, antes
 * de qualquer provider, então mudar de ideia depois exige avisar `date_default_timezone_set()` —
 * é o que `now()`, o `format()` de toda view e o `diffForHumans()` do painel perguntam.
 */
class Fuso
{
    /** A chave do que o painel grava. */
    public const CHAVE = 'fuso_horario';

    /**
     * Os fusos que a tela oferece, e a única lista que o validador aceita.
     *
     * @return array<string, string> código IANA => rótulo em português
     */
    public static function opcoes(): array
    {
        return (array) config('golden.fusos');
    }

    /**
     * O fuso vigente: o escolhido no painel, senão o do ambiente, senão o da casa.
     *
     * Um valor gravado à mão no banco fora da lista fechada não é fuso: ele voltaria o relógio de
     * todo o sistema para o fuso do servidor, que é justamente o bug que esta classe existe para
     * fechar. Nesse caso vale o padrão de `config('app.timezone')`.
     */
    public static function vigente(): string
    {
        $escolhido = (string) Setting::get(self::CHAVE, '');

        return array_key_exists($escolhido, self::opcoes()) ? $escolhido : (string) config('app.timezone');
    }

    /**
     * Joga o fuso vigente sobre o PHP e sobre `config()`. Uma vez por requisição, no boot.
     *
     * Sem tabela de configurações (instalação que ainda não rodou o `migrate`) o fuso é o do
     * ambiente: o `.env` conduz, e o instalador não para por causa de uma linha que ainda não existe.
     */
    public static function aplicar(): void
    {
        try {
            $fuso = self::vigente();
        } catch (Throwable) {
            return;
        }

        config(['app.timezone' => $fuso]);
        date_default_timezone_set($fuso);
    }
}
