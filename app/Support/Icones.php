<?php

namespace App\Support;

class Icones
{
    /**
     * Catálogo completo de ícones curados e organizados por categorias.
     *
     * @return array<string, array{nome: string, icone: string, itens: array<int, array{codigo: string, nome: string, tags: string}>}>
     */
    public static function catalogo(): array
    {
        return [
            'alertas' => [
                'nome' => 'Alertas e Erros',
                'icone' => 'fa-triangle-exclamation',
                'itens' => [
                    ['codigo' => 'fa-triangle-exclamation', 'nome' => 'Alerta / Atenção', 'tags' => 'alerta atencao aviso perigo erro triangulo'],
                    ['codigo' => 'fa-circle-exclamation', 'nome' => 'Exclamação no Círculo', 'tags' => 'exclamacao circulo atencao erro aviso'],
                    ['codigo' => 'fa-circle-xmark', 'nome' => 'Erro / X no Círculo', 'tags' => 'erro x cancelar fechar invalido reprovado'],
                    ['codigo' => 'fa-xmark', 'nome' => 'X / Fechar', 'tags' => 'x cancelar excluir fechar negado'],
                    ['codigo' => 'fa-ban', 'nome' => 'Proibido / Bloqueado', 'tags' => 'proibido bloqueado cancelado ban impedido'],
                    ['codigo' => 'fa-shield-halved', 'nome' => 'Escudo de Segurança', 'tags' => 'escudo protecao seguranca autorizacao blindagem'],
                    ['codigo' => 'fa-lock', 'nome' => 'Cadeado Fechado / Restrito', 'tags' => 'cadeado fechado restrito seguranca senha autenticacao'],
                    ['codigo' => 'fa-lock-open', 'nome' => 'Cadeado Aberto / Liberado', 'tags' => 'cadeado aberto liberado livre desprotegido'],
                    ['codigo' => 'fa-key', 'nome' => 'Chave de Acesso', 'tags' => 'chave acesso permissao token login'],
                    ['codigo' => 'fa-hand', 'nome' => 'Mão / Pare', 'tags' => 'mao pare limite espera pausa segure'],
                    ['codigo' => 'fa-bug', 'nome' => 'Bug / Falha no Sistema', 'tags' => 'bug falha erro defeito problema inseto'],
                    ['codigo' => 'fa-bomb', 'nome' => 'Bomba / Falha Crítica', 'tags' => 'bomba explosao erro fatal perigo'],
                    ['codigo' => 'fa-radiation', 'nome' => 'Perigo / Radiação', 'tags' => 'perigo risco radiacao toxico extremo'],
                    ['codigo' => 'fa-skull-crossbones', 'nome' => 'Perigo Extremo', 'tags' => 'caveira perigo morte alerta critico'],
                    ['codigo' => 'fa-heart-crack', 'nome' => 'Sem Sinal / Desconectado', 'tags' => 'coracao quebrado sem sinal perda desconexao'],
                    ['codigo' => 'fa-clock-rotate-left', 'nome' => 'Histórico / Tempo Expirado', 'tags' => 'relogio tempo expirado sessao historico voltar'],
                    ['codigo' => 'fa-circle-pause', 'nome' => 'Pausado / Aguardando', 'tags' => 'pausa aguardando espera congelado'],
                    ['codigo' => 'fa-circle-stop', 'nome' => 'Parado / Interrompido', 'tags' => 'parado stop interrompido fim'],
                    ['codigo' => 'fa-fire', 'nome' => 'Fogo / Urgente', 'tags' => 'fogo urgente chamas incendio alerta'],
                ],
            ],
            'servidor' => [
                'nome' => 'Servidor e Rede',
                'icone' => 'fa-server',
                'itens' => [
                    ['codigo' => 'fa-server', 'nome' => 'Servidor / Host', 'tags' => 'servidor host hosting maquina rack computador'],
                    ['codigo' => 'fa-database', 'nome' => 'Banco de Dados', 'tags' => 'banco dados sql storage mysql registros'],
                    ['codigo' => 'fa-cloud', 'nome' => 'Nuvem / Cloud', 'tags' => 'nuvem cloud online hospedagem backup'],
                    ['codigo' => 'fa-network-wired', 'nome' => 'Rede Cabeada', 'tags' => 'rede cabo lan conexao internet roteador'],
                    ['codigo' => 'fa-wifi', 'nome' => 'Sinal Wi-Fi', 'tags' => 'wifi sinal internet wireless conexao antena'],
                    ['codigo' => 'fa-tower-broadcast', 'nome' => 'Torre de Transmissão', 'tags' => 'torre transmissao sinal antena 4g 5g celula'],
                    ['codigo' => 'fa-microchip', 'nome' => 'Chip / Processador', 'tags' => 'chip processador placa hardware cpu m2m'],
                    ['codigo' => 'fa-hard-drive', 'nome' => 'Disco / Armazenamento', 'tags' => 'disco hd ssd memoria armazenamento espaco'],
                    ['codigo' => 'fa-screwdriver-wrench', 'nome' => 'Manutenção / Reparo', 'tags' => 'manutencao ferramentas chave fenda conserto ajuste'],
                    ['codigo' => 'fa-gears', 'nome' => 'Engrenagens / Configurações', 'tags' => 'engrenagens configuracao ajustes sistema preferencias'],
                    ['codigo' => 'fa-gear', 'nome' => 'Configuração', 'tags' => 'engrenagem ajuste opcao ferramenta'],
                    ['codigo' => 'fa-wrench', 'nome' => 'Chave de Boca', 'tags' => 'ferramenta chave boca conserto oficina'],
                    ['codigo' => 'fa-terminal', 'nome' => 'Terminal / Shell', 'tags' => 'terminal console prompt comando script bash'],
                    ['codigo' => 'fa-code', 'nome' => 'Código / Programação', 'tags' => 'codigo api dev html script programacao'],
                    ['codigo' => 'fa-plug', 'nome' => 'Conector / Plug', 'tags' => 'plug tomada conector energia conexao cabo'],
                    ['codigo' => 'fa-power-off', 'nome' => 'Liga / Desliga', 'tags' => 'power energia ligar desligar reiniciar'],
                    ['codigo' => 'fa-bolt', 'nome' => 'Raio / Energia Rápida', 'tags' => 'raio eletrico energia rapido choque voltagem'],
                ],
            ],
            'rastreamento' => [
                'nome' => 'Rastreamento e Veículos',
                'icone' => 'fa-car',
                'itens' => [
                    ['codigo' => 'fa-compass', 'nome' => 'Bússola / Orientação', 'tags' => 'bussola direcao rota orientacao navegacao gps'],
                    ['codigo' => 'fa-location-dot', 'nome' => 'Ponto no Mapa / GPS', 'tags' => 'gps ponto mapa localizacao pino latitude'],
                    ['codigo' => 'fa-map-location-dot', 'nome' => 'Mapa com Localização', 'tags' => 'mapa rota localizacao gps rastreamento'],
                    ['codigo' => 'fa-route', 'nome' => 'Trajeto / Rota Traçada', 'tags' => 'rota trajeto caminho percurso viagem'],
                    ['codigo' => 'fa-road', 'nome' => 'Estrada / Rodovia', 'tags' => 'estrada rodovia pista asfalto caminho'],
                    ['codigo' => 'fa-satellite', 'nome' => 'Satélite em Órbita', 'tags' => 'satelite orbita espaco rastreamento telemetria'],
                    ['codigo' => 'fa-satellite-dish', 'nome' => 'Antena Parabólica', 'tags' => 'antena parabolica sinal satelite comunicacao'],
                    ['codigo' => 'fa-sim-card', 'nome' => 'Chip M2M / Conectividade', 'tags' => 'chip sim m2m cartao dados multioperadora'],
                    ['codigo' => 'fa-car', 'nome' => 'Carro Particular', 'tags' => 'carro automovel veiculo passeio particular'],
                    ['codigo' => 'fa-truck', 'nome' => 'Caminhão de Carga', 'tags' => 'caminhao carga pesado transporte carreta'],
                    ['codigo' => 'fa-van-shuttle', 'nome' => 'Van / Frotas', 'tags' => 'van utilitario frota entrega furgão'],
                    ['codigo' => 'fa-truck-fast', 'nome' => 'Entrega Rápida / SEDEX', 'tags' => 'entrega rapida correios sedex frete envio'],
                    ['codigo' => 'fa-motorcycle', 'nome' => 'Moto / Motocicleta', 'tags' => 'moto motocicleta motoboy duas rodas'],
                    ['codigo' => 'fa-tractor', 'nome' => 'Trator / Operação Rural', 'tags' => 'trator maquina agricola campo rural fazenda'],
                    ['codigo' => 'fa-taxi', 'nome' => 'Táxi / Aplicativo', 'tags' => 'taxi uber app motorista passageiro'],
                    ['codigo' => 'fa-bus', 'nome' => 'Ônibus / Coletivo', 'tags' => 'onibus transporte coletivo passageiros viagem'],
                    ['codigo' => 'fa-gauge-high', 'nome' => 'Velocímetro / Telemetria', 'tags' => 'velocimetro telemetria velocidade medidor painel'],
                    ['codigo' => 'fa-crosshairs', 'nome' => 'Mira / Precisão', 'tags' => 'mira precisao localizacao exata radar'],
                ],
            ],
            'comunicacao' => [
                'nome' => 'Comunicação e Suporte',
                'icone' => 'fa-headset',
                'itens' => [
                    ['codigo' => 'fa-headset', 'nome' => 'Central de Atendimento', 'tags' => 'suporte atendimento headset fone sac central'],
                    ['codigo' => 'fa-phone', 'nome' => 'Telefone', 'tags' => 'telefone chamada ligacao contato voz fixo'],
                    ['codigo' => 'fa-envelope', 'nome' => 'E-mail / Correio', 'tags' => 'email correio mensagem envelope contato'],
                    ['codigo' => 'fa-comments', 'nome' => 'Chat / Conversa', 'tags' => 'chat conversa mensagens balao discussao'],
                    ['codigo' => 'fa-comment', 'nome' => 'Balão de Mensagem', 'tags' => 'mensagem balao fala comentario chat'],
                    ['codigo' => 'fa-message', 'nome' => 'Texto / SMS', 'tags' => 'mensagem texto sms aviso celular'],
                    ['codigo' => 'fa-robot', 'nome' => 'Atendente Virtual / IA', 'tags' => 'robo bot ia assistente artificial automacao'],
                    ['codigo' => 'fa-user', 'nome' => 'Usuário / Pessoa', 'tags' => 'usuario pessoa perfil cliente motorista'],
                    ['codigo' => 'fa-users', 'nome' => 'Equipe / Grupo', 'tags' => 'equipe time usuarios pessoas grupo clientes'],
                    ['codigo' => 'fa-circle-user', 'nome' => 'Avatar / Perfil', 'tags' => 'perfil avatar conta usuario foto'],
                    ['codigo' => 'fa-id-card', 'nome' => 'Crachá / Identificação', 'tags' => 'documento cracha identificacao cadastro carteira'],
                    ['codigo' => 'fa-bullhorn', 'nome' => 'Megafone / Anúncio', 'tags' => 'megafone anuncio aviso novidade propaganda'],
                    ['codigo' => 'fa-circle-info', 'nome' => 'Informação / Ajuda', 'tags' => 'informacao ajuda dica saiba mais sobre'],
                    ['codigo' => 'fa-circle-question', 'nome' => 'Dúvida / Pergunta', 'tags' => 'duvida pergunta faq interrogacao como funciona'],
                    ['codigo' => 'fa-brands fa-whatsapp', 'nome' => 'WhatsApp Oficial', 'tags' => 'whatsapp zap conversa canal contato'],
                    ['codigo' => 'fa-brands fa-telegram', 'nome' => 'Telegram Bot', 'tags' => 'telegram bot canal alerta notificacao'],
                ],
            ],
            'comercio' => [
                'nome' => 'Comércio e Pedidos',
                'icone' => 'fa-cart-shopping',
                'itens' => [
                    ['codigo' => 'fa-cart-shopping', 'nome' => 'Carrinho de Compras', 'tags' => 'carrinho compras loja pedido checkout ecommerce'],
                    ['codigo' => 'fa-basket-shopping', 'nome' => 'Cesta de Produtos', 'tags' => 'cesta sacola compras produtos loja'],
                    ['codigo' => 'fa-bag-shopping', 'nome' => 'Sacola de Compras', 'tags' => 'sacola compras bolsa loja varejo'],
                    ['codigo' => 'fa-box', 'nome' => 'Pacote / Encomenda', 'tags' => 'pacote encomenda caixa correios produto mercadoria'],
                    ['codigo' => 'fa-boxes-stacked', 'nome' => 'Estoque / Caixas', 'tags' => 'estoque caixas armazem inventario deposito'],
                    ['codigo' => 'fa-credit-card', 'nome' => 'Cartão de Crédito', 'tags' => 'cartao credito debito pagamento checkout'],
                    ['codigo' => 'fa-money-bill-wave', 'nome' => 'Dinheiro / Pagamento', 'tags' => 'dinheiro nota pagamento pix valor reais'],
                    ['codigo' => 'fa-receipt', 'nome' => 'Comprovante / Fatura', 'tags' => 'recibo fatura nota fiscal comprovante pagamento'],
                    ['codigo' => 'fa-barcode', 'nome' => 'Código de Barras / Boleto', 'tags' => 'codigo barras boleto leitura scanner'],
                    ['codigo' => 'fa-tag', 'nome' => 'Etiqueta / Preço', 'tags' => 'etiqueta preco desconto promocao oferta'],
                    ['codigo' => 'fa-tags', 'nome' => 'Categorias / Tags', 'tags' => 'etiquetas tags categorias filtros produtos'],
                    ['codigo' => 'fa-store', 'nome' => 'Loja Física / Retirada', 'tags' => 'loja empresa filial presencial retirada sede'],
                    ['codigo' => 'fa-percent', 'nome' => 'Porcentagem / Desconto', 'tags' => 'porcentagem desconto promocao oferta taxa'],
                ],
            ],
            'gerais' => [
                'nome' => 'Status e Interface',
                'icone' => 'fa-icons',
                'itens' => [
                    ['codigo' => 'fa-circle-check', 'nome' => 'Sucesso / Concluído', 'tags' => 'sucesso ok correto confirmado ativo aprovado'],
                    ['codigo' => 'fa-check', 'nome' => 'Check / Verificado', 'tags' => 'check verificado certo pronto'],
                    ['codigo' => 'fa-star', 'nome' => 'Estrela / Destaque', 'tags' => 'estrela favorito destaque avaliacao qualidade'],
                    ['codigo' => 'fa-thumbs-up', 'nome' => 'Curtir / Aprovado', 'tags' => 'positivo legal curtir aprovado confiavel'],
                    ['codigo' => 'fa-eye', 'nome' => 'Visualizar / Olho', 'tags' => 'olho ver visualizar exibir previsualizar'],
                    ['codigo' => 'fa-arrow-rotate-right', 'nome' => 'Recarregar / Atualizar', 'tags' => 'recarregar atualizar refresh girar sincronizar'],
                    ['codigo' => 'fa-house', 'nome' => 'Início / Home', 'tags' => 'casa home inicio principal voltar'],
                    ['codigo' => 'fa-globe', 'nome' => 'Globo / Site Público', 'tags' => 'globo mundo site web internet publico'],
                    ['codigo' => 'fa-link', 'nome' => 'Link / Vínculo', 'tags' => 'link conexao url endereco vinculo'],
                    ['codigo' => 'fa-link-slash', 'nome' => 'Link Quebrado', 'tags' => 'link quebrado desconectado erro url sem acesso'],
                    ['codigo' => 'fa-magnifying-glass', 'nome' => 'Lupa / Pesquisa', 'tags' => 'lupa busca pesquisa procurar encontrar'],
                    ['codigo' => 'fa-filter', 'nome' => 'Filtro / Refinar', 'tags' => 'filtro refinar selecionar buscar funil'],
                    ['codigo' => 'fa-arrow-left', 'nome' => 'Seta para Esquerda', 'tags' => 'seta esquerda voltar anterior retorno'],
                    ['codigo' => 'fa-arrow-right', 'nome' => 'Seta para Direita', 'tags' => 'seta direita proximo avancar continuar'],
                    ['codigo' => 'fa-pen-to-square', 'nome' => 'Editar / Caneta', 'tags' => 'editar modificar escrever formulario caneta'],
                    ['codigo' => 'fa-trash-can', 'nome' => 'Lixeira / Excluir', 'tags' => 'lixeira excluir deletar remover apagar'],
                ],
            ],
        ];
    }

