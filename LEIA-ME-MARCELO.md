# Golden Gestão — Landing Page v1 (prévia)
## Abrir
Abra `index.html` no Chrome ou Edge. É um site real (HTML, CSS e JS), não uma imagem. Também pode ser hospedado em ambiente de homologação HTTPS.

## Instalação para Marcelo
1. Subir os três arquivos `index.html`, `style.css` e `script.js` na pasta pública de um subdomínio de homologação (ex.: landing-teste no domínio da empresa, se autorizado). Configurar HTTPS e cache.
2. Substituir o logotipo textual provisório por arquivo OFICIAL fornecido pela Golden. A paleta é preliminar; confirmar cores oficiais.
3. Substituir ilustrações conceituais por screenshots reais autorizados da plataforma e fotos do J16.
4. Confirmar preços e inserir valores de kits e mensalidades separados, caso desejado. Não há taxa de instalação Golden: o cliente contrata o instalador diretamente.
5. No `script.js`, preencher `CONFIG.whatsapp` com número oficial em formato DDI+DDD+número, somente dígitos. Hoje está vazio e os botões exibem aviso.
6. Criar API `POST /api/leads` segura e colocar URL em `CONFIG.leadEndpoint`. Validar no servidor, limitar taxa, proteção anti-spam, logs, consentimento, armazenamento e autenticação do painel. NÃO armazenar leads somente no navegador.
7. Criar política/aviso de privacidade e banner de preferências de cookies conforme LGPD. Configurar Google Tag Manager, GA4, Google Ads e Meta Pixel respeitando consentimento e políticas. Somente após implementar consentimento e tags, habilitar `trackingEnabled` e disparos de conversão reais.
8. Preservar UTMs, gclid/fbclid quando fornecidos, junto com um identificador da visita. Evitar expor dados pessoais em URLs e plataformas de anúncios. Não considerar clique de WhatsApp uma conversa confirmada.
9. Testar desktop, Android, iOS, formulário, botões, acessibilidade, desempenho, atribuição e integração antes de publicar.

## Painel de leads Golden — diferencial previsto (NÃO implementado nesta v1)
Desenvolver aplicação ADMINISTRATIVA protegida por login e perfis, com lista de leads (nome, telefone, perfil, tamanho de frota, campanha, origem, timestamp, consentimento e status), funil (novo, contatado, proposta, vendido, perdido), filtros por campanha e período, exportação autorizada, trilha de auditoria e controles de acesso. Receber dados exclusivamente do backend. Vincular vendas confirmadas ao CRM/financeiro mediante integração posterior. Painel NÃO está ativo e esta versão NÃO captura contatos em banco de dados.

## Medição
Eventos preparados no JS: `view_plans`, `select_plan`, `contact`, `whatsapp_click`, `lead_form_ready`, `generate_lead` (apenas após sucesso HTTP no backend). `purchase` somente com confirmação de venda do sistema comercial. Os eventos são impressos localmente no console enquanto a coleta está desativada.

## Pendências para publicação
Logo oficial, capturas reais, WhatsApp oficial, preço vigente e regras de oferta, endpoint de leads, privacidade/consentimento, tags e backend do painel. Não usar como produção sem essas configurações.
