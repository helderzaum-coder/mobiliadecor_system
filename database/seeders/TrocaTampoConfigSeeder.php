<?php

namespace Database\Seeders;

use App\Models\TrocaTampoConfig;
use Illuminate\Database\Seeder;

class TrocaTampoConfigSeeder extends Seeder
{
    public function run(): void
    {
        $configs = [
            // === ALANA / LILIAN ===
            // Branco
            ['grupo' => 'Alana', 'cor' => 'Branco', 'tipo_tampo' => '4bocas', 'sku_produto' => 'ALANA-4B-BR', 'nome_produto' => 'Alana 4 Bocas Branco', 'sku_tampo' => 'TAMPO-4B', 'nome_tampo' => 'Tampo 4 Bocas', 'cor_tampo' => 'universal'],
            ['grupo' => 'Alana', 'cor' => 'Branco', 'tipo_tampo' => '5bocas', 'sku_produto' => 'ALANA-5B-BR', 'nome_produto' => 'Alana 5 Bocas Branco', 'sku_tampo' => 'TAMPO-5B', 'nome_tampo' => 'Tampo 5 Bocas', 'cor_tampo' => 'universal'],
            ['grupo' => 'Alana', 'cor' => 'Branco', 'tipo_tampo' => 'liso', 'sku_produto' => 'LILIAN-BR', 'nome_produto' => 'Lilian Branco', 'sku_tampo' => 'TAMPO-LISO-BR', 'nome_tampo' => 'Tampo Liso Branco', 'cor_tampo' => 'branco'],

            // Savana/Preto
            ['grupo' => 'Alana', 'cor' => 'Savana/Preto', 'tipo_tampo' => '4bocas', 'sku_produto' => 'ALANA-4B-SP', 'nome_produto' => 'Alana 4 Bocas Savana/Preto', 'sku_tampo' => 'TAMPO-4B', 'nome_tampo' => 'Tampo 4 Bocas', 'cor_tampo' => 'universal'],
            ['grupo' => 'Alana', 'cor' => 'Savana/Preto', 'tipo_tampo' => '5bocas', 'sku_produto' => 'ALANA-5B-SP', 'nome_produto' => 'Alana 5 Bocas Savana/Preto', 'sku_tampo' => 'TAMPO-5B', 'nome_tampo' => 'Tampo 5 Bocas', 'cor_tampo' => 'universal'],
            ['grupo' => 'Alana', 'cor' => 'Savana/Preto', 'tipo_tampo' => 'liso', 'sku_produto' => 'LILIAN-SP', 'nome_produto' => 'Lilian Savana/Preto', 'sku_tampo' => 'TAMPO-LISO-SV', 'nome_tampo' => 'Tampo Liso Savana', 'cor_tampo' => 'savana'],

            // Savana/Off-White
            ['grupo' => 'Alana', 'cor' => 'Savana/Off-White', 'tipo_tampo' => '4bocas', 'sku_produto' => 'ALANA-4B-SOW', 'nome_produto' => 'Alana 4 Bocas Savana/Off-White', 'sku_tampo' => 'TAMPO-4B', 'nome_tampo' => 'Tampo 4 Bocas', 'cor_tampo' => 'universal'],
            ['grupo' => 'Alana', 'cor' => 'Savana/Off-White', 'tipo_tampo' => '5bocas', 'sku_produto' => 'ALANA-5B-SOW', 'nome_produto' => 'Alana 5 Bocas Savana/Off-White', 'sku_tampo' => 'TAMPO-5B', 'nome_tampo' => 'Tampo 5 Bocas', 'cor_tampo' => 'universal'],
            ['grupo' => 'Alana', 'cor' => 'Savana/Off-White', 'tipo_tampo' => 'liso', 'sku_produto' => 'LILIAN-SOW', 'nome_produto' => 'Lilian Savana/Off-White', 'sku_tampo' => 'TAMPO-LISO-SV', 'nome_tampo' => 'Tampo Liso Savana', 'cor_tampo' => 'savana'],
        ];

        foreach ($configs as $config) {
            TrocaTampoConfig::updateOrCreate(
                ['grupo' => $config['grupo'], 'cor' => $config['cor'], 'tipo_tampo' => $config['tipo_tampo']],
                $config
            );
        }
    }
}
