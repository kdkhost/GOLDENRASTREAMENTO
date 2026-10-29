<?php

namespace App\Services\Seguranca;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * A segunda chave da porta: o código de seis dígitos que nasce no celular da pessoa.
 *
 * TOTP padrão (RFC 6238), o mesmo que Google Authenticator, Authy, Microsoft Authenticator e Bitwarden
 * entendem. Nada aqui é invenção da casa: o cálculo do código vem do `pragmarx/google2fa`, o mesmo
 * motor que o Laravel Fortify usa, e o desenho do QR vem do `bacon/bacon-qr-code`. Reescrever HMAC
 * por conta própria é o jeito mais rápido de ter um dois fatores que parece funcionar e aceita
 * código de dez minutos atrás.
 *
 * O segredo e os códigos de recuperação são gravados **cifrados** (cast `encrypted` no model). Um
 * despejo da tabela `users` entrega então o mesmo que entregava antes: nada que abra a porta.
 *
 * Os códigos de recuperação existem porque celular morre, é roubado e é trocado. Sem eles, o dois
 * fatores vira a fechadura que tranca do lado de dentro quem esqueceu a chave — e o administrador
 * que perde o aparelho liga para o suporte às nove da noite. Cada um deles abre a porta **uma** vez
 * e se apaga em seguida: um código queimado não vale nada para quem o encontrou num print.
 */
class DoisFatores
{
    /** Alfabeto sem as letras que se confundem no teclado do celular (I/L/O e 0/1). */
    private const ALFABETO = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    private Google2FA $motor;

    public function __construct()
    {
        $this->motor = new Google2FA;
    }

    public function novoSegredo(): string
    {
        return $this->motor->generateSecretKey(32);
    }

    /** A linha `otpauth://` que qualquer aplicativo de autenticação lê. */
    public function urlDeConfiguracao(string $email, string $segredo): string
    {
        $emissor = (string) config('golden.marca.nome', 'Golden');

        return 'otpauth://totp/'
            .rawurlencode($emissor.':'.$email)
            .'?secret='.$segredo
            .'&issuer='.rawurlencode($emissor)
            .'&algorithm=SHA1&digits=6&period=30';
    }

    /**
     * O QR desenhado pela própria página, em SVG inline.
     *
     * Não vira arquivo no disco de propósito: um QR gravado em `storage/app/public` é a chave
     * secreta de alguém publicada na URL, e o cache do navegador guarda a imagem mesmo depois de a
     * pessoa sair da tela. SVG numa tag `<img>` não executa nada.
     */
    public function qrEmSvg(string $email, string $segredo): string
    {
        $desenho = new ImageRenderer(new RendererStyle(224, 8), new SvgImageBackEnd);

        return (new Writer($desenho))->writeString($this->urlDeConfiguracao($email, $segredo));
    }

    /**
     * O código de seis dígitos vale? A janela vem da tela de Configurações, em períodos de 30
     * segundos para cada lado: sem tolerância nenhuma, um celular com relógio dois segundos adiantado
     * faz a pessoa se convencer de que a chave está errada.
     */
    public function codigoValido(string $segredo, string $codigo): bool
    {
        return $this->motor->verifyKey($segredo, preg_replace('/\D/', '', $codigo) ?? '', (int) config('seguranca.totp_janela', 3));
    }

    /** Oito códigos de uso único, no formato `XXXXX-XXXXX` que se copia de um papel. */
    public function novosCodigosDeRecuperacao(): array
    {
        return array_map(fn (): string => $this->grupo().'-'.$this->grupo(), range(1, 8));
    }

    /**
     * Consome um código de recuperação. Verdadeiro só se ele estava na lista — e aí ele sai dela
     * na mesma hora, com o resto intacto.
     */
    public function queimarCodigoDeRecuperacao(User $usuario, string $codigo): bool
    {
        $lista = (array) ($usuario->two_factor_recovery_codes ?? []);
        $pedido = Str::upper(trim($codigo));

        $sobrantes = array_values(array_filter($lista, fn (string $um): bool => ! hash_equals(Str::upper($um), $pedido)));

        if (count($sobrantes) === count($lista)) {
            return false;
        }

        $usuario->forceFill(['two_factor_recovery_codes' => $sobrantes === [] ? null : $sobrantes])->save();

        return true;
    }

    /** Desliga tudo: segredo, códigos e a confirmação. A conta volta a abrir só com a senha. */
    public function desligar(User $usuario): void
    {
        $usuario->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    /**
     * A política do painel, em uma frase: quem hoje é obrigado a ter o aplicativo ligado. Vem da
     * tela de Configurações e, se ninguém mexeu lá, do arquivo de configuração.
     */
    public static function exige(User $usuario): bool
    {
        return match ((string) config('seguranca.dois_fatores', 'ninguem')) {
            'equipe' => true,
            'administradores' => $usuario->isAdmin(),
            default => false,
        };
    }

    private function grupo(): string
    {
        return implode('', array_map(
            fn (): string => self::ALFABETO[random_int(0, strlen(self::ALFABETO) - 1)],
            range(1, 5)
        ));
    }
}
