<?php

namespace App\Providers;

use App\Services\GoogleAds\GoogleAdsService;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\Two\GoogleProvider;

/**
 * A integração Google Ads montada sobre a biblioteca oficial.
 *
 * Faz duas coisas e só estas: publica o serviço que fala com a API e registra no Socialite o
 * consentimento que autoriza o painel a ler a conta do anunciante. O serviço é singleton porque
 * montar um cliente da API por requisição é jogar fora o trabalho do construtor — e porque ele
 * é a única classe do sistema que conhece GAQL.
 *
 * O driver entra com o nome `google-ads`, e não como o `google` puro do Socialite, por uma
 * razão: o escopo `adwords` não é o que se pede para entrar no site com a conta Google, e as
 * credenciais do app OAuth do Ads têm um endereço de volta próprio. Os dois usos passam a
 * existir lado a lado sem um descrever o outro.
 */
class GoogleAdsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GoogleAdsService::class);
    }

    public function boot(): void
    {
        /** @var \Laravel\Socialite\SocialiteManager $socialite */
        $socialite = $this->app->make(SocialiteFactory::class);

        $socialite->extend('google-ads', function ($app) use ($socialite) {
            $config = $app['config']['services.google_ads'];

            // `setScopes` substitui, não soma: a lista do config é a fonte única do que o
            // consentimento pede, e o padrão do GoogleProvider (openid, profile, email) já
            // está dentro dela — sem repetição entre as duas pontas.
            //
            // Os dois parâmetros a mais não são enfeite: sem `access_type=offline` o Google
            // devolve só o token de acesso, que dura uma hora, e o painel fica sem nada para
            // guardar. Sem `prompt=consent` ele nem isso — um consentimento já dado na mesma
            // sessão volta sem token de renovação, e a conta conectada nasce morta.
            return $socialite->buildProvider(GoogleProvider::class, $config)
                ->setScopes($app['config']['google_ads.escopos'])
                ->with(['access_type' => 'offline', 'prompt' => 'consent']);
        });
    }
}