    /**
     * Mapa plano de todos os códigos com nome amigável e categoria.
     *
     * @return array<string, array{codigo: string, nome: string, tags: string, categoria: string}>
     */
    public static function todos(): array
    {
        $mapa = [];
        foreach (self::catalogo() as $slugCat => $cat) {
            foreach ($cat['itens'] as $item) {
                $mapa[$item['codigo']] = [
                    'codigo' => $item['codigo'],
                    'nome' => $item['nome'],
                    'tags' => $item['tags'],
                    'categoria' => $slugCat,
                ];
            }
        }

        return $mapa;
    }

    /**
     * Retorna o nome amigável em português para um código de ícone.
     */
    public static function nomeAmigavel(?string $codigo): string
    {
        if ($codigo === null || trim($codigo) === '') {
            return 'Nenhum ícone';
        }

        $codigoLimpo = self::normalizarCodigo($codigo);
        $todos = self::todos();

        if (isset($todos[$codigoLimpo])) {
            return $todos[$codigoLimpo]['nome'];
        }

        $semPrefixo = preg_replace('/^(fa-(solid|regular|brands)\s+|fas\s+|far\s+|fab\s+|fa-)/', '', $codigoLimpo);

        return ucwords(str_replace('-', ' ', $semPrefixo));
    }

