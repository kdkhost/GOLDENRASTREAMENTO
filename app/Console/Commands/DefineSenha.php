<?php

namespace App\Console\Commands;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Console\Command;

class DefineSenha extends Command
{
    protected $signature = 'app:define-senha
                            {email : E-mail do usuário do painel}
                            {senha? : A nova senha; se omitir, a pergunta aparece sem ecoar na tela}
                            {--reativar : Coloca a conta como ativa}';

    protected $description = 'Definir a senha de um usuário do painel (e reativar a conta, se preciso)';

    public function handle(): int
    {
        $usuario = User::where('email', $this->argument('email'))->first();

        if (!$usuario) {
            $this->error("Nenhum usuário com o e-mail {$this->argument('email')}.");
            $this->line('Usuários existentes: ' . User::orderBy('email')->pluck('email')->implode(', '));

            return self::FAILURE;
        }

        $senha = $this->argument('senha') ?? $this->secret('Nova senha');

        if (mb_strlen((string) $senha) < 8) {
            $this->error('A senha precisa de pelo menos 8 caracteres.');

            return self::FAILURE;
        }

        $usuario->password = $senha;

        if ($this->option('reativar')) {
            $usuario->status = 'ativo';
        }

        $usuario->save();

        Activity::log('senha_redefinida', "Senha de {$usuario->email} redefinida pela linha de comando.", $usuario);

        $this->info("Senha de {$usuario->email} ({$usuario->role}) atualizada. Faça login e troque por outra dentro do painel.");

        return self::SUCCESS;
    }
}
