# Changelog

Todo o registro de mudanças notáveis deste projeto. O formato segue
[Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/) e o projeto usa Versionamento Semântico.

## [2.0.0] - 2026-10-22

### Adicionado
- **Sistema integrado CRM + CMS + ERP** no mesmo painel, com dados reais coletados do site oficial goldenrastreamento.com.br.
- **CRM** — `customers` + `interactions`: ficha de cliente (CPF/CNPJ, endereço, frota, valor anual, responsável), linha do tempo de interações (ligação, WhatsApp, e-mail, reunião, nota), próxima ação e conversão de **lead → cliente** com um clique (no funil ou automaticamente ao marcar "Vendido").
- **ERP** — `categories`, `products`, `stock_movements`, `suppliers`, `orders`, `order_items`: catálogo com preços, promoção, mensalidade, estoque com mínimo, movimentações (entrada/saída/ajuste), fornecedores (Vivo, Claro, Algar, TIM) e pedidos com status e formas de pagamento.
- **CMS** — `pages`, `testimonials`, `faqs`: páginas institucionais editáveis (com HTML sanitizado contra XSS), depoimentos e FAQs gerenciados pelo painel; conteúdo do site agora vem do banco com *fallback* para `config/golden.php`. Páginas do CMS entram automaticamente no menu do site.
- **Loja virtual pública** (`/loja`, `/loja/{slug}`, checkout e confirmação de pedido) para vender rastreadores J16, chips M2M e acessórios — **com interruptor ON/OFF no painel** (botão rápido na sidebar + seção em Configurações). Quando desligada, as rotas respondem 404 e o item some do menu.
- Checkout com validação, *honeypot*, *rate limit*, transação de banco, baixa automática de estoque e registro de auditoria.
- Helpers globais (`brl`, `loja_ativa`, `slugify_pt`) e middleware `LojaAtiva` (alias de rota `loja`).
- Seeders `CatalogSeeder`, `CmsSeeder`, `CrmSeeder`, `OrdersSeeder` com produtos, depoimentos reais (Douglas, Jonatha, Marcelle), FAQs, páginas e pedidos de demonstração.
- Dados oficiais da empresa aplicados no site (endereço, telefones, parceiros M2M, formas de pagamento) e novo bloco "Módulos integrados" no dashboard.

### Atualizado
- **Estrutura do repositório**: o projeto Laravel agora ocupa a **raiz** do repositório — as
  migrações, controllers, views e o restante do sistema foram movidos de `golden/` para a raiz
  (histórico preservado como *rename*). O `.gitignore` da raiz unificou as regras do framework
  com a lista de documentos internos, que continuam fora do versionamento.
- Sidebar do painel reorganizada em Comercial (CRM) / Vendas (ERP) / Site (CMS) / Administração + atalho de liga/desliga da loja.
- Rodapé e cabeçalho do site com endereço, telefones, cobertura de chips e pagamento reais.
- `config/golden.php`, `.env.example` e `AppServiceProvider` com os dados oficiais da Golden.
- `.env.example` ganhou o bloco completo de **SMTP/e-mail** (`MAIL_*`) documentado: em desenvolvimento o driver é `log` (nada é enviado, só registrado em `storage/logs`), e em produção basta preencher a credencial do servidor — o código de e-mail faz parte do sistema e não é removido.
- Fechar uma venda no funil (**status "Vendido"**) agora **converte o lead em cliente do CRM automaticamente** e abre a ficha do cliente, com lançamento na auditoria.
- `README.md` reescrito para cobrir os módulos CRM/ERP/CMS, a loja virtual com interruptor, as rotas novas, os seeders de demonstração e o passo `php artisan storage:link`.

## [1.0.0] - 2026-10-22

### Adicionado
- Projeto **Laravel 13** "Golden Rastreamento" criado seguindo o padrão do repositório de referência `foco7` (AdminLTE 4, auth próprio, migrations *driver-agnostic*).
- **Site multipágina** com o padrão visual aprovado (dourado + teal sobre fundo escuro): Home, Soluções, Para quem é, Planos, Como funciona, FAQ, Contato e Privacidade.
- **Painel administrativo de leads** (prioridade do briefing):
  - Dashboard com KPIs (total, novos, taxa de conversão, receita), funil visual, gráfico de 30 dias e conversão por campanha.
  - CRUD/consulta de leads com filtros (status, campanha, período, busca, responsável), ficha com mover no funil, notas e linha do tempo.
  - Exportação CSV respeitando filtros.
  - Gestão de campanhas (cadastro + criação automática por UTM) com métricas.
  - Trilha de auditoria das ações administrativas.
  - Usuários e permissões (perfis `admin`, `vendedor`, `visualizador`).
  - Configurações de contato e de medição (GA4/Pixel/GTM) com *switch* de consentimento.
- **Captura de leads** (`POST /leads`) com validação, *rate limit*, *honeypot*, preservação de UTMs/`gclid`/`fbclid`, ID de visita anônimo, IP pseudonimizado e registro de consentimento LGPD.
- Instrumentação de eventos (`page_view`, `view_plans`, `select_plan`, `contact`, `whatsapp_click`, `generate_lead`, `lead_success`).
- Migrations: `users` (role/status/telefone/avatar), `campaigns`, `leads`, `lead_status_histories`, `activities`, `settings`.
- `DatabaseSeeder` com admin, vendedor, 3 campanhas e 42 leads de demonstração.
- Configuração dupla de banco: **SQLite** para desenvolvimento offline e **MySQL** pronto para produção (mesmas migrations).
- Documentação: `README.md` (instalação dev/prod, estrutura, pendências do briefing) e este `CHANGELOG.md`.
