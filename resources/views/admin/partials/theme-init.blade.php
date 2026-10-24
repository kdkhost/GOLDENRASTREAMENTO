{{-- Tema escolhido no painel vale também para o portal do cliente: é a mesma preferência
     gravada no mesmo endereço, e este script roda antes de a tela pintar para o fundo não
     piscar claro quando o usuário trabalha no escuro. --}}
<script>(function(){var t=localStorage.getItem('golden_admin_theme')||'light';document.documentElement.setAttribute('data-bs-theme',t);})();</script>
