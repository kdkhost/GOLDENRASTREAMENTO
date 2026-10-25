{{--
    Ícones do Font Awesome servidos pela própria pasta public, e não pelo cdnjs. Um
    CSS de terceiro no <head> custa a resolução de DNS, o aperto de mão TLS e uma
    conexão nova antes de o primeiro pixel aparecer — em 4G são uns 300 ms de tela em
    branco que não dependem do nosso servidor. Copiado para dentro, o arquivo entra no
    mesmo cache de um ano que já vale para o resto do CSS e reaproveita a conexão que o
    navegador já tem com o site.

    A pasta repete o desenho da distribuição oficial (css/ e webfonts/ lado a lado)
    porque o CSS busca a fonte por caminho relativo: mudado o desenho, o caminho
    quebra e a tela enche de quadradinhos no lugar dos ícones.

    Não há preload da fonte de propósito: ela é chamada por caminho relativo dentro do
    CSS, então qualquer endereço adiantado que a gente escrevesse aqui seria uma
    segunda cópia baixada e nunca usada.
--}}
<link rel="stylesheet" href="{{ asset('css/fontawesome/css/all.min.css') }}?v={{ filemtime(public_path('css/fontawesome/css/all.min.css')) }}">
