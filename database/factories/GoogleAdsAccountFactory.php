<?php

namespace Database\Factories;

use App\Models\GoogleAdsAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Conta de Google Ads conectada. O token é um texto qualquer que imita o formato do Google
 * (`1//04...`): o que importa no teste é o cast `encrypted` cifrar e decifrar a mesma corda,
 * não o valor dele.
 *
 * @extends Factory<GoogleAdsAccount>
 */
class GoogleAdsAccountFactory extends Factory
{
    protected $model = GoogleAdsAccount::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'customer_id' => (string) fake()->numberBetween(1000000000, 9999999999),
            'refresh_token' => '1//04'.fake()->bothify('??##??##??##??##??##??##'),
            'descriptive_name' => fake()->company().' — Google Ads',
            'currency_code' => 'BRL',
            'status' => GoogleAdsAccount::ATIVO,
            'sincronizado_em' => now(),
        ];
    }

    /** A conta caiu: token revogado, senha trocada no Google. O painel avisa, não esconde. */
    public function desconectada(): static
    {
        return $this->state(fn () => [
            'status' => GoogleAdsAccount::DESCONECTADO,
            'motivo_desconexao' => 'O Google recusou o token de atualização (OAuth_TOKEN_EXPIRED).',
        ]);
    }
}
