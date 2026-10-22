<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        View::composer(['layouts.site', 'site.*', 'admin.layouts.app'], function ($view) {
            $whatsapp = preg_replace('/\D/', '', Setting::get('contato_whatsapp', config('golden.whatsapp')));
            $view->with('golden', [
                'marca' => config('golden.marca'),
                'whatsapp' => $whatsapp,
                'whatsapp_url' => 'https://wa.me/' . $whatsapp,
                'email' => Setting::get('contato_email', config('golden.email')),
                'tracking' => Setting::get('tracking_enabled', '0') === '1',
                'ga_id' => Setting::get('ga_measurement_id'),
                'meta_pixel' => Setting::get('meta_pixel_id'),
            ]);
        });
    }
}
