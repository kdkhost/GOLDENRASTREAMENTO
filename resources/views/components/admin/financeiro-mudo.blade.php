@props([
    'colunas' => 5,
    'oQue' => null,
    'texto' => null,
])
{{-- A linha que separa "não tem" de "não consegui ler". O texto mora aqui uma vez só porque a
     frase é a mesma em todas as tabelas do financeiro, e uma view que a reescreveria teria
     chance de virar "lista vazia" — o engano que já custou atendimento. --}}
<tr>
    <td colspan="{{ $colunas }}" class="text-center text-secondary py-4 small">
        {{ $texto ?? 'O financeiro não respondeu à consulta '.($oQue ?? 'desta lista').'. A lista não está vazia — ela não pôde ser lida agora.' }}
    </td>
</tr>
