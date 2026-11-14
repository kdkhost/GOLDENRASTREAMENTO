<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ErrorPage;
use App\Services\ErrorPageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ErrorPageController extends Controller
{
    public function __construct(
        protected ErrorPageService $errorService
    ) {}

    public function index(): View
    {
        $paginas = $this->errorService->todas();

        return view('admin.erros.index', [
            'paginas' => $paginas,
            'codigos' => ErrorPageService::CODIGOS,
        ]);
    }

    public function edit(int $code): View
    {
        abort_unless(in_array($code, ErrorPageService::CODIGOS, true), 404);

        $dados = $this->errorService->obter($code);
        $registro = ErrorPage::where('code', $code)->first();

        return view('admin.erros.edit', [
            'code' => $code,
            'nomeCodigo' => ErrorPage::nomePadraoDoCodigo($code),
            'dados' => $dados,
            'registro' => $registro,
        ]);
    }

    public function update(Request $request, int $code): RedirectResponse
    {
        abort_unless(in_array($code, ErrorPageService::CODIGOS, true), 404);

        $validados = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000'],
            'primary_button_text' => ['nullable', 'string', 'max:80'],
            'primary_button_url' => ['nullable', 'string', 'max:255'],
            'show_whatsapp_button' => ['nullable', 'boolean'],
            'whatsapp_custom_text' => ['nullable', 'string', 'max:120'],
            'icon' => ['required', 'string', 'max:60'],
            'active' => ['nullable', 'boolean'],
        ]);

        $this->errorService->salvar($code, $validados);

        Activity::log(
            'error_page_update',
            "Personalizou a página de erro HTTP {$code} ({$validados['title']}).",
            $request->user(),
            ['code' => $code]
        );

        return redirect()
            ->route('admin.erros.index')
            ->with('success', "Página de erro HTTP {$code} atualizada com sucesso!");
    }

    public function preview(int $code): View
    {
        abort_unless(in_array($code, ErrorPageService::CODIGOS, true), 404);

        if ($code === 503) {
            return view('errors.503');
        }

        $dados = $this->errorService->obter($code);

        return view('errors.layout', ['dados' => $dados]);
    }

    public function restaurar(Request $request, int $code): RedirectResponse
    {
        abort_unless(in_array($code, ErrorPageService::CODIGOS, true), 404);

        $this->errorService->restaurar($code);

        Activity::log(
            'error_page_reset',
            "Restaurou os textos padrão de fábrica para o erro HTTP {$code}.",
            $request->user(),
            ['code' => $code]
        );

        return redirect()
            ->route('admin.erros.edit', $code)
            ->with('success', "Textos padrão da página de erro HTTP {$code} restaurados!");
    }
}