    /**
     * Normaliza e garante que a classe CSS do FontAwesome sempre seja válida e completa.
     */
    public static function classeCompleta(?string $codigo): string
    {
        if ($codigo === null || trim($codigo) === '') {
            return 'fa-solid fa-circle-question';
        }

        $c = trim($codigo);

        // Se já tiver classe de estilo FontAwesome explícita
        if (str_starts_with($c, 'fa-solid ') || str_starts_with($c, 'fa-regular ') || str_starts_with($c, 'fa-brands ')) {
            return $c;
        }

        if (str_starts_with($c, 'fas ') || str_starts_with($c, 'far ') || str_starts_with($c, 'fab ')) {
            return $c;
        }

        // Casos de marcas conhecidas
        if (str_contains($c, 'whatsapp') || str_contains($c, 'telegram') || str_contains($c, 'google') || str_contains($c, 'facebook') || str_contains($c, 'instagram')) {
            $base = str_starts_with($c, 'fa-') ? $c : 'fa-'.$c;

            return 'fa-brands '.$base;
        }

        // Padrão: fa-solid
        $base = str_starts_with($c, 'fa-') ? $c : 'fa-'.$c;

        return 'fa-solid '.$base;
    }

    /**
     * Normaliza a chave para busca no catálogo.
     */
    public static function normalizarCodigo(string $codigo): string
    {
        $c = trim($codigo);
        if (str_starts_with($c, 'fa-brands ') || str_starts_with($c, 'fab ')) {
            $nome = preg_replace('/^(fa-brands\s+|fab\s+)/', '', $c);

            return 'fa-brands '.(str_starts_with($nome, 'fa-') ? $nome : 'fa-'.$nome);
        }

        $nome = preg_replace('/^(fa-(solid|regular)\s+|fas\s+|far\s+)/', '', $c);

        return str_starts_with($nome, 'fa-') ? $nome : 'fa-'.$nome;
    }
}
