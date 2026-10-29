<?php

/*
|--------------------------------------------------------------------------
| Campos de envio do painel (arrastar e soltar)
|--------------------------------------------------------------------------
| Uma entrada por campo, identificada pela chave usada no partial
| (partials/upload) e no endpoint Admin\UploadController. O partial e o
| validador leem a mesma regra, então a dica escrita no campo — formatos,
| tamanho máximo e dimensão recomendada — nunca diverge do que o servidor
| aceita. A pasta de armazenamento fica aqui: o navegador envia só a chave,
| nunca o destino.
*/

return [
    'produto_imagem' => [
        'tipo' => 'imagem',
        'pasta' => 'produtos',
        'titulo' => 'Imagem do produto',
        'chamada' => 'Arraste a foto aqui ou clique para escolher',
        'formatos' => ['jpg', 'jpeg', 'png', 'webp'],
        'max_mb' => 4,
        'dimensao' => '1200 × 1200 px',
        'proporcao' => '1:1',
    ],

    'produto_video' => [
        'tipo' => 'video',
        'pasta' => 'produtos/video',
        'titulo' => 'Vídeo do produto',
        'chamada' => 'Arraste o vídeo aqui ou clique para escolher',
        'formatos' => ['mp4', 'webm'],
        'max_mb' => 40,
        'dimensao' => '1920 × 1080 px',
        'proporcao' => '16:9',
    ],

    'pedido_documento' => [
        'tipo' => 'documento',
        'pasta' => 'pedidos',
        'titulo' => 'Documentos do pedido',
        'chamada' => 'Arraste comprovantes e notas aqui ou clique para escolher',
        'formatos' => ['pdf', 'jpg', 'jpeg', 'png'],
        'max_mb' => 10,
        'dimensao' => null,
        'proporcao' => null,
    ],

    /*
    | O contrato do cliente entra por aqui: um PDF por ficha de contrato, gravado em
    | storage/app/public/contratos e entregue ao portal pela rota autenticada de download
    | — o caminho nunca é publicado na página do cliente.
    */
    'contrato_documento' => [
        'tipo' => 'documento',
        'pasta' => 'contratos',
        'titulo' => 'PDF do contrato',
        'chamada' => 'Arraste o contrato assinado aqui ou clique para escolher',
        'formatos' => ['pdf'],
        'max_mb' => 8,
        'dimensao' => null,
        'proporcao' => null,
    ],

    /*
    | As fotos do site (Admin → Conteúdo → Imagens e textos) entram todas por
    | este campo: a dimensão recomendada muda de slot para slot e é a tela do
    | painel que a mostra, não a regra. O destino no disco é sempre `site`.
    */
    'site_imagem' => [
        'tipo' => 'imagem',
        'pasta' => 'site',
        'titulo' => 'Foto do site',
        'chamada' => 'Arraste a foto aqui ou clique para escolher',
        'formatos' => ['jpg', 'jpeg', 'png', 'webp'],
        'max_mb' => 6,
        'dimensao' => null,
        'proporcao' => null,
    ],

    /*
    | A foto de quem deu o depoimento (Admin → Conteúdo → Depoimentos, e a importação do
    | Google). Quadrada e pequena de propósito: o carrossel desenha o avatar em 56 px, e
    | um retrato grande pago em cada cartão do site não é troca justa.
    */
    'depoimento_avatar' => [
        'tipo' => 'imagem',
        'pasta' => 'site/depoimentos',
        'titulo' => 'Foto do autor',
        'chamada' => 'Arraste o avatar aqui ou clique para escolher',
        'formatos' => ['jpg', 'jpeg', 'png', 'webp'],
        'max_mb' => 2,
        'dimensao' => '112 × 112 px',
        'proporcao' => '1:1',
    ],

    /*
    | O rosto do assistente (Admin → Atendimento → Assistente → Ficha). Quadrada de propósito:
    | a bolha do chat e o avatar da conversa são desenhados em círculo, e um retrato retangular
    | perderia o rosto na corte. Sem arquivo, a tela desenha a inicial do nome — o mesmo
    | desenho do carrossel de depoimentos.
    */
    'assistente_avatar' => [
        'tipo' => 'imagem',
        'pasta' => 'assistente',
        'titulo' => 'Rosto do assistente',
        'chamada' => 'Arraste o avatar aqui ou clique para escolher',
        'formatos' => ['jpg', 'jpeg', 'png', 'webp'],
        'max_mb' => 2,
        'dimensao' => '96 × 96 px',
        'proporcao' => '1:1',
    ],

    /*
    | A foto de quem trabalha no painel (Admin → Usuários). Redonda na tela — a barra do topo, a
    | lista e a própria ficha —, por isso a recomendação quadrada: um retrato 3:4 perderia o
    | queixo no recorte. Sem foto, a tela desenha as iniciais do nome.
    */
    'usuario_avatar' => [
        'tipo' => 'imagem',
        'pasta' => 'usuarios',
        'titulo' => 'Foto do usuário',
        'chamada' => 'Arraste a foto aqui ou clique para escolher',
        'formatos' => ['jpg', 'jpeg', 'png', 'webp'],
        'max_mb' => 2,
        'dimensao' => '256 × 256 px',
        'proporcao' => '1:1',
    ],
];
