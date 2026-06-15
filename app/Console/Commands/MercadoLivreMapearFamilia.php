<?php

namespace App\Console\Commands;

use App\Models\MercadoLivreToken;
use App\Services\MercadoLivre\MercadoLivreClient;
use Illuminate\Console\Command;

class MercadoLivreMapearFamilia extends Command
{
    protected $signature = 'ml:mapear-familia
        {item : MLB ID ou MLBU ID para mapear}
        {--account=primary : Conta ML (primary/secondary)}';

    protected $description = 'Mapeia a família completa de um item ML (Family → UPs → MLBs) com status, preço, frete, catálogo';

    public function handle(): int
    {
        $input = $this->argument('item');
        $accountKey = $this->option('account');

        $client = new MercadoLivreClient($accountKey);

        if (!$client->isAuthorized()) {
            $this->error("Conta '{$accountKey}' não autorizada.");
            return 1;
        }

        $tokenModel = MercadoLivreToken::where('account_key', $accountKey)->first();
        $userId = $tokenModel?->user_id ?? config("mercadolivre.accounts.{$accountKey}.user_id");

        // 1. Determinar o user_product_id
        $this->info("=== Mapeando família a partir de: {$input} ===\n");

        if (str_starts_with(strtoupper($input), 'MLBU')) {
            $userProductId = strtoupper($input);
        } else {
            // É um MLB — buscar user_product_id
            $this->line("Buscando item {$input}...");
            $itemResult = $client->get("/items/{$input}");
            if (!$itemResult['success']) {
                $this->error("Erro ao buscar item: HTTP " . ($itemResult['http_code'] ?? '?'));
                return 1;
            }
            $userProductId = $itemResult['body']['user_product_id'] ?? null;
            if (!$userProductId) {
                $this->error("Item não possui user_product_id (modelo antigo sem UP).");
                return 1;
            }
            $this->line("  user_product_id: {$userProductId}");
        }

        // 2. Buscar User Product para pegar family_id
        sleep(1);
        $this->line("Buscando User Product {$userProductId}...");
        $upResult = $client->get("/user-products/{$userProductId}");
        if (!$upResult['success']) {
            $this->error("Erro ao buscar UP: HTTP " . ($upResult['http_code'] ?? '?'));
            return 1;
        }

        $up = $upResult['body'];
        $familyId = $up['family_id'] ?? null;
        $familyName = $up['family_name'] ?? '—';

        $this->newLine();
        $this->info("📦 Família: {$familyName}");
        $this->info("   Family ID: {$familyId}");
        $this->newLine();

        if (!$familyId) {
            $this->warn("Sem family_id. Mostrando apenas este UP.");
            $this->mostrarUP($client, $up, $userId);
            return 0;
        }

        // 3. Buscar todos os UPs da família
        sleep(1);
        $this->line("Buscando UPs da família...");
        $familyResult = $client->get("/sites/MLB/user-products-families/{$familyId}");

        $userProductIds = [];
        if ($familyResult['success']) {
            $body = $familyResult['body'];
            $userProductIds = $body['user_products_ids']
                ?? $body['user_products']
                ?? [];
            // Se veio array de objetos, extrair IDs
            if (!empty($userProductIds) && is_array($userProductIds[0] ?? null)) {
                $userProductIds = array_map(fn($up) => $up['id'] ?? $up['user_product_id'] ?? '', $userProductIds);
            }
        }

        if (empty($userProductIds)) {
            // Fallback: só mostrar o UP que temos
            $userProductIds = [$userProductId];
        }

        $this->info("   UPs na família: " . count($userProductIds));
        $this->newLine();

        // 4. Para cada UP, buscar detalhes e MLBs
        foreach ($userProductIds as $upId) {
            sleep(1);
            $upData = null;
            if ($upId === $userProductId) {
                $upData = $up; // Já temos
            } else {
                $upRes = $client->get("/user-products/{$upId}");
                $upData = $upRes['success'] ? $upRes['body'] : null;
            }

            $this->mostrarUP($client, $upData, $userId, $upId);
        }

        return 0;
    }

    private function mostrarUP(MercadoLivreClient $client, ?array $upData, ?string $userId, ?string $upId = null): void
    {
        $id = $upData['id'] ?? $upId ?? '?';
        $name = $upData['name'] ?? '—';
        $catalogProductId = $upData['catalog_product_id'] ?? null;

        // Extrair SKU e cor dos atributos
        $sku = '—';
        $cor = '—';
        foreach ($upData['attributes'] ?? [] as $attr) {
            if ($attr['id'] === 'SELLER_SKU') $sku = $attr['values'][0]['name'] ?? '—';
            if ($attr['id'] === 'COLOR') $cor = $attr['values'][0]['name'] ?? '—';
        }

        $catalogBadge = $catalogProductId ? "📦 Catálogo: {$catalogProductId}" : "❌ Sem catálogo";

        $this->line("┌─────────────────────────────────────────────────────");
        $this->line("│ 🏷️  UP: {$id}");
        $this->line("│    Nome: {$name}");
        $this->line("│    SKU: {$sku} | Cor: {$cor}");
        $this->line("│    {$catalogBadge}");
        $this->line("│");

        // Buscar MLBs deste UP
        if ($userId) {
            sleep(1);
            $searchResult = $client->get("/users/{$userId}/items/search", [
                'user_product_id' => $id,
                'limit' => 50,
            ]);

            $mlbIds = $searchResult['success'] ? ($searchResult['body']['results'] ?? []) : [];

            if (empty($mlbIds)) {
                $this->line("│    ⚠️  Nenhum MLB encontrado para este UP");
            } else {
                foreach ($mlbIds as $mlbId) {
                    sleep(1);
                    $mlbResult = $client->get("/items/{$mlbId}");
                    if (!$mlbResult['success']) {
                        $this->line("│    ├── {$mlbId} — Erro HTTP " . ($mlbResult['http_code'] ?? '?'));
                        continue;
                    }

                    $mlb = $mlbResult['body'];
                    $status = $mlb['status'] ?? '?';
                    $price = $mlb['price'] ?? 0;
                    $qty = $mlb['available_quantity'] ?? 0;
                    $listingType = $mlb['listing_type_id'] ?? '?';
                    $freeShipping = ($mlb['shipping']['free_shipping'] ?? false) ? '✅ Frete Grátis' : '❌ Sem FG';
                    $logisticType = $mlb['shipping']['logistic_type'] ?? '?';
                    $catalogListing = !empty($mlb['catalog_listing']);
                    $permalink = $mlb['permalink'] ?? '';

                    $tipoLabel = match ($listingType) {
                        'gold_pro' => '🟣 Premium',
                        'gold_special' => '🔵 Clássico',
                        default => $listingType,
                    };

                    $statusIcon = match ($status) {
                        'active' => '🟢',
                        'paused' => '🟡',
                        'closed' => '🔴',
                        default => '⚪',
                    };

                    $catalogTag = $catalogListing ? ' [CATÁLOGO]' : '';

                    $this->line("│    ├── {$statusIcon} {$mlbId} | {$tipoLabel}{$catalogTag}");
                    $this->line("│    │   Preço: R$ " . number_format($price, 2, ',', '.') . " | Estoque: {$qty} | Status: {$status}");
                    $this->line("│    │   {$freeShipping} | Logística: {$logisticType}");
                }
            }
        }

        $this->line("└─────────────────────────────────────────────────────");
        $this->newLine();
    }
}
