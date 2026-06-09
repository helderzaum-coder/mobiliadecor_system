<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class Faq extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-question-mark-circle';
    protected static ?string $navigationGroup = 'Ajuda';
    protected static ?string $navigationLabel = 'FAQ';
    protected static ?string $title = 'FAQ - Perguntas Frequentes';
    protected static string $view = 'filament.pages.faq';
    protected static ?int $navigationSort = 99;

    public static function canAccess(): bool
    {
        return true; // Acessível a todos os usuários autenticados
    }

    public function getSections(): array
    {
        return $this->getFaqData();
    }

    private function getFaqData(): array
    {
        return [
            [
                'slug' => 'dashboard-vendas',
                'title' => 'Dashboard Vendas',
                'icon' => 'heroicon-o-chart-bar',
                'questions' => [
                    [
                        'question' => 'Quais filtros estão disponíveis no Dashboard de Vendas?',
                        'answer' => '<p>O Dashboard de Vendas oferece os seguintes filtros para refinar a visualização dos pedidos:</p><ul><li><strong>Período</strong>: permite selecionar o intervalo de datas. Opções disponíveis:<ul><li>Hoje</li><li>Esta semana</li><li>Este mês</li><li>Mês passado</li><li>Selecionar mês (escolha um mês específico dos últimos 12 meses)</li><li>Customizado (defina data de início e fim manualmente)</li></ul></li><li><strong>Canal de venda</strong>: filtra por marketplace ou canal específico (ex: Shopee, Mercado Livre, Magalu, MadeiraMadeira, Webcontinental)</li><li><strong>Conta Bling</strong>: filtra por conta do ERP (Enterprise Resource Planning) Bling — Mobilia Decor ou HES Móveis</li><li><strong>Status</strong>: filtra por status de completude do pedido (veja pergunta sobre status abaixo)</li><li><strong>Busca por número de pedido</strong>: pesquisa direta pelo número do pedido no canal. Quando utilizada, o filtro de período é ignorado para encontrar o pedido em qualquer data</li></ul><p><strong>Dica:</strong> Os filtros são combináveis. Você pode, por exemplo, filtrar por canal "Shopee" + status "Falta Planilha" para ver apenas pedidos Shopee pendentes de planilha.</p>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Quais são as ações em lote disponíveis e o que cada uma faz?',
                        'answer' => '<p>As ações em lote processam múltiplos pedidos do período filtrado de uma só vez. Elas são executadas em segundo plano (jobs assíncronos) e você receberá uma notificação ao concluir:</p><ul><li><strong>Buscar NF-e (Nota Fiscal Eletrônica)</strong>: busca automaticamente as notas fiscais no Bling para todos os pedidos do período que ainda não possuem NF-e vinculada. O sistema consulta a API do Bling e vincula a chave de acesso, número e valor da nota ao pedido.</li><li><strong>Buscar CT-e (Conhecimento de Transporte Eletrônico)</strong>: busca os CT-es para pedidos que já possuem NF-e mas ainda não têm frete lançado. O CT-e contém o valor do frete cobrado pela transportadora. Pedidos com frete ME2/FULL ou Shopee Xpress são ignorados pois o frete é gerenciado pelo marketplace.</li><li><strong>Buscar Custos</strong>: consulta o custo dos produtos no Bling para pedidos que ainda não possuem custo de produto registrado. Atualiza o custo e recalcula as margens automaticamente.</li><li><strong>Aplicar Planilha Shopee</strong>: tenta vincular dados financeiros da planilha Shopee (previamente importada) aos pedidos Shopee do período que ainda não foram processados. Necessário ter importado a planilha antes na página "Importar Planilha Shopee".</li></ul><p><strong>Pré-requisito:</strong> As ações em lote dependem da integração com o Bling estar ativa e autorizada. Verifique o status na página "Integração Bling".</p>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Quais são as ações individuais disponíveis para cada pedido?',
                        'answer' => '<p>Cada pedido na listagem possui um menu de ações individuais. As ações disponíveis são:</p><ul><li><strong>Recalcular margens</strong>: recalcula a margem de contribuição e o lucro do pedido com base nos dados atuais (custo, frete, comissão, impostos). Use quando algum dado foi atualizado manualmente.</li><li><strong>Buscar NF-e por número</strong>: permite informar manualmente o número da nota fiscal para buscar no Bling. Útil quando a busca automática não encontra a NF-e (ex: nota emitida com número diferente do pedido).</li><li><strong>Lançar frete manual</strong>: permite registrar o valor do frete e a transportadora manualmente. Informe o valor em reais e opcionalmente o nome da transportadora. Após o lançamento, as margens são recalculadas automaticamente.</li><li><strong>Marcar frete Envias</strong>: zera o valor do frete do pedido (frete cobrado e frete pago = R$ 0,00) e marca como frete pago. Usado para pedidos onde o frete é gerenciado pela plataforma Envias e não gera custo para a empresa.</li><li><strong>Marcar aguardando envio</strong>: define uma data prevista de envio para o pedido. Pedidos marcados como aguardando envio aparecem no filtro de status correspondente e geram conta a receber quando completos.</li></ul>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Como cancelar um pedido com estorno?',
                        'answer' => '<p>Para cancelar um pedido com estorno:</p><ol><li>Localize o pedido na listagem do Dashboard</li><li>Clique no menu de ações do pedido (ícone de três pontos)</li><li>Selecione <strong>"Cancelar com estorno"</strong></li><li>Confirme a data do estorno (padrão: hoje)</li></ol><p>O sistema irá:</p><ul><li>Marcar o pedido como cancelado</li><li>Gerar uma conta a pagar com o valor do estorno (valor do repasse que será devolvido ao marketplace)</li><li>Marcar a conta a receber correspondente com estorno pendente</li><li>Atualizar o status no staging do Bling para "cancelado"</li></ul><p>⚠️ <strong>Aviso:</strong> Esta ação é irreversível. O cancelamento com estorno não pode ser desfeito. O valor do estorno será registrado como conta a pagar pendente no financeiro. Certifique-se de que o pedido realmente foi cancelado no marketplace antes de executar esta ação.</p>',
                        'destructive' => true,
                    ],
                    [
                        'question' => 'Como registrar um reembolso?',
                        'answer' => '<p>Para registrar um reembolso de pedido:</p><ol><li>Localize o pedido na listagem do Dashboard</li><li>Clique no menu de ações do pedido</li><li>Selecione <strong>"Registrar reembolso"</strong></li><li>Confirme a data do reembolso</li></ol><p>O sistema irá:</p><ul><li>Gerar uma conta a pagar com o valor do reembolso (valor que o marketplace debitará)</li><li>Marcar a conta a receber correspondente com estorno pendente</li><li>Marcar o pedido como cancelado</li></ul><p>⚠️ <strong>Aviso:</strong> Esta ação é irreversível. O reembolso não pode ser desfeito. Use esta opção quando o marketplace já processou o reembolso ao cliente e irá debitar o valor do seu repasse. A diferença para o "cancelar com estorno" é que o reembolso é usado quando o marketplace já efetuou a devolução ao comprador.</p>',
                        'destructive' => true,
                    ],
                    [
                        'question' => 'O que significam os status de filtro dos pedidos?',
                        'answer' => '<p>Os status indicam a situação de completude de cada pedido. Um pedido é considerado "completo" quando possui todas as informações necessárias para cálculo correto da margem:</p><ul><li><strong>⚠ Falta NF-e (Nota Fiscal Eletrônica)</strong>: pedido sem nota fiscal vinculada. A NF-e é necessária para vincular o CT-e e confirmar o valor da venda.</li><li><strong>🚚 Falta Frete</strong>: pedido com frete pendente. Não possui CT-e (Conhecimento de Transporte Eletrônico) nem frete manual lançado. Exclui pedidos ME2/FULL e Shopee Xpress onde o frete é do marketplace.</li><li><strong>📊 Falta Planilha</strong>: pedido de marketplace que ainda não teve a planilha financeira do canal aplicada. A planilha contém dados de comissão e taxas reais cobradas.</li><li><strong>👥 Falta Afiliado Shopee</strong>: pedido Shopee sem dados de comissão de afiliado processados.</li><li><strong>💰 Sem Custo Produto</strong>: pedido sem custo de produto registrado. O custo é necessário para calcular a margem real.</li><li><strong>📦 Aguardando Envio</strong>: pedidos com data prevista de envio definida, aguardando despacho.</li><li><strong>📦 ME2/FULL</strong>: pedidos do Mercado Livre com frete gerenciado pelo marketplace (Mercado Envios 2 ou Fulfillment). O frete não gera custo direto para o vendedor.</li><li><strong>🚚 Shopee Xpress</strong>: pedidos Shopee com frete gerenciado pela Shopee (frete = R$ 0,00 para o vendedor).</li><li><strong>🚚 Envias</strong>: pedidos com frete gerenciado pela plataforma Envias/CNova (frete zerado).</li><li><strong>❌ Incompleto</strong>: pedidos que possuem pelo menos uma pendência (falta NF-e, frete, planilha ou custo).</li><li><strong>✅ Completo</strong>: pedidos com todas as informações preenchidas e margem calculada corretamente.</li><li><strong>🚫 Cancelados/Estornos</strong>: pedidos que foram cancelados ou tiveram estorno/reembolso registrado.</li></ul>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Como funciona a exportação CSV (Comma-Separated Values) e quais colunas são exportadas?',
                        'answer' => '<p>A exportação gera um arquivo CSV (Comma-Separated Values) com todos os pedidos do período e filtros aplicados. O arquivo usa separador ponto-e-vírgula (;) e codificação UTF-8 com BOM para compatibilidade com Excel.</p><p><strong>Colunas exportadas:</strong></p><ul><li><strong>Pedido</strong>: número do pedido no canal de venda</li><li><strong>Data</strong>: data da venda (formato dd/mm/aaaa)</li><li><strong>Canal</strong>: nome do canal/marketplace</li><li><strong>Conta</strong>: conta Bling (Mobilia Decor ou HES Móveis)</li><li><strong>Cliente</strong>: nome do cliente</li><li><strong>Total Pedido</strong>: valor total da venda</li><li><strong>Subtotal Produtos</strong>: soma dos valores dos produtos</li><li><strong>Custo Produtos</strong>: custo total dos produtos vendidos</li><li><strong>Comissão</strong>: comissão cobrada pelo marketplace</li><li><strong>Imposto</strong>: valor de impostos</li><li><strong>Frete Cobrado</strong>: valor do frete cobrado do cliente</li><li><strong>Frete Pago</strong>: valor do frete pago à transportadora</li><li><strong>Lucro Final</strong>: margem de venda total em reais</li><li><strong>Margem %</strong>: margem de contribuição percentual</li><li><strong>NF-e Número, Chave, Valor</strong>: dados da nota fiscal</li><li><strong>Frete Lançado</strong>: indica se o frete foi registrado (Sim/Não)</li><li><strong>CTe Número, Transportadora, Valor</strong>: dados do conhecimento de transporte</li><li><strong>Transportadora Manual</strong>: nome da transportadora quando lançado manualmente</li><li><strong>Planilha Canal</strong>: se a planilha do marketplace foi aplicada (Sim/Não/N/A)</li><li><strong>Planilha Afiliado</strong>: se a planilha de afiliado Shopee foi aplicada (Sim/Não/N/A)</li><li><strong>Custo Lançado</strong>: se o custo do produto está registrado (Sim/Não)</li><li><strong>Completo</strong>: se o pedido está com todas as informações (Sim/Não)</li><li><strong>Cancelado</strong>: se o pedido foi cancelado (Sim/Não)</li></ul>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'O que é a margem de contribuição e como ela é calculada?',
                        'answer' => '<p>A margem de contribuição indica o percentual de lucro real de cada pedido após descontar todos os custos variáveis. O cálculo é:</p><p><strong>Lucro = Total Pedido - Custo Produtos - Comissão - Impostos - Frete Pago</strong></p><p><strong>Margem % = (Lucro / Total Pedido) × 100</strong></p><p>Para que a margem seja calculada corretamente, o pedido precisa ter:</p><ul><li>NF-e vinculada (confirma valor da venda)</li><li>Custo dos produtos registrado</li><li>Frete lançado (CT-e, manual ou zerado para ME2/FULL/Envias)</li><li>Planilha do canal aplicada (para marketplaces que fornecem dados de comissão via planilha)</li></ul><p>Pedidos incompletos podem apresentar margem incorreta. Use a ação "Recalcular margens" após completar as informações pendentes.</p>',
                        'destructive' => false,
                    ],
                ],
            ],
            [
                'slug' => 'caixa',
                'title' => 'Caixa',
                'icon' => 'heroicon-o-banknotes',
                'questions' => [
                    [
                        'question' => 'Quais filtros estão disponíveis na página Caixa e como utilizá-los?',
                        'answer' => '<p>A página Caixa (Fluxo de Caixa) oferece os seguintes filtros para controlar a visualização das movimentações financeiras:</p><ul><li><strong>Período</strong>: define o intervalo de datas. Opções disponíveis:<ul><li>Este mês (padrão)</li><li>Mês passado</li><li>Selecionar mês (escolha um mês específico dos últimos 12 meses)</li><li>Customizado (defina data de início e fim manualmente)</li></ul></li><li><strong>Conta bancária</strong>: filtra movimentações de uma conta específica. Quando selecionado "Todos", exibe movimentações de todas as contas ativas.</li><li><strong>Categoria financeira</strong>: filtra por categoria (ex: Vendas, Fornecedores, Impostos, Frete). Quando selecionado "Todas", exibe todas as categorias.</li><li><strong>Visão</strong>: alterna entre visão diária (📅) e visão por categoria (📊).</li><li><strong>Exibir saldo anterior</strong>: toggle que controla se o saldo anterior ao período é considerado no cálculo do saldo acumulado.</li></ul><p>Os filtros são combináveis e a página atualiza automaticamente ao alterar qualquer filtro.</p>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Qual a diferença entre a visão diária e a visão por categoria?',
                        'answer' => '<p>A página Caixa oferece duas formas de visualizar as movimentações:</p><p><strong>📅 Visão Diária:</strong></p><ul><li>Agrupa todas as movimentações por dia</li><li>Exibe para cada dia: total de entradas, total de saídas e saldo do dia</li><li>Mostra o <strong>saldo acumulado</strong> ao longo do período (saldo anterior + entradas - saídas até aquele dia)</li><li>Permite ver o fluxo de caixa dia a dia e identificar dias com saldo negativo</li><li>Cada movimentação individual é listada dentro do seu respectivo dia</li></ul><p><strong>📊 Visão por Categoria:</strong></p><ul><li>Agrupa todas as movimentações por categoria financeira</li><li>Exibe para cada categoria: total de entradas, total de saídas e saldo da categoria</li><li>Mostra a quantidade de movimentações por categoria</li><li>Permite identificar quais categorias geram mais receita ou despesa</li><li>Útil para análise de composição de custos e receitas</li></ul>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Como funciona o saldo anterior e por que ele é importante?',
                        'answer' => '<p>O <strong>saldo anterior</strong> representa o valor acumulado em caixa antes do início do período selecionado. Ele é calculado como:</p><p><strong>Saldo Anterior = Saldo Inicial das Contas + Todas as Entradas antes do período - Todas as Saídas antes do período</strong></p><p>Detalhamento:</p><ul><li><strong>Saldo inicial das contas</strong>: valor configurado em cada conta bancária como saldo de abertura</li><li><strong>Entradas anteriores</strong>: soma de todos os recebimentos com status "recebido" e data de recebimento anterior ao período</li><li><strong>Saídas anteriores</strong>: soma de todos os pagamentos com status "pago" e data de pagamento anterior ao período</li></ul><p>Quando o filtro de conta bancária está ativo, o saldo anterior considera apenas as movimentações e o saldo inicial daquela conta específica.</p><p><strong>Por que é importante:</strong> O saldo anterior permite que o saldo acumulado na visão diária reflita o valor real disponível em caixa, não apenas o resultado do período isolado. Desative o toggle "Exibir saldo anterior" se quiser analisar apenas o resultado do período selecionado.</p>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Como realizar uma transferência entre contas bancárias?',
                        'answer' => '<p>Para realizar uma transferência entre contas:</p><ol><li>Clique no botão <strong>"Transferência"</strong> no topo da página (ícone de setas)</li><li>Selecione a <strong>conta de origem</strong> (de onde o dinheiro sairá)</li><li>Selecione a <strong>conta de destino</strong> (para onde o dinheiro irá). A conta de origem é automaticamente excluída das opções de destino.</li><li>Informe o <strong>valor</strong> da transferência (mínimo R$ 0,01)</li><li>Informe a <strong>data</strong> da transferência (padrão: hoje)</li><li>Opcionalmente, adicione uma <strong>descrição</strong> (ex: "Transferência para pagar fornecedor")</li><li>Confirme a operação</li></ol><p>O sistema gera automaticamente:</p><ul><li>Uma <strong>saída</strong> (conta a pagar com status "pago") na conta de origem com a descrição "↗ [descrição]"</li><li>Uma <strong>entrada</strong> (conta a receber com status "recebido") na conta de destino com a descrição "↙ [descrição]"</li></ul><p>Ambos os lançamentos são marcados como "lançamento manual" e utilizam a forma de pagamento "Transferência".</p>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'O que são entradas e saídas no fluxo de caixa?',
                        'answer' => '<p>No fluxo de caixa, as movimentações são classificadas em dois tipos:</p><p><strong>Entradas (recebimentos):</strong></p><ul><li>São registros de <strong>contas a receber com status "recebido"</strong> e data de recebimento dentro do período</li><li>Incluem: repasses de marketplaces, recebimentos de vendas diretas, transferências recebidas, lotes de recebimento</li><li>Recebimentos agrupados em lote aparecem como uma única linha com o nome do lote</li><li>Recebimentos individuais mostram o número do pedido ou a descrição do lançamento</li></ul><p><strong>Saídas (pagamentos):</strong></p><ul><li>São registros de <strong>contas a pagar com status "pago"</strong> e data de pagamento dentro do período</li><li>Incluem: pagamentos a fornecedores, fretes, impostos, estornos, transferências enviadas, despesas operacionais</li><li>Cada saída mostra a descrição (observações ou número da fatura) e a categoria financeira</li></ul><p><strong>Resultado do período</strong> = Total de Entradas - Total de Saídas</p><p><strong>Saldo Final</strong> = Saldo Anterior + Resultado do período</p>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Como interpretar o saldo acumulado na visão diária?',
                        'answer' => '<p>O saldo acumulado na visão diária mostra a evolução do caixa ao longo do período:</p><ul><li>O primeiro dia começa com o <strong>saldo anterior</strong> (se ativado) somado ao resultado do dia</li><li>Cada dia subsequente acumula o resultado (entradas - saídas) sobre o saldo do dia anterior</li><li>O saldo acumulado do último dia é igual ao <strong>saldo final</strong> exibido nos totais</li></ul><p><strong>Exemplo:</strong></p><ul><li>Saldo anterior: R$ 10.000,00</li><li>Dia 01: +R$ 5.000 (entradas) - R$ 3.000 (saídas) = Saldo acumulado R$ 12.000,00</li><li>Dia 02: +R$ 2.000 (entradas) - R$ 1.000 (saídas) = Saldo acumulado R$ 13.000,00</li></ul><p>Se o saldo acumulado ficar negativo em algum dia, isso indica que as saídas superaram as entradas até aquele ponto, sinalizando necessidade de atenção ao fluxo de caixa.</p>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Quem pode acessar a página Caixa?',
                        'answer' => '<p>A página Caixa é restrita a usuários com perfil <strong>administrador (admin)</strong>. Apenas administradores podem visualizar o fluxo de caixa e realizar transferências entre contas.</p><p>Isso se deve à natureza sensível das informações financeiras consolidadas. Se você precisa de acesso e não possui perfil admin, solicite ao administrador do sistema.</p><p><strong>Dependência:</strong> Os dados exibidos no Caixa dependem dos lançamentos feitos em outras páginas do sistema (Recebimentos, Lote Recebimentos, Dashboard Vendas). Certifique-se de que os recebimentos e pagamentos estão sendo registrados corretamente para que o fluxo de caixa reflita a realidade.</p>',
                        'destructive' => false,
                    ],
                ],
            ],
            [
                'slug' => 'bling-integration',
                'title' => 'Integração Bling',
                'icon' => 'heroicon-o-link',
                'questions' => [
                    [
                        'question' => 'Como funciona a autenticação OAuth (Open Authorization) com o Bling?',
                        'answer' => '<p>A integração com o Bling (ERP - Enterprise Resource Planning) utiliza autenticação OAuth (Open Authorization), um protocolo seguro que permite ao sistema acessar dados do Bling sem armazenar sua senha:</p><ol><li>Acesse a página de Integração Bling no menu "Integrações"</li><li>Clique em "Autorizar" na conta desejada para iniciar o fluxo OAuth</li><li>Você será redirecionado para o site do Bling para conceder permissão</li><li>Após autorizar, o sistema armazena o token de acesso automaticamente</li><li>O status mudará para "Autorizado" indicando que a conexão está ativa</li></ol><p>O sistema suporta múltiplas contas Bling simultaneamente, cada uma com seu próprio token de acesso independente.</p>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Como gerenciar múltiplas contas Bling?',
                        'answer' => '<p>O sistema permite conectar várias contas Bling ao mesmo tempo. Cada conta é configurada independentemente:</p><ul><li><strong>Identificação</strong>: cada conta possui um nome e uma chave única para diferenciação</li><li><strong>Autorização independente</strong>: cada conta tem seu próprio token OAuth, podendo ser autorizada ou revogada separadamente</li><li><strong>Status individual</strong>: o status de autorização é exibido por conta, permitindo identificar rapidamente qual conta precisa de atenção</li></ul><p>As contas são configuradas no arquivo de configuração do sistema e aparecem automaticamente na página de integração.</p>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Como verificar o status de autorização de cada conta?',
                        'answer' => '<p>Na página de Integração Bling, cada conta exibe informações de status:</p><ul><li><strong>Status de autorização</strong>: indica se o token está válido ("Autorizado") ou expirado/inválido ("Não autorizado")</li><li><strong>Nome da conta</strong>: identificação da conta Bling vinculada</li><li><strong>Botão de ação</strong>: "Autorizar" para contas não conectadas ou "Reautorizar" para renovar tokens expirados</li></ul><p>Recomenda-se verificar o status periodicamente para garantir que a sincronização está funcionando corretamente.</p>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'O que fazer quando o token do Bling expira?',
                        'answer' => '<p>Quando o token expira, o sistema não consegue mais sincronizar dados com o Bling. Para resolver:</p><ol><li>Acesse a página de Integração Bling no menu "Integrações"</li><li>Verifique o status da autorização — indicará "Não autorizado" ou "Expirado"</li><li>Clique em "Reautorizar" para iniciar um novo fluxo OAuth</li><li>Conceda permissão novamente no site do Bling</li><li>Aguarde a confirmação de que o novo token foi salvo</li></ol><p>⚠️ <strong>Aviso:</strong> Enquanto o token estiver expirado, nenhuma sincronização será realizada automaticamente. Pedidos e estoque não serão atualizados até a reautorização.</p>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'A sincronização com o Bling falhou. O que fazer?',
                        'answer' => '<p>Se a sincronização com o Bling não está funcionando, verifique os seguintes pontos:</p><ol><li><strong>Token válido</strong>: confirme que o status da conta está "Autorizado" na página de integração</li><li><strong>Conexão com internet</strong>: verifique se o servidor tem acesso à API (Application Programming Interface) do Bling</li><li><strong>Limites da API</strong>: o Bling possui limites de requisições por minuto — aguarde alguns minutos e tente novamente</li><li><strong>Dados corretos</strong>: verifique se os produtos/pedidos existem na conta Bling correta</li></ol><p>Se o problema persistir após essas verificações, tente reautorizar a conta e aguarde a próxima sincronização automática.</p>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Pedidos ou produtos do Bling não aparecem no sistema. Como resolver?',
                        'answer' => '<p>Se dados do Bling não estão aparecendo no sistema, considere as seguintes causas:</p><ul><li><strong>Conta errada</strong>: verifique se está consultando a conta Bling correta (o sistema suporta múltiplas contas)</li><li><strong>Token expirado</strong>: um token inválido impede a busca de novos dados — reautorize se necessário</li><li><strong>Período de sincronização</strong>: dados recém-criados no Bling podem levar alguns minutos para aparecer no sistema</li><li><strong>Filtros ativos</strong>: verifique se há filtros aplicados na página que possam estar ocultando os dados</li><li><strong>Status do pedido no Bling</strong>: apenas pedidos com determinados status são importados pelo sistema</li></ul><p>Caso nenhuma das opções acima resolva, utilize o comando de sincronização manual ou entre em contato com o administrador.</p>',
                        'destructive' => false,
                    ],
                ],
            ],
            [
                'slug' => 'mercado-livre-integration',
                'title' => 'Integração Mercado Livre',
                'icon' => 'heroicon-o-shopping-bag',
                'questions' => [
                    [
                        'question' => 'Como funciona a autenticação OAuth (Open Authorization) com o Mercado Livre?',
                        'answer' => '<p>A integração com o Mercado Livre utiliza autenticação OAuth (Open Authorization), permitindo que o sistema acesse dados da sua conta de forma segura:</p><ol><li>Acesse a página de Integração Mercado Livre no menu "Integrações"</li><li>Clique em "Autorizar" na conta desejada</li><li>Você será redirecionado para o Mercado Livre para fazer login e conceder permissão</li><li>Após autorizar, o sistema armazena o token de acesso e o refresh token</li><li>O status mudará para "Autorizado" com a data de expiração do token visível</li></ol><p>O sistema suporta múltiplas contas do Mercado Livre simultaneamente, cada uma com credenciais independentes.</p>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Como gerenciar múltiplas contas do Mercado Livre?',
                        'answer' => '<p>O sistema permite conectar várias contas do Mercado Livre. Cada conta exibe:</p><ul><li><strong>Nome da conta</strong>: identificação configurada para cada conta</li><li><strong>Status de autorização</strong>: indica se a conexão está ativa</li><li><strong>User ID</strong>: identificador único da conta no Mercado Livre, usado para vincular pedidos e operações</li><li><strong>Data de expiração do token</strong>: indica quando o token atual irá expirar e precisará ser renovado</li></ul><p>Cada conta opera de forma independente, permitindo gerenciar múltiplas lojas ou CNPJs no mesmo sistema.</p>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'O que significa a data de expiração do token e o user_id?',
                        'answer' => '<p>Na página de integração, cada conta exibe duas informações importantes:</p><ul><li><strong>Data de expiração do token</strong>: indica até quando o token de acesso é válido. Após essa data, o sistema tentará renovar automaticamente usando o refresh token. Se a renovação falhar, será necessário reautorizar manualmente</li><li><strong>User ID</strong>: é o identificador numérico único da sua conta no Mercado Livre. Ele é usado internamente pelo sistema para vincular pedidos, anúncios e operações financeiras à conta correta</li></ul><p>Monitore a data de expiração para garantir que a integração permaneça ativa.</p>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'O token do Mercado Livre expirou. Como renovar?',
                        'answer' => '<p>O token do Mercado Livre tem validade limitada. Quando expira:</p><ol><li>O sistema tenta renovar automaticamente usando o refresh token</li><li>Se a renovação automática falhar, o status mudará para "Não autorizado"</li><li>Acesse a página de Integração Mercado Livre</li><li>Clique em "Reautorizar" na conta afetada</li><li>Faça login novamente no Mercado Livre e conceda permissão</li></ol><p>⚠️ <strong>Aviso:</strong> Enquanto o token estiver expirado e a renovação automática falhar, pedidos novos do Mercado Livre não serão importados e dados financeiros não serão atualizados.</p>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'A sincronização de pedidos do Mercado Livre falhou. O que verificar?',
                        'answer' => '<p>Se pedidos do Mercado Livre não estão sendo sincronizados, verifique:</p><ol><li><strong>Status do token</strong>: confirme que a conta está "Autorizada" e o token não expirou</li><li><strong>User ID correto</strong>: verifique se o user_id exibido corresponde à conta esperada</li><li><strong>Limites da API</strong>: o Mercado Livre possui limites de requisições — aguarde e tente novamente</li><li><strong>Webhook ativo</strong>: notificações de novos pedidos dependem do webhook estar configurado corretamente</li><li><strong>Status do pedido</strong>: apenas pedidos com pagamento confirmado são importados</li></ol><p>Se o problema persistir, tente reautorizar a conta e verifique os logs do sistema para mensagens de erro específicas.</p>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Pedidos ou dados financeiros do Mercado Livre não aparecem. Como resolver?',
                        'answer' => '<p>Se dados do Mercado Livre não estão visíveis no sistema:</p><ul><li><strong>Conta correta</strong>: com múltiplas contas, verifique se está consultando a conta certa (confira o user_id)</li><li><strong>Token válido</strong>: um token expirado impede a importação de novos dados</li><li><strong>Período de processamento</strong>: pedidos recentes podem levar alguns minutos para serem processados</li><li><strong>Planilha financeira</strong>: dados financeiros detalhados requerem importação da planilha do Mercado Livre na página "Importar Planilha ML"</li><li><strong>Filtros aplicados</strong>: verifique se filtros de período ou canal estão ocultando os dados no Dashboard</li></ul><p>Para dados financeiros específicos (comissões, taxas), é necessário importar a planilha financeira do Mercado Livre separadamente.</p>',
                        'destructive' => false,
                    ],
                ],
            ],
            [
                'slug' => 'shopee-integration',
                'title' => 'Integração Shopee',
                'icon' => 'heroicon-o-shopping-bag',
                'questions' => [
                    [
                        'question' => 'Como funciona a autenticação OAuth (Open Authorization) com a Shopee?',
                        'answer' => '<p>A integração com a Shopee utiliza autenticação OAuth (Open Authorization) via redirecionamento:</p><ol><li>Acesse a página de Integração Shopee no menu "Integrações"</li><li>Clique no botão "Conectar" para iniciar o fluxo de autorização</li><li>Você será redirecionado para a página da Shopee para conceder permissão</li><li>Após autorizar, o sistema recebe e armazena o token de acesso</li><li>O status mudará para "Autorizado" indicando que a conexão está ativa</li></ol><p>A autorização é feita via redirecionamento do navegador — você será levado ao site da Shopee e retornará automaticamente ao sistema após conceder permissão.</p>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Como testar a conexão com a Shopee e ver o nome da loja?',
                        'answer' => '<p>Após autorizar a integração, você pode verificar se a conexão está funcionando:</p><ol><li>Na página de Integração Shopee, clique no botão "Testar Conexão"</li><li>O sistema fará uma chamada à API (Application Programming Interface) da Shopee para obter informações da loja</li><li>Se a conexão for bem-sucedida, será exibida uma notificação com o nome da loja cadastrada na Shopee</li><li>Se houver erro, uma mensagem indicará o problema encontrado</li></ol><p>O teste de conexão é útil para confirmar que o token está válido e que a comunicação com a Shopee está funcionando corretamente.</p>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Qual a diferença entre modo sandbox e modo produção na Shopee?',
                        'answer' => '<p>A integração Shopee pode operar em dois modos:</p><ul><li><strong>Modo Sandbox (teste)</strong>: conecta ao ambiente de testes da Shopee. Usado para desenvolvimento e validação sem afetar dados reais. Pedidos e operações neste modo não são reais</li><li><strong>Modo Produção</strong>: conecta ao ambiente real da Shopee. Todas as operações afetam dados reais de pedidos, estoque e financeiro</li></ul><p>O modo atual é indicado na página de integração. A troca entre modos é feita na configuração do sistema e requer reautorização.</p><p>⚠️ <strong>Aviso:</strong> Certifique-se de estar no modo correto antes de realizar operações. Ações no modo produção afetam dados reais e não podem ser desfeitas pela integração.</p>',
                        'destructive' => true,
                    ],
                    [
                        'question' => 'O token da Shopee expirou ou a conexão falhou. Como resolver?',
                        'answer' => '<p>Se o token da Shopee expirou ou a conexão não está funcionando:</p><ol><li>Acesse a página de Integração Shopee</li><li>Verifique o status — se indicar "Não autorizado", o token expirou</li><li>Clique em "Conectar" para iniciar um novo fluxo de autorização OAuth</li><li>Autorize novamente no site da Shopee</li><li>Após retornar ao sistema, use "Testar Conexão" para confirmar que está funcionando</li></ol><p>⚠️ <strong>Aviso:</strong> Enquanto o token estiver expirado, pedidos da Shopee não serão sincronizados e dados financeiros não serão atualizados automaticamente.</p>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'A sincronização de pedidos da Shopee não está funcionando. O que fazer?',
                        'answer' => '<p>Se pedidos da Shopee não estão sendo importados para o sistema:</p><ol><li><strong>Verifique o token</strong>: use "Testar Conexão" para confirmar que a autorização está válida</li><li><strong>Modo correto</strong>: confirme que está no modo produção (sandbox não importa pedidos reais)</li><li><strong>Shop ID</strong>: verifique se o Shop ID exibido corresponde à loja correta</li><li><strong>Limites da API</strong>: a Shopee possui limites de requisições — aguarde alguns minutos</li><li><strong>Status do pedido</strong>: apenas pedidos com determinados status são importados pelo sistema</li></ol><p>Se o problema persistir após essas verificações, tente desconectar e reconectar a integração.</p>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Pedidos ou dados da Shopee não aparecem no sistema. Como resolver?',
                        'answer' => '<p>Se dados da Shopee não estão visíveis no sistema, considere:</p><ul><li><strong>Token válido</strong>: um token expirado impede a importação de novos dados — reconecte se necessário</li><li><strong>Modo sandbox vs produção</strong>: no modo sandbox, apenas dados de teste são exibidos</li><li><strong>Período de processamento</strong>: pedidos recentes podem levar alguns minutos para aparecer</li><li><strong>Planilha financeira</strong>: dados financeiros detalhados (comissões, taxas) requerem importação da planilha Shopee na página "Importar Planilha Shopee"</li><li><strong>Filtros no Dashboard</strong>: verifique se filtros de canal ou período estão ocultando os pedidos da Shopee</li></ul><p>Para dados financeiros completos, importe a planilha da Shopee periodicamente através da página dedicada de importação.</p>',
                        'destructive' => false,
                    ],
                ],
            ],
            [
                'slug' => 'conciliacao-madeira-madeira',
                'title' => 'Conciliação Madeira Madeira',
                'icon' => 'heroicon-o-clipboard-document-check',
                'questions' => [
                    [
                        'question' => 'Como funciona o repasse da Madeira Madeira?',
                        'answer' => '<p>O repasse da Madeira Madeira é <strong>parcelado</strong> de acordo com a quantidade de parcelas que o cliente pagou na compra. Se o cliente pagou em 3x, você receberá o repasse em 3 parcelas. A exceção é quando você solicita antecipação — nesse caso o valor total é creditado de uma vez (com desconto de taxa de antecipação).</p><p><strong>Exemplo:</strong></p><ul><li>Venda de R$ 600,00 — cliente pagou em 3x</li><li>Repasse total (descontada comissão): R$ 510,00</li><li>Você receberá: 3 parcelas de R$ 170,00</li></ul>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Como conciliar o repasse parcelado da Madeira Madeira no sistema?',
                        'answer' => '<p>Para conciliar repasses parcelados da Madeira Madeira, use a funcionalidade de <strong>Baixa Parcial</strong> nas Contas a Receber:</p><ol><li>Acesse <strong>Financeiro → Contas a Receber</strong></li><li>Busque o pedido pelo número (ex: 9642183)</li><li>Remova o filtro "Status: Pendente" se necessário para ver todas as parcelas</li><li>Clique no botão <strong>"Baixa Parcial"</strong> (ícone de tesoura ✂️)</li><li>Informe a <strong>quantidade total de parcelas</strong> do repasse (ex: 3)</li><li>Informe a <strong>data do 1º recebimento</strong></li><li>Confirme</li></ol><p>O sistema irá:</p><ul><li>Dividir o valor total pelo número de parcelas</li><li>Marcar a 1ª parcela como recebida</li><li>Criar um registro pendente com o valor restante (parcelas 2 a N)</li></ul>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Como dar baixa nas parcelas seguintes?',
                        'answer' => '<p>Quando receber a próxima parcela do repasse:</p><ol><li>Acesse <strong>Financeiro → Contas a Receber</strong> e busque o pedido</li><li>Localize o registro <strong>pendente</strong> (mostrará algo como "parcelas 2-3")</li><li>Você tem duas opções:<ul><li><strong>Baixa Parcial novamente</strong>: se ainda faltam parcelas. Informe o número de parcelas restantes</li><li><strong>Recebido (baixa total)</strong>: se recebeu todo o restante de uma vez (antecipação)</li></ul></li></ol><p><strong>Exemplo com 3 parcelas:</strong></p><ul><li>1º mês: Baixa Parcial (3 parcelas) → R$ 170 recebido, R$ 340 pendente</li><li>2º mês: Baixa Parcial (2 parcelas) no restante → R$ 170 recebido, R$ 170 pendente</li><li>3º mês: "Recebido" no restante → R$ 170 recebido, tudo quitado</li></ul>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'E se eu antecipar o repasse na Madeira Madeira?',
                        'answer' => '<p>Se você solicitou antecipação e recebeu o valor total de uma vez:</p><ol><li>Busque o pedido em <strong>Contas a Receber</strong></li><li>Clique em <strong>"Recebido"</strong> (baixa total) no registro pendente</li><li>Informe a data do recebimento</li></ol><p>O valor completo será marcado como recebido. Se houve desconto de taxa de antecipação, você pode ajustar o valor manualmente antes de confirmar, ou lançar a diferença como conta a pagar (despesa de antecipação).</p>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Como identificar pedidos Madeira Madeira com repasse pendente?',
                        'answer' => '<p>Para ver todos os repasses pendentes da Madeira Madeira:</p><ol><li>Acesse <strong>Financeiro → Contas a Receber</strong></li><li>No filtro de <strong>Status</strong>, selecione "Pendente"</li><li>No filtro de <strong>Canal</strong>, selecione "Madeira Madeira"</li></ol><p>A listagem mostrará todas as contas pendentes. As colunas <strong>"Parcela"</strong> e <strong>"Já Recebido"</strong> indicam:</p><ul><li><strong>Parcela</strong>: ex "2/3" = parcela 2 de 3 total</li><li><strong>Já Recebido</strong>: valor total já recebido daquela venda (em verde)</li></ul><p>Se o campo parcela estiver vazio (sem badge), significa que o repasse é integral (1 parcela única).</p>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Como funciona o frete "Madeira Envios"?',
                        'answer' => '<p>Alguns pedidos da Madeira Madeira usam o <strong>"Madeira Envios"</strong> (etiqueta do marketplace), onde o frete é gerenciado e pago pelo marketplace. Funciona igual ao "Envias" e "ME2/FULL":</p><ul><li>O frete cobrado do cliente fica <strong>zerado</strong></li><li>O custo do frete para a empresa é <strong>zero</strong></li><li>O frete é marcado como <strong>pago</strong></li></ul><p>Para marcar um pedido como Madeira Envios:</p><ol><li>No <strong>Dashboard de Vendas</strong>, localize o pedido</li><li>Clique no botão <strong>"📦 Madeira Envios"</strong></li><li>Confirme a ação</li></ol><p>O sistema zerará os campos de frete e recalculará as margens automaticamente.</p>',
                        'destructive' => false,
                    ],
                ],
            ],
            [
                'slug' => 'importar-planilha-madeiramadeira',
                'title' => 'Importar Planilha MadeiraMadeira',
                'icon' => 'heroicon-o-document-arrow-up',
                'questions' => [
                    [
                        'question' => 'Qual o propósito da importação de planilha MadeiraMadeira?',
                        'answer' => '<p>A importação da planilha MadeiraMadeira tem como objetivo vincular os dados financeiros do marketplace aos pedidos cadastrados no sistema. Isso permite o cálculo correto das margens de lucro, considerando comissões e taxas cobradas pela MadeiraMadeira em cada venda.</p>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Qual o formato esperado do arquivo para importação MadeiraMadeira?',
                        'answer' => '<p>O arquivo deve estar no formato CSV (Comma-Separated Values — valores separados por vírgula). A planilha deve ser exportada diretamente do painel da MadeiraMadeira, mantendo a estrutura original de colunas sem alterações manuais.</p><p><strong>Dica:</strong> Certifique-se de que o arquivo possui a extensão <code>.csv</code> e não foi convertido para outro formato antes do upload.</p>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Quais são os resultados possíveis após a importação MadeiraMadeira?',
                        'answer' => '<p>Após processar a planilha, o sistema exibe um resumo com os seguintes indicadores:</p><ul><li><strong>Processados</strong>: quantidade de registros vinculados com sucesso aos pedidos do sistema</li><li><strong>Erros</strong>: registros que não puderam ser processados por problemas no formato ou dados inválidos</li></ul><p>Se nenhum registro for processado, o sistema exibe um alerta de aviso para que você verifique o arquivo enviado.</p>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'O que fazer quando a importação MadeiraMadeira apresenta muitos erros?',
                        'answer' => '<p>Se a importação apresentar muitos erros, verifique os seguintes pontos:</p><ol><li><strong>Formato do arquivo</strong>: confirme que o arquivo é CSV e foi exportado diretamente do painel MadeiraMadeira sem edições manuais</li><li><strong>Codificação</strong>: o arquivo deve estar em UTF-8; abrir e salvar no Excel pode alterar a codificação</li><li><strong>Pedidos existentes</strong>: os pedidos referenciados na planilha precisam existir no sistema (já importados via integração Bling)</li></ol><p>Se o problema persistir, tente exportar a planilha novamente do painel MadeiraMadeira.</p>',
                        'destructive' => false,
                    ],
                ],
            ],
            [
                'slug' => 'importar-planilha-magalu',
                'title' => 'Importar Planilha Magalu',
                'icon' => 'heroicon-o-document-arrow-up',
                'questions' => [
                    [
                        'question' => 'Qual o propósito da importação de planilha Magalu?',
                        'answer' => '<p>A importação da planilha Magalu vincula os dados financeiros do marketplace aos pedidos do sistema, permitindo o cálculo preciso das margens de lucro. Com essa importação, o sistema consegue considerar comissões, taxas e repasses do Magalu para cada pedido vendido.</p>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Qual o formato esperado do arquivo para importação Magalu?',
                        'answer' => '<p>O arquivo deve estar no formato XLSX (Microsoft Excel). A planilha deve ser exportada diretamente do portal do Magalu (seller center), mantendo a estrutura original de colunas.</p><p><strong>Formatos aceitos:</strong></p><ul><li><code>.xlsx</code> — formato principal recomendado</li><li><code>.xls</code> — formato Excel legado também aceito</li></ul><p><strong>Dica:</strong> Não renomeie ou altere as colunas da planilha antes de importar.</p>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Quais são os resultados possíveis após a importação Magalu?',
                        'answer' => '<p>Após processar a planilha, o sistema exibe um resumo com os seguintes indicadores:</p><ul><li><strong>Atualizados</strong>: quantidade de pedidos cujos dados financeiros foram vinculados com sucesso</li><li><strong>Não encontrados</strong>: pedidos presentes na planilha que não foram localizados no sistema (podem ainda não ter sido sincronizados via Bling)</li><li><strong>Erros</strong>: registros que não puderam ser processados por problemas no formato ou dados inconsistentes</li></ul>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'O que fazer quando pedidos aparecem como "não encontrados" na importação Magalu?',
                        'answer' => '<p>Pedidos "não encontrados" significam que o sistema não localizou o pedido correspondente. Possíveis causas:</p><ol><li><strong>Pedido não sincronizado</strong>: o pedido pode ainda não ter sido importado do Bling. Aguarde a próxima sincronização ou force uma busca manual no Dashboard de Vendas</li><li><strong>Número divergente</strong>: verifique se o número do pedido na planilha corresponde ao formato usado no sistema</li><li><strong>Pedido cancelado</strong>: pedidos cancelados podem não estar disponíveis para vinculação</li></ol><p><strong>Pré-requisito:</strong> Os pedidos devem estar cadastrados no sistema (via integração Bling) antes de importar a planilha financeira.</p>',
                        'destructive' => false,
                    ],
                ],
            ],
            [
                'slug' => 'importar-planilha-ml',
                'title' => 'Importar Planilha Mercado Livre',
                'icon' => 'heroicon-o-document-arrow-up',
                'questions' => [
                    [
                        'question' => 'Qual o propósito da importação de planilha Mercado Livre?',
                        'answer' => '<p>A importação da planilha Mercado Livre vincula os dados financeiros (rebates, comissões e taxas) do marketplace aos pedidos do sistema. Isso é essencial para o cálculo correto das margens de lucro, pois o Mercado Livre aplica diferentes percentuais de comissão e pode oferecer rebates (devoluções parciais de comissão) em determinadas vendas.</p>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Qual o formato esperado do arquivo e como selecionar a conta?',
                        'answer' => '<p>O arquivo pode estar nos seguintes formatos:</p><ul><li><strong>XLSX</strong> (Microsoft Excel) — formato recomendado</li><li><strong>XLS</strong> — formato Excel legado</li><li><strong>CSV</strong> (Comma-Separated Values — valores separados por vírgula)</li></ul><p>Além do arquivo, é necessário selecionar a <strong>conta Mercado Livre</strong> correspondente antes de processar:</p><ul><li><strong>Mobilia Decor</strong> — conta principal</li><li><strong>HES Móveis</strong> — conta secundária</li></ul><p><strong>Importante:</strong> Selecione a conta correta antes de importar. A planilha será vinculada apenas aos pedidos da conta selecionada.</p>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Quais são os resultados possíveis após a importação Mercado Livre?',
                        'answer' => '<p>Após processar a planilha, o sistema exibe um resumo com os seguintes indicadores:</p><ul><li><strong>Com rebate</strong>: pedidos processados que possuem rebate (devolução parcial de comissão) aplicado</li><li><strong>Sem rebate</strong>: pedidos processados sem rebate identificado na planilha</li><li><strong>Não encontrados</strong>: pedidos presentes na planilha que não foram localizados no sistema</li><li><strong>Erros</strong>: registros que não puderam ser processados por problemas no formato ou dados inconsistentes</li></ul>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'O que fazer quando a planilha Mercado Livre não processa nenhum registro?',
                        'answer' => '<p>Se nenhum registro for processado, verifique:</p><ol><li><strong>Conta selecionada</strong>: confirme que a conta escolhida (Mobilia Decor ou HES Móveis) corresponde à planilha exportada</li><li><strong>Formato do arquivo</strong>: certifique-se de que o arquivo está em XLSX, XLS ou CSV e foi exportado do painel do Mercado Livre</li><li><strong>Pedidos no sistema</strong>: os pedidos precisam existir no sistema antes da importação financeira (sincronizados via integração Bling)</li><li><strong>Arquivo expirado</strong>: se o upload foi feito há muito tempo, o arquivo temporário pode ter expirado. Faça o upload novamente</li></ol>',
                        'destructive' => false,
                    ],
                ],
            ],
            [
                'slug' => 'importar-planilha-shopee',
                'title' => 'Importar Planilha Shopee',
                'icon' => 'heroicon-o-document-arrow-up',
                'questions' => [
                    [
                        'question' => 'Qual o propósito da importação de planilha Shopee?',
                        'answer' => '<p>A importação da planilha Shopee vincula os dados financeiros do marketplace aos pedidos do sistema, permitindo o cálculo das margens de lucro. Além da importação padrão, a página também oferece a funcionalidade de corrigir dados do Bling (ERP - Enterprise Resource Planning) com base na planilha Shopee, atualizando informações como SKU (Stock Keeping Unit — código único de identificação do produto) e valores.</p>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Qual o formato esperado do arquivo para importação Shopee?',
                        'answer' => '<p>O arquivo pode estar nos seguintes formatos:</p><ul><li><strong>XLSX</strong> (Microsoft Excel) — formato recomendado</li><li><strong>XLS</strong> — formato Excel legado</li><li><strong>CSV</strong> (Comma-Separated Values — valores separados por vírgula)</li></ul><p>A planilha deve ser exportada diretamente do painel Shopee Seller Center, na seção de relatórios financeiros. Não altere a estrutura de colunas antes de importar.</p>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Quais são os resultados possíveis após a importação Shopee?',
                        'answer' => '<p>Após processar a planilha, o sistema exibe um resumo com os seguintes indicadores:</p><ul><li><strong>Processados</strong>: quantidade de registros vinculados com sucesso aos pedidos do sistema</li><li><strong>Não encontrados</strong>: pedidos presentes na planilha que não foram localizados no sistema</li><li><strong>Erros</strong>: registros que não puderam ser processados por problemas no formato ou dados inconsistentes</li></ul>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'O que fazem os botões "Corrigir dados Bling" e "Reprocessar dados Bling"?',
                        'answer' => '<p>A página de importação Shopee oferece funcionalidades adicionais além da importação padrão:</p><ul><li><strong>Corrigir dados Bling</strong>: utiliza a planilha Shopee para corrigir dados dos pedidos no Bling que estejam incompletos ou incorretos. Pula registros já corrigidos anteriormente</li><li><strong>Reprocessar dados Bling</strong>: mesma funcionalidade de correção, porém força o reprocessamento de todos os registros, incluindo os já corrigidos anteriormente</li></ul><p>Ambas as ações exibem um resumo com: corrigidos, já corrigidos (pulados), não encontrados no staging e erros.</p>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'O que fazer quando pedidos Shopee aparecem como "não encontrados"?',
                        'answer' => '<p>Pedidos "não encontrados" indicam que o sistema não localizou o pedido correspondente. Verifique:</p><ol><li><strong>Sincronização pendente</strong>: o pedido pode não ter sido importado do Bling ainda. Verifique no Dashboard de Vendas se o pedido existe</li><li><strong>Número do pedido</strong>: a Shopee usa um formato específico de número de pedido. Confirme que a planilha contém os números corretos</li><li><strong>Período da planilha</strong>: certifique-se de que a planilha exportada cobre o período dos pedidos que deseja vincular</li></ol><p><strong>Pré-requisito:</strong> Os pedidos devem estar cadastrados no sistema (via integração Bling) antes de importar a planilha financeira da Shopee.</p>',
                        'destructive' => false,
                    ],
                ],
            ],
            [
                'slug' => 'importar-planilha-webcontinental',
                'title' => 'Importar Planilha Webcontinental',
                'icon' => 'heroicon-o-document-arrow-up',
                'questions' => [
                    [
                        'question' => 'Qual o propósito da importação de planilha Webcontinental?',
                        'answer' => '<p>A importação da planilha Webcontinental vincula os dados financeiros do marketplace aos pedidos do sistema, permitindo o cálculo correto das margens de lucro. O sistema processa comissões e taxas cobradas pela Webcontinental e verifica possíveis divergências nos valores de comissão.</p>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Qual o formato esperado do arquivo para importação Webcontinental?',
                        'answer' => '<p>O arquivo pode estar nos seguintes formatos:</p><ul><li><strong>XLSX</strong> (Microsoft Excel) — formato recomendado</li><li><strong>XLS</strong> — formato Excel legado</li><li><strong>CSV</strong> (Comma-Separated Values — valores separados por vírgula)</li></ul><p>A planilha deve ser exportada diretamente do portal Webcontinental, mantendo a estrutura original de colunas sem alterações.</p>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Quais são os resultados possíveis após a importação Webcontinental?',
                        'answer' => '<p>Após processar a planilha, o sistema exibe um resumo com os seguintes indicadores:</p><ul><li><strong>Processados</strong>: quantidade de registros vinculados com sucesso aos pedidos do sistema</li><li><strong>Já processados</strong>: registros que já haviam sido importados anteriormente (são ignorados para evitar duplicidade)</li><li><strong>Com divergência</strong>: registros onde o valor de comissão na planilha difere do valor esperado pelo sistema</li><li><strong>Não encontrados</strong>: pedidos presentes na planilha que não foram localizados no sistema</li><li><strong>Erros</strong>: registros que não puderam ser processados por problemas no formato ou dados inconsistentes</li></ul>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'O que significa "com divergência de comissão" na importação Webcontinental?',
                        'answer' => '<p>O indicador "com divergência de comissão" aparece quando o valor de comissão informado na planilha da Webcontinental difere do valor que o sistema esperava para aquele pedido. Isso pode ocorrer por:</p><ol><li><strong>Alteração de taxa</strong>: a Webcontinental pode ter aplicado uma taxa diferente da cadastrada no sistema</li><li><strong>Promoção ou campanha</strong>: taxas promocionais temporárias podem gerar divergência</li><li><strong>Atualização pendente</strong>: o percentual de comissão no sistema pode estar desatualizado</li></ol><p>Quando houver divergências, o sistema exibe detalhes adicionais para que você possa verificar caso a caso.</p>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'O que fazer quando a importação Webcontinental apresenta erros?',
                        'answer' => '<p>Se a importação apresentar erros, verifique os seguintes pontos:</p><ol><li><strong>Formato do arquivo</strong>: confirme que o arquivo está em XLSX, XLS ou CSV e foi exportado diretamente do portal Webcontinental</li><li><strong>Estrutura da planilha</strong>: não altere, remova ou renomeie colunas da planilha original</li><li><strong>Pedidos no sistema</strong>: os pedidos referenciados precisam existir no sistema (importados via integração Bling)</li><li><strong>Arquivo expirado</strong>: se o upload foi feito há muito tempo, o arquivo temporário pode ter expirado. Faça o upload novamente</li></ol><p>O sistema exibe detalhes dos primeiros 10 erros encontrados para facilitar a identificação do problema.</p>',
                        'destructive' => false,
                    ],
                ],
            ],
            [
                'slug' => 'calculadora-compras',
                'title' => 'Calculadora de Compras',
                'icon' => 'heroicon-o-calculator',
                'questions' => [
                    [
                        'question' => 'O que é a Calculadora de Compras e para que serve?',
                        'answer' => '<p>A Calculadora de Compras é uma ferramenta que ajuda a identificar os melhores dias para realizar compras com fornecedores, evitando que os vencimentos das parcelas caiam em datas bloqueadas (dia 20 e 6º dia útil de cada mês). O objetivo é otimizar o fluxo de caixa evitando concentração de pagamentos em datas críticas.</p><p><strong>Funcionalidades disponíveis:</strong></p><ul><li>Seleção do mês de referência (mês atual + próximos 5 meses)</li><li>Visualização de calendário com indicação de dias seguros e bloqueados para compra</li><li>Cálculo automático dos vencimentos para prazos de 14, 28 e 42 dias</li><li>Identificação de dias bloqueados: dia 20 (vencimento de impostos) e 6º dia útil (pagamento de funcionários)</li><li>Indicação visual de fins de semana</li></ul>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Como interpretar o calendário de compras?',
                        'answer' => '<p>O calendário exibe cada dia do mês de referência com as seguintes informações:</p><ul><li><strong>Dias verdes</strong>: dias seguros para compra — nenhum vencimento (14d, 28d ou 42d) cairá em data bloqueada</li><li><strong>Dias vermelhos/bloqueados</strong>: dias em que pelo menos um vencimento cairá no dia 20 ou no 6º dia útil. O motivo é exibido (ex: "Dia 20 (28d)" significa que o vencimento de 28 dias cairá no dia 20)</li><li><strong>Dias cinza</strong>: fins de semana (sábado e domingo)</li></ul><p>Para cada dia, são exibidas as datas de vencimento calculadas para os três prazos (14, 28 e 42 dias), permitindo verificar individualmente qual prazo causa o conflito.</p>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'O que são as datas bloqueadas e por que evitá-las?',
                        'answer' => '<p>As datas bloqueadas são dias em que há concentração de pagamentos obrigatórios:</p><ul><li><strong>Dia 20 de cada mês</strong>: data comum de vencimento de impostos (DAS, ICMS, etc.)</li><li><strong>6º dia útil de cada mês</strong>: data de pagamento de salários e encargos trabalhistas</li></ul><p>Evitar que parcelas de fornecedores vençam nessas datas ajuda a distribuir melhor os pagamentos ao longo do mês, evitando picos de saída de caixa que podem comprometer a liquidez da empresa.</p><p><strong>Nota:</strong> O cálculo do 6º dia útil considera feriados nacionais fixos (Confraternização, Tiradentes, Trabalho, Independência, N.S. Aparecida, Finados, Proclamação e Natal).</p>',
                        'destructive' => false,
                    ],
                ],
            ],
            [
                'slug' => 'calculadora-ml',
                'title' => 'Calculadora Marketplace',
                'icon' => 'heroicon-o-calculator',
                'questions' => [
                    [
                        'question' => 'O que é a Calculadora Marketplace e para que serve?',
                        'answer' => '<p>A Calculadora Marketplace é uma ferramenta para simular preços de venda e calcular margens de lucro em marketplaces como ML (Mercado Livre) e Shopee. Ela considera comissões, frete, impostos e custo do produto para determinar o preço ideal ou a margem resultante.</p><p><strong>Funcionalidades disponíveis:</strong></p><ul><li>Cálculo de margem: informe o preço de venda e descubra a margem resultante</li><li>Cálculo de preço ideal: informe a margem desejada e descubra o preço de venda necessário</li><li>Suporte a múltiplos marketplaces: ML (Mercado Livre) e Shopee</li><li>Tipos de anúncio: Clássico, Premium e outros</li><li>Cálculo automático de frete baseado em peso e tabela do marketplace</li><li>Suporte a comissão manual (override) e frete manual</li><li>Simulação com múltiplas unidades (quantidade)</li></ul>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Como calcular a margem de um produto no Mercado Livre?',
                        'answer' => '<p>Para calcular a margem de um produto no ML (Mercado Livre):</p><ol><li>Selecione o marketplace <strong>"Mercado Livre"</strong></li><li>Selecione o modo <strong>"Calcular Margem"</strong></li><li>Informe o <strong>custo do produto</strong> (valor de compra unitário)</li><li>Informe o <strong>preço de venda</strong> desejado</li><li>Selecione o <strong>tipo de anúncio</strong> (Clássico ou Premium — cada um tem comissão diferente)</li><li>Informe o <strong>peso unitário</strong> em kg (usado para calcular o frete ME2)</li><li>Opcionalmente, informe o <strong>percentual de imposto</strong></li><li>Clique em <strong>"Calcular"</strong></li></ol><p>O resultado exibirá: comissão do ML (Mercado Livre), custo de frete estimado, impostos, lucro em reais e margem percentual.</p><p><strong>Dica:</strong> Use o modo "Preço Ideal" para descobrir por quanto vender para atingir uma margem específica.</p>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Como funciona o cálculo de frete na calculadora?',
                        'answer' => '<p>O frete é calculado automaticamente com base na tabela oficial do marketplace:</p><ul><li><strong>ML (Mercado Livre)</strong>: utiliza a tabela de frete ME2 (Mercado Envios 2) que varia por faixa de peso e faixa de preço. O peso total é calculado como peso unitário × quantidade.</li><li><strong>Shopee</strong>: utiliza a tabela de frete Shopee Xpress com faixas de peso e comissão fixa por faixa de preço.</li></ul><p>Você pode sobrescrever o frete calculado ativando o <strong>"Frete manual"</strong> e informando o valor desejado. Isso é útil quando você conhece o custo real de frete ou quando o produto tem dimensões especiais.</p><p><strong>Nota:</strong> As tabelas de frete são atualizadas periodicamente conforme os marketplaces alteram suas políticas.</p>',
                        'destructive' => false,
                    ],
                ],
            ],
            [
                'slug' => 'comparar-estoque-bling',
                'title' => 'Comparar Estoque Bling',
                'icon' => 'heroicon-o-scale',
                'questions' => [
                    [
                        'question' => 'O que é a página Comparar Estoque Bling e para que serve?',
                        'answer' => '<p>A página Comparar Estoque Bling permite verificar divergências de saldo entre as duas contas do ERP (Enterprise Resource Planning) Bling (primary e secondary) e o estoque registrado no sistema. É uma ferramenta de auditoria para garantir que os saldos estejam sincronizados entre todas as plataformas.</p><p><strong>Funcionalidades disponíveis:</strong></p><ul><li>Comparação completa de todos os produtos ativos (executada em background via job)</li><li>Busca específica por SKU (Stock Keeping Unit) ou nome do produto (execução imediata, limitada a 20 resultados)</li><li>Filtro por divergências ou visualização de todos os produtos</li><li>Exportação dos resultados em CSV (Comma-Separated Values)</li><li>Exibição de totais: produtos consultados e quantidade de divergências encontradas</li></ul>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Como realizar uma comparação de estoque?',
                        'answer' => '<p>Existem duas formas de comparar o estoque:</p><p><strong>1. Comparação completa (todos os produtos):</strong></p><ol><li>Deixe o campo de busca vazio</li><li>Selecione o filtro desejado ("Apenas divergências" ou "Todos")</li><li>Clique em "Consultar"</li><li>A comparação será executada em background (pode levar alguns minutos)</li><li>Recarregue a página após receber a notificação de conclusão</li></ol><p><strong>2. Busca específica por SKU (Stock Keeping Unit):</strong></p><ol><li>Digite o SKU ou nome do produto no campo de busca</li><li>Clique em "Consultar"</li><li>O resultado aparece imediatamente (consulta em tempo real à API do Bling)</li></ol><p>Os resultados exibem para cada produto: SKU, nome, saldo no sistema, saldo na conta primary e saldo na conta secondary, com destaque visual para itens divergentes.</p>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'O que fazer quando encontro divergências de estoque?',
                        'answer' => '<p>Quando divergências são identificadas, siga estes passos:</p><ol><li><strong>Identifique a causa</strong>: verifique se houve movimentação recente (venda, entrada, ajuste) que ainda não foi sincronizada</li><li><strong>Verifique o depósito</strong>: o sistema compara o depósito "Geral" de cada conta Bling. Movimentações em outros depósitos não são consideradas</li><li><strong>Aguarde sincronização</strong>: se houve movimentação recente, aguarde a próxima sincronização automática</li><li><strong>Ajuste manual</strong>: se a divergência persistir, utilize a página de Contagem de Estoque para corrigir o saldo</li></ol><p><strong>Dica:</strong> Exporte o resultado em CSV para análise detalhada em planilha, especialmente quando há muitas divergências.</p>',
                        'destructive' => false,
                    ],
                ],
            ],
            [
                'slug' => 'consulta-ctes',
                'title' => 'Consulta CT-es',
                'icon' => 'heroicon-o-document-text',
                'questions' => [
                    [
                        'question' => 'O que é a página Consulta CT-es e para que serve?',
                        'answer' => '<p>A página Consulta CT-es (Conhecimento de Transporte Eletrônico) permite visualizar, filtrar e gerenciar todos os CT-es importados no sistema. O CT-e é o documento fiscal que registra o serviço de transporte de mercadorias, contendo informações como valor do frete, transportadora, destinatário e chave de acesso da NF-e (Nota Fiscal Eletrônica) vinculada.</p><p><strong>Funcionalidades disponíveis:</strong></p><ul><li>Filtro por status: não utilizados, utilizados ou todos</li><li>Busca por número do CT-e, chave NF-e, chave CT-e, destinatário ou remetente</li><li>Filtro por transportadora e período</li><li>Vinculação manual de CT-e a pedidos</li><li>Alteração de tipo do CT-e (entrega, reentrega, devolução, assistência)</li><li>Exibição de totais e valor acumulado de CT-es não utilizados</li></ul>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Como vincular um CT-e a um pedido manualmente?',
                        'answer' => '<p>Para vincular um CT-e (Conhecimento de Transporte Eletrônico) a um pedido:</p><ol><li>Localize o CT-e na listagem (use os filtros de busca se necessário)</li><li>Clique no botão de vincular (ícone de link) no CT-e desejado</li><li>Informe o número do pedido no campo de busca do modal</li><li>O sistema exibirá os dados do pedido encontrado (cliente, canal, valor, nota fiscal)</li><li>Confirme a vinculação</li></ol><p>Após a vinculação, o sistema automaticamente:</p><ul><li>Marca o CT-e como "utilizado"</li><li>Atualiza o valor do frete no pedido (soma de todos os CT-es tipo "entrega" vinculados)</li><li>Recalcula as margens do pedido</li></ul><p><strong>Nota:</strong> Um pedido pode ter múltiplos CT-es vinculados (ex: entrega + reentrega). Apenas CT-es do tipo "entrega" são somados no valor do frete.</p>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'O que significam os tipos de CT-e e como alterá-los?',
                        'answer' => '<p>Cada CT-e pode ser classificado em um dos seguintes tipos:</p><ul><li><strong>Entrega</strong>: frete da entrega principal ao cliente. Este valor é somado ao custo de frete do pedido.</li><li><strong>Reentrega</strong>: frete de uma segunda tentativa de entrega. Não é somado ao frete do pedido (custo operacional separado).</li><li><strong>Devolução</strong>: frete de retorno do produto ao remetente. Não é somado ao frete do pedido.</li><li><strong>Assistência</strong>: frete relacionado a assistência técnica ou troca. Não é somado ao frete do pedido.</li></ul><p>Para alterar o tipo, clique no seletor de tipo ao lado do CT-e e escolha a nova classificação. O sistema recalculará automaticamente o frete do pedido vinculado (somando apenas CT-es tipo "entrega").</p><p><strong>Importante:</strong> Ao alterar o tipo para reentrega, devolução ou assistência em um CT-e não vinculado, o sistema abrirá um modal para vincular a um pedido.</p>',
                        'destructive' => false,
                    ],
                ],
            ],
            [
                'slug' => 'contagem-estoque',
                'title' => 'Contagem de Estoque',
                'icon' => 'heroicon-o-clipboard-document-check',
                'questions' => [
                    [
                        'question' => 'O que é a página Contagem de Estoque e para que serve?',
                        'answer' => '<p>A Contagem de Estoque é uma ferramenta para realizar inventário físico dos produtos utilizando leitor de código de barras ou digitação manual de SKU (Stock Keeping Unit). Ao finalizar a contagem, o sistema compara as quantidades contadas com o saldo registrado e atualiza automaticamente o estoque em ambas as contas Bling.</p><p><strong>Funcionalidades disponíveis:</strong></p><ul><li>Bipagem de produtos por código de barras ou SKU</li><li>Contagem acumulativa (cada bipagem incrementa a quantidade)</li><li>Ajuste manual de quantidade por item</li><li>Remoção de itens da contagem</li><li>Suporte a grupos de troca de tampo (equalização automática)</li><li>Finalização com balanço automático e sincronização com Bling</li><li>Relatório de divergências (diferença entre contagem e saldo do sistema)</li></ul>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Como realizar uma contagem de estoque passo a passo?',
                        'answer' => '<p>Para realizar uma contagem de estoque:</p><ol><li><strong>Inicie a contagem</strong>: acesse a página e posicione o cursor no campo de entrada</li><li><strong>Bipe os produtos</strong>: use o leitor de código de barras ou digite o SKU/código de barras e pressione Enter. Cada bipagem incrementa a quantidade do produto em 1 unidade</li><li><strong>Ajuste se necessário</strong>: caso tenha bipado a mais ou a menos, ajuste a quantidade manualmente no campo ao lado do produto</li><li><strong>Finalize a contagem</strong>: clique em "Finalizar Contagem" quando todos os produtos forem contados</li></ol><p>Ao finalizar, o sistema:</p><ul><li>Compara a quantidade contada com o saldo físico registrado</li><li>Atualiza o saldo de cada produto divergente via balanço</li><li>Sincroniza o novo saldo com ambas as contas Bling</li><li>Exibe um relatório de divergências com a diferença por produto</li></ul><p>⚠️ <strong>Aviso:</strong> A finalização da contagem é irreversível. Os saldos serão atualizados imediatamente no sistema e no Bling. Certifique-se de que a contagem está completa antes de finalizar.</p>',
                        'destructive' => true,
                    ],
                    [
                        'question' => 'Como funciona a contagem de produtos com troca de tampo?',
                        'answer' => '<p>Produtos que pertencem a um grupo de troca de tampo recebem tratamento especial na contagem:</p><ul><li>Ao bipar um produto de grupo de tampo, o sistema exibe o nome do grupo e cor entre parênteses</li><li>Na finalização, todos os SKUs do mesmo grupo+cor recebem o mesmo saldo total contado</li><li>Isso ocorre porque produtos de troca de tampo compartilham a mesma carcaça física — a quantidade de carcaças disponíveis é a mesma para todos os SKUs do grupo</li></ul><p><strong>Exemplo:</strong> Se o grupo "Mesa Retangular" cor "Branco" tem 3 SKUs (tampo vidro, tampo madeira, tampo mármore) e você contou 10 carcaças, todos os 3 SKUs terão saldo atualizado para 10.</p>',
                        'destructive' => false,
                    ],
                ],
            ],
            [
                'slug' => 'importar-frenet',
                'title' => 'Importar Frenet',
                'icon' => 'heroicon-o-truck',
                'questions' => [
                    [
                        'question' => 'O que é a página Importar Frenet e para que serve?',
                        'answer' => '<p>A página Importar Frenet permite importar dados de fretes da plataforma Frenet (gateway de frete) e vinculá-los aos pedidos do sistema. A Frenet é um intermediário que conecta transportadoras ao e-commerce, e esta página permite reconciliar os fretes cotados/contratados com os pedidos correspondentes.</p><p><strong>Funcionalidades disponíveis:</strong></p><ul><li>Importação de arquivo CSV (Comma-Separated Values) com dados de frete da Frenet</li><li>Listagem de fretes importados com filtros</li><li>Vinculação automática de frete a pedidos (por número de pedido)</li><li>Vinculação manual via busca de pedido</li><li>Alteração de tipo do frete (entrega, reentrega, devolução)</li><li>Exibição de totais (valor total de fretes, quantidade vinculada/pendente)</li></ul>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Como importar e vincular fretes da Frenet?',
                        'answer' => '<p>Para importar fretes da Frenet:</p><ol><li>Exporte o relatório de fretes da plataforma Frenet em formato CSV</li><li>Na página Importar Frenet, faça o upload do arquivo CSV</li><li>O sistema processará o arquivo e listará os fretes importados</li></ol><p>Para vincular fretes a pedidos:</p><ul><li><strong>Vinculação automática</strong>: clique no botão "Vincular Auto" em um frete — o sistema tentará encontrar o pedido correspondente automaticamente pelo número</li><li><strong>Vinculação manual</strong>: clique no botão de vincular e informe o número do pedido manualmente</li></ul><p>Após a vinculação, o valor do frete é registrado no pedido e as margens são recalculadas automaticamente.</p>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'O que fazer quando a vinculação automática não encontra o pedido?',
                        'answer' => '<p>A vinculação automática pode falhar quando:</p><ul><li><strong>Número de pedido diferente</strong>: o número no Frenet pode não corresponder exatamente ao número no sistema (prefixos, sufixos ou formatação diferente)</li><li><strong>Pedido não importado</strong>: o pedido pode ainda não ter sido importado do Bling para o sistema</li><li><strong>Pedido já com frete</strong>: se o pedido já possui frete lançado, a vinculação automática pode ignorá-lo</li></ul><p>Nesses casos, use a <strong>vinculação manual</strong>: clique no botão de vincular, informe o número correto do pedido e confirme após verificar os dados exibidos no modal.</p>',
                        'destructive' => false,
                    ],
                ],
            ],
            [
                'slug' => 'importar-pedidos',
                'title' => 'Importar Pedidos',
                'icon' => 'heroicon-o-arrow-down-on-square',
                'questions' => [
                    [
                        'question' => 'O que é a página Importar Pedidos e para que serve?',
                        'answer' => '<p>A página Importar Pedidos permite importar pedidos diretamente do ERP (Enterprise Resource Planning) Bling para o sistema, com filtros de período, conta e canal. A importação é executada em background (job assíncrono) e traz os dados completos dos pedidos incluindo produtos, valores, cliente e informações de envio.</p><p><strong>Funcionalidades disponíveis:</strong></p><ul><li>Seleção de conta Bling (Mobilia Decor ou HES Móveis)</li><li>Filtro por período (data início e data fim)</li><li>Filtro opcional por canal de venda específico</li><li>Execução em background com notificação ao concluir</li></ul>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Como importar pedidos do Bling?',
                        'answer' => '<p>Para importar pedidos:</p><ol><li>Selecione a <strong>conta Bling</strong> desejada (Mobilia Decor ou HES Móveis)</li><li>Informe a <strong>data de início</strong> do período</li><li>Informe a <strong>data de fim</strong> do período</li><li>Opcionalmente, selecione um <strong>canal de venda</strong> específico para filtrar (ex: apenas Shopee)</li><li>Clique em <strong>"Importar"</strong></li></ol><p>A importação será enfileirada e processada em background. Você receberá uma notificação quando o processo terminar.</p><p><strong>Pré-requisitos:</strong></p><ul><li>A integração com o Bling deve estar autorizada (token válido)</li><li>Os pedidos devem existir na conta Bling selecionada dentro do período informado</li></ul><p><strong>Nota:</strong> Pedidos já existentes no sistema serão atualizados com os dados mais recentes do Bling.</p>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'A importação de pedidos não trouxe todos os pedidos esperados. O que verificar?',
                        'answer' => '<p>Se a importação não trouxe todos os pedidos, verifique:</p><ul><li><strong>Conta correta</strong>: confirme que selecionou a conta Bling onde os pedidos estão registrados</li><li><strong>Período correto</strong>: verifique se as datas de início e fim cobrem o período desejado. A data considerada é a data do pedido no Bling</li><li><strong>Canal de venda</strong>: se selecionou um canal específico, apenas pedidos daquele canal serão importados</li><li><strong>Status no Bling</strong>: apenas pedidos com determinados status são importados (pedidos em rascunho ou cancelados no Bling podem ser ignorados)</li><li><strong>Token válido</strong>: verifique se a integração Bling está autorizada na página de Integração</li></ul>',
                        'destructive' => false,
                    ],
                ],
            ],
            [
                'slug' => 'importar-shopee-afiliados',
                'title' => 'Importar Shopee Afiliados',
                'icon' => 'heroicon-o-document-arrow-up',
                'questions' => [
                    [
                        'question' => 'O que é a página Importar Shopee Afiliados e para que serve?',
                        'answer' => '<p>A página Importar Shopee Afiliados permite importar a planilha de comissões de afiliados da Shopee e vincular os valores aos pedidos correspondentes. Afiliados são parceiros que divulgam produtos e recebem comissão sobre vendas geradas — esse custo precisa ser registrado para cálculo correto da margem.</p><p><strong>Funcionalidades disponíveis:</strong></p><ul><li>Upload de planilha CSV com dados de comissão de afiliados</li><li>Definição do período de referência da importação</li><li>Processamento automático: vincula comissão de afiliado aos pedidos Shopee</li><li>Travamento de pedidos anteriores ao período (marca como processados sem afiliado)</li><li>Marcação de pedidos do período sem afiliado encontrado na planilha</li><li>Exibição de pedidos pendentes e data do primeiro pendente</li><li>Marcação manual de período como processado (sem planilha)</li></ul>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Como importar a planilha de afiliados Shopee?',
                        'answer' => '<p>Para importar a planilha de afiliados:</p><ol><li>Defina o <strong>período de referência</strong> (data início e fim) — geralmente o mês anterior completo</li><li>Faça o <strong>upload do arquivo CSV</strong> exportado do painel de afiliados da Shopee</li><li>Clique em <strong>"Processar"</strong></li></ol><p>O sistema executará as seguintes etapas automaticamente:</p><ol><li><strong>Trava pedidos anteriores</strong>: marca pedidos Shopee com data anterior ao período como "processados" (sem afiliado)</li><li><strong>Processa planilha</strong>: vincula a comissão de afiliado aos pedidos encontrados na planilha</li><li><strong>Marca restantes</strong>: pedidos do período que não aparecem na planilha são marcados como "sem afiliado"</li></ol><p>O resultado exibe: quantidade de afiliados vinculados, pedidos travados, pedidos sem afiliado, não encontrados e erros.</p>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'O que significa "marcar período como processado" e quando usar?',
                        'answer' => '<p>A função "Marcar Período" permite marcar manualmente todos os pedidos Shopee de um período específico como "processados para afiliado", mesmo sem importar uma planilha. Use esta função quando:</p><ul><li>Não há planilha de afiliados disponível para o período</li><li>Você sabe que nenhum pedido do período teve afiliado</li><li>Deseja limpar o status "Falta Afiliado Shopee" dos pedidos no Dashboard</li></ul><p>Após marcar, os pedidos do período não aparecerão mais com o status "Falta Afiliado Shopee" no Dashboard de Vendas.</p><p><strong>Nota:</strong> Esta ação não pode ser desfeita facilmente. Certifique-se de que realmente não há dados de afiliado para o período antes de usar.</p>',
                        'destructive' => false,
                    ],
                ],
            ],
            [
                'slug' => 'importar-tabela-transportadora',
                'title' => 'Importar Tabela Transportadora',
                'icon' => 'heroicon-o-document-arrow-up',
                'questions' => [
                    [
                        'question' => 'O que é a página Importar Tabela Transportadora e para que serve?',
                        'answer' => '<p>A página Importar Tabela Transportadora permite importar tabelas de frete e taxas especiais de transportadoras via planilha. Essas tabelas são utilizadas pelo Simulador de Frete para calcular cotações e pelo sistema para estimar custos de envio.</p><p><strong>Funcionalidades disponíveis:</strong></p><ul><li>Seleção da transportadora cadastrada no sistema</li><li>Dois tipos de importação: Taxas Especiais (TDA, TRT, TAR, TAS) e Tabela de Frete (faixas de peso/CEP)</li><li>Opção de limpar dados existentes antes de importar</li><li>Suporte a arquivos XLSX, XLS e CSV (Comma-Separated Values)</li><li>Relatório de importação com quantidade de registros importados e erros</li></ul>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Qual a diferença entre importar Taxas Especiais e Tabela de Frete?',
                        'answer' => '<p>A importação oferece dois tipos distintos:</p><p><strong>Taxas Especiais (TDA, TRT, TAR, TAS):</strong></p><ul><li><strong>TDA</strong> (Taxa de Dificuldade de Acesso): cobrada para entregas em locais de difícil acesso</li><li><strong>TRT</strong> (Taxa de Restrição de Trânsito): cobrada para entregas em áreas com restrição de veículos</li><li><strong>TAR</strong> (Taxa de Área de Risco): cobrada para entregas em áreas consideradas de risco</li><li><strong>TAS</strong> (Taxa de Área de Serviço): cobrada para entregas fora da área de cobertura padrão</li></ul><p><strong>Tabela de Frete (faixas de peso/CEP):</strong></p><ul><li>Define o valor do frete por combinação de faixa de peso e faixa de CEP de destino</li><li>Utilizada pelo Simulador de Frete para calcular cotações</li></ul><p>⚠️ <strong>Aviso:</strong> Se a opção "Limpar dados existentes antes de importar" estiver ativada, todos os registros do tipo selecionado para aquela transportadora serão removidos antes da importação. Esta ação é irreversível.</p>',
                        'destructive' => true,
                    ],
                    [
                        'question' => 'O que fazer quando a importação apresenta erros?',
                        'answer' => '<p>Se a importação apresentar erros, verifique:</p><ul><li><strong>Formato do arquivo</strong>: confirme que está usando XLSX, XLS ou CSV</li><li><strong>Estrutura da planilha</strong>: a planilha deve seguir o formato esperado pelo sistema (colunas na ordem correta)</li><li><strong>Dados válidos</strong>: verifique se os valores numéricos estão formatados corretamente (sem texto em campos numéricos)</li><li><strong>Transportadora selecionada</strong>: confirme que a transportadora correta está selecionada</li><li><strong>Tamanho do arquivo</strong>: o limite é 10MB. Planilhas muito grandes podem precisar ser divididas</li></ul><p>O sistema exibe até 3 mensagens de erro para ajudar na identificação do problema. Corrija os dados na planilha e tente novamente.</p>',
                        'destructive' => false,
                    ],
                ],
            ],
            [
                'slug' => 'lote-recebimentos',
                'title' => 'Lote de Recebimentos',
                'icon' => 'heroicon-o-clipboard-document-list',
                'questions' => [
                    [
                        'question' => 'O que é a página Lote de Recebimentos e para que serve?',
                        'answer' => '<p>A página Lote de Recebimentos permite confirmar o recebimento de múltiplos repasses de marketplaces de uma só vez, agrupando-os em um lote. É utilizada quando o marketplace realiza um pagamento consolidado (ex: repasse semanal) que inclui vários pedidos.</p><p><strong>Funcionalidades disponíveis:</strong></p><ul><li>Busca de pedidos pendentes por número</li><li>Adição em lote via lista de números (separados por vírgula, espaço ou ponto-e-vírgula)</li><li>Definição de data de recebimento e conta bancária</li><li>Identificador do lote (ex: "Repasse Shopee 15/01")</li><li>Adição de descontos ao lote (taxas, glosas, ajustes)</li><li>Visualização de totais: valor bruto, descontos e líquido</li><li>Confirmação em lote com registro automático no financeiro</li></ul>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Como criar e confirmar um lote de recebimentos?',
                        'answer' => '<p>Para criar um lote de recebimentos:</p><ol><li><strong>Adicione pedidos ao lote</strong>: use a busca individual ou cole uma lista de números de pedido no campo "Adicionar múltiplos"</li><li><strong>Verifique os itens</strong>: confira os pedidos adicionados, valores e totais</li><li><strong>Adicione descontos</strong> (se houver): informe descrição e valor de cada desconto (ex: "Taxa de antecipação", "Glosa por atraso")</li><li><strong>Defina a data de recebimento</strong>: data em que o dinheiro entrou na conta</li><li><strong>Selecione a conta bancária</strong>: conta onde o valor foi creditado</li><li><strong>Informe o identificador</strong> (opcional): nome para identificar o lote (ex: "Repasse ML Semana 3")</li><li><strong>Confirme o lote</strong>: clique em "Confirmar Lote"</li></ol><p>Ao confirmar, o sistema:</p><ul><li>Marca todas as contas a receber como "recebido" com a data informada</li><li>Atualiza os pedidos correspondentes como "repasse recebido"</li><li>Lança os descontos como contas a pagar (já pagas) na conta bancária selecionada</li></ul>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'O que acontece quando um pedido não é encontrado na busca?',
                        'answer' => '<p>Quando um pedido não é encontrado ao adicionar ao lote, pode ser por:</p><ul><li><strong>Pedido sem conta a receber</strong>: o sistema tentará criar automaticamente uma conta a receber para o pedido (calculando o valor de repasse esperado)</li><li><strong>Pedido não existe no sistema</strong>: o número informado não corresponde a nenhum pedido importado. Verifique se o pedido foi importado do Bling</li><li><strong>Conta já recebida</strong>: se o pedido já teve seu recebimento confirmado anteriormente, ele não aparecerá na busca de pendentes</li></ul><p>Ao usar "Adicionar múltiplos", o sistema informa quais números não foram encontrados para que você possa verificar individualmente.</p>',
                        'destructive' => false,
                    ],
                ],
            ],
            [
                'slug' => 'mercado-livre-promocoes',
                'title' => 'Mercado Livre Promoções',
                'icon' => 'heroicon-o-tag',
                'questions' => [
                    [
                        'question' => 'O que é a página Mercado Livre Promoções e para que serve?',
                        'answer' => '<p>A página Mercado Livre Promoções permite gerenciar a adesão de produtos a promoções ativas no ML (Mercado Livre). Através dela, você pode visualizar promoções disponíveis, verificar quais produtos são elegíveis e aderir automaticamente, otimizando a visibilidade dos seus anúncios.</p><p><strong>Funcionalidades disponíveis:</strong></p><ul><li>Visualização de promoções ativas por conta ML (Mercado Livre)</li><li>Alternância entre contas (múltiplas contas suportadas)</li><li>Listagem de itens elegíveis para cada promoção</li><li>Adesão individual ou em lote a promoções</li><li>Edição de preço promocional antes da adesão</li><li>Busca de promoções disponíveis para um item específico</li><li>Opção de pular/ignorar itens que não deseja aderir</li></ul>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Como aderir produtos a uma promoção do Mercado Livre?',
                        'answer' => '<p>Para aderir produtos a uma promoção:</p><ol><li>Selecione a <strong>conta ML</strong> desejada no topo da página</li><li>Clique em <strong>"Carregar Promoções"</strong> para listar as promoções ativas</li><li>Selecione a promoção desejada na lista</li><li>Clique em <strong>"Carregar Itens"</strong> para ver os produtos elegíveis</li><li>Para cada item, você pode:<ul><li><strong>Aderir</strong>: confirma a adesão com o preço sugerido</li><li><strong>Editar preço</strong>: altera o preço promocional antes de aderir</li><li><strong>Pular</strong>: ignora o item (não adere)</li></ul></li></ol><p>A adesão é processada via API (Application Programming Interface) do Mercado Livre e o resultado é exibido imediatamente (sucesso ou erro).</p><p><strong>Nota:</strong> Promoções têm prazo de adesão. Verifique as datas de início e fim antes de aderir.</p>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Como buscar promoções disponíveis para um item específico?',
                        'answer' => '<p>Para verificar quais promoções estão disponíveis para um produto específico:</p><ol><li>Use o campo de busca de itens na página</li><li>Informe o ID do item no ML (Mercado Livre) (ex: MLB123456789)</li><li>O sistema consultará a API e listará todas as promoções disponíveis para aquele item</li><li>Você pode aderir diretamente a partir dos resultados</li></ol><p>Isso é útil quando você quer verificar se um produto específico tem promoções disponíveis sem precisar navegar por todas as promoções ativas.</p>',
                        'destructive' => false,
                    ],
                ],
            ],
            [
                'slug' => 'recebimentos',
                'title' => 'Recebimentos',
                'icon' => 'heroicon-o-currency-dollar',
                'questions' => [
                    [
                        'question' => 'O que é a página Recebimentos e para que serve?',
                        'answer' => '<p>A página Recebimentos permite gerenciar as contas a receber do sistema, controlando quais repasses de marketplaces já foram creditados na conta bancária. É o ponto central para confirmar individualmente o recebimento de valores e manter o financeiro atualizado.</p><p><strong>Funcionalidades disponíveis:</strong></p><ul><li>Listagem de contas a receber com filtros (período, canal, conta, status)</li><li>Filtro por status: pendentes, recebidos ou todos</li><li>Busca por número de pedido</li><li>Confirmação individual de recebimento (com data)</li><li>Desfazer recebimento (voltar para pendente)</li><li>Agrupamento por canal ou por data</li><li>Exibição de totais: valor pendente, valor recebido, quantidade</li><li>Paginação para grandes volumes</li></ul>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Como confirmar o recebimento de um repasse?',
                        'answer' => '<p>Para confirmar que um repasse foi recebido na conta bancária:</p><ol><li>Localize o pedido na listagem (use filtros ou busca por número)</li><li>Clique no botão <strong>"Confirmar Recebimento"</strong> ao lado do pedido</li><li>Informe a <strong>data de recebimento</strong> (data em que o valor entrou na conta)</li><li>Confirme a operação</li></ol><p>O sistema irá:</p><ul><li>Alterar o status da conta a receber para "recebido"</li><li>Registrar a data de recebimento</li><li>Marcar o pedido como "repasse recebido"</li><li>O valor aparecerá como entrada no Fluxo de Caixa na data informada</li></ul><p><strong>Dica:</strong> Para confirmar múltiplos recebimentos de uma vez, use a página "Lote de Recebimentos".</p>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Como desfazer um recebimento confirmado por engano?',
                        'answer' => '<p>Se um recebimento foi confirmado por engano, você pode desfazê-lo:</p><ol><li>Filtre por status <strong>"Recebidos"</strong> para encontrar o registro</li><li>Localize o pedido desejado</li><li>Clique no botão <strong>"Desfazer Recebimento"</strong></li></ol><p>O sistema irá:</p><ul><li>Reverter o status da conta a receber para "pendente"</li><li>Remover a data de recebimento</li><li>Desmarcar o pedido como "repasse recebido"</li><li>O valor deixará de aparecer como entrada no Fluxo de Caixa</li></ul><p>⚠️ <strong>Aviso:</strong> Desfazer um recebimento altera o fluxo de caixa retroativamente. Verifique se isso não impacta relatórios já gerados.</p>',
                        'destructive' => true,
                    ],
                ],
            ],
            [
                'slug' => 'relatorio-fretes',
                'title' => 'Relatório de Fretes',
                'icon' => 'heroicon-o-document-chart-bar',
                'questions' => [
                    [
                        'question' => 'O que é a página Relatório de Fretes e para que serve?',
                        'answer' => '<p>O Relatório de Fretes é uma ferramenta de análise que permite visualizar e exportar dados detalhados sobre os fretes de todos os pedidos do sistema. Ele consolida informações de CT-e (Conhecimento de Transporte Eletrônico), frete manual, frete de marketplace e custos de envio para análise gerencial.</p><p><strong>Funcionalidades disponíveis:</strong></p><ul><li>Filtros avançados: período, canal de venda, conta Bling, transportadora, tipo de frete, status</li><li>Visualização detalhada por pedido: valor do frete, transportadora, tipo (CT-e, manual, marketplace)</li><li>Resumo consolidado: total de fretes, média por pedido, distribuição por transportadora</li><li>Exportação completa em planilha para análise externa</li><li>Comparação entre frete cobrado do cliente e frete pago à transportadora</li></ul>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Como exportar o relatório de fretes?',
                        'answer' => '<p>Para exportar o relatório:</p><ol><li>Configure os filtros desejados (período, canal, transportadora, etc.)</li><li>Clique no botão <strong>"Exportar"</strong></li><li>O sistema gerará um arquivo com todos os pedidos que atendem aos filtros</li></ol><p>O arquivo exportado contém informações detalhadas incluindo: número do pedido, data, canal, cliente, valor do frete cobrado, valor do frete pago, transportadora, número do CT-e, tipo de frete e margem de frete (diferença entre cobrado e pago).</p><p><strong>Dica:</strong> Use o relatório para identificar transportadoras com melhor custo-benefício e pedidos com margem de frete negativa (frete pago maior que o cobrado do cliente).</p>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Como identificar pedidos com frete deficitário?',
                        'answer' => '<p>Pedidos com frete deficitário são aqueles onde o valor pago à transportadora é maior que o valor cobrado do cliente. Para identificá-los:</p><ul><li>No relatório, observe a coluna de margem de frete (frete cobrado - frete pago)</li><li>Valores negativos indicam prejuízo no frete</li><li>Filtre por transportadora específica para identificar padrões</li></ul><p>Causas comuns de frete deficitário:</p><ul><li>Frete grátis oferecido ao cliente (cobrado = R$ 0,00)</li><li>Tabela de frete do marketplace desatualizada</li><li>Reentregas que geram custo adicional</li><li>Peso real do produto maior que o cadastrado</li></ul>',
                        'destructive' => false,
                    ],
                ],
            ],
            [
                'slug' => 'simulador-frete',
                'title' => 'Simulador de Frete',
                'icon' => 'heroicon-o-truck',
                'questions' => [
                    [
                        'question' => 'O que é o Simulador de Frete e para que serve?',
                        'answer' => '<p>O Simulador de Frete permite calcular cotações de frete para qualquer destino com base nas tabelas de transportadoras cadastradas no sistema. É utilizado para estimar custos de envio antes de fechar vendas ou para comparar valores entre transportadoras.</p><p><strong>Funcionalidades disponíveis:</strong></p><ul><li>Cotação por CEP de destino e UF</li><li>Cálculo automático de peso cubado (comprimento × largura × altura × 300)</li><li>Comparação entre peso real e peso cubado (utiliza o maior)</li><li>Cotação simultânea em todas as transportadoras cadastradas</li><li>Informação de valor da NF-e (Nota Fiscal Eletrônica) para cálculo de ad valorem</li><li>Exibição de prazo estimado de entrega por transportadora</li></ul>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Como simular um frete?',
                        'answer' => '<p>Para simular um frete:</p><ol><li>Informe o <strong>CEP de destino</strong> (formato 00000-000)</li><li>Informe a <strong>UF</strong> de destino (ex: SP, RJ, MG)</li><li>Opcionalmente, informe a <strong>cidade</strong> de destino</li><li>Informe o <strong>valor da NF</strong> (usado para cálculo de ad valorem/seguro)</li><li>Informe o <strong>peso bruto</strong> em kg</li><li>Opcionalmente, informe as <strong>dimensões</strong> (largura, altura, comprimento em cm) para cálculo de peso cubado</li><li>Clique em <strong>"Simular"</strong></li></ol><p>O resultado exibirá cotações de todas as transportadoras cadastradas, ordenadas por valor, incluindo: nome da transportadora, valor do frete, prazo estimado e observações.</p><p><strong>Peso cubado:</strong> Calculado como (Comprimento × Largura × Altura em metros) × 300. O sistema utiliza o maior valor entre peso real e peso cubado para a cotação.</p>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Por que o peso cubado pode ser diferente do peso real?',
                        'answer' => '<p>O peso cubado é uma medida que considera o volume do pacote, não apenas seu peso físico. Transportadoras cobram pelo maior valor entre peso real e peso cubado porque:</p><ul><li>Pacotes volumosos ocupam mais espaço no veículo, mesmo sendo leves</li><li>O espaço no caminhão é limitado e tem custo</li></ul><p><strong>Fórmula:</strong> Peso Cubado = (Comprimento × Largura × Altura em metros) × fator 300</p><p><strong>Exemplo:</strong> Uma caixa de 100cm × 50cm × 50cm com peso real de 5kg:<br>Peso cubado = (1,0 × 0,5 × 0,5) × 300 = 75kg<br>Como 75kg > 5kg, a transportadora cobrará pelo peso cubado (75kg).</p><p>Se as dimensões não forem informadas, o simulador utilizará apenas o peso bruto informado.</p>',
                        'destructive' => false,
                    ],
                ],
            ],
            [
                'slug' => 'troca-tampos',
                'title' => 'Troca de Tampos',
                'icon' => 'heroicon-o-arrows-right-left',
                'questions' => [
                    [
                        'question' => 'O que é a página Troca de Tampos e para que serve?',
                        'answer' => '<p>A página Troca de Tampos gerencia a operação de montagem de produtos que compartilham a mesma carcaça (estrutura base) mas possuem tampos diferentes (vidro, madeira, mármore, etc.). Quando um cliente compra um produto com tampo específico que não está em estoque como caixa fechada, é necessário abrir outra caixa do mesmo grupo para usar a carcaça e obter o tampo de outra fonte.</p><p><strong>Funcionalidades disponíveis:</strong></p><ul><li>Seleção guiada em 3 passos: produto vendido → fonte do tampo → caixa a abrir</li><li>Execução automática de movimentações de estoque no Bling</li><li>Suporte a tampo vindo do estoque avulso ou de outra caixa</li><li>Ação "Rodar Variação de Tampos": equaliza saldos de todos os grupos</li><li>Ação "Aplicar Limite de Tampos": limita saldo pelo estoque do tampo correspondente</li></ul>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Como executar uma troca de tampo passo a passo?',
                        'answer' => '<p>A troca de tampo segue 3 passos:</p><p><strong>Passo 1 — Produto Vendido:</strong></p><ol><li>Selecione o <strong>grupo</strong> do produto (ex: Mesa Retangular)</li><li>Selecione a <strong>cor</strong> (ex: Branco)</li><li>Selecione o <strong>tipo de tampo</strong> vendido (ex: Vidro)</li></ol><p><strong>Passo 2 — Fonte do Tampo:</strong></p><ul><li>Selecione de onde vem o tampo: do estoque avulso ou de outra caixa do grupo</li></ul><p><strong>Passo 3 — Caixa a Abrir:</strong></p><ul><li>Selecione qual caixa será aberta para fornecer a carcaça</li><li>O tampo desta caixa voltará ao estoque (se a fonte for estoque) ou será usado para montar outro produto (se a fonte for outra caixa)</li></ul><p>Clique em <strong>"Executar"</strong> para processar. O sistema realizará todas as movimentações de estoque automaticamente no Bling.</p><p>⚠️ <strong>Aviso:</strong> A execução da troca movimenta estoque real no Bling e não pode ser desfeita automaticamente. Verifique as seleções antes de confirmar.</p>',
                        'destructive' => true,
                    ],
                    [
                        'question' => 'O que fazem as ações "Rodar Variação" e "Aplicar Limite de Tampos"?',
                        'answer' => '<p>Estas são ações em lote que afetam todos os grupos de troca de tampo:</p><p><strong>Rodar Variação de Tampos:</strong></p><ul><li>Equaliza os saldos de todos os SKUs (Stock Keeping Unit) dentro de cada grupo+cor</li><li>Garante que todos os produtos do mesmo grupo tenham o mesmo saldo (baseado na quantidade de carcaças disponíveis)</li><li>Executada em background via job</li></ul><p><strong>Aplicar Limite de Tampos:</strong></p><ul><li>Limita o saldo de cada produto pela disponibilidade do tampo correspondente</li><li>Se há 10 carcaças mas apenas 5 tampos de vidro, o SKU com tampo vidro terá saldo limitado a 5</li><li>Executada em background via job</li></ul><p>⚠️ <strong>Aviso:</strong> Ambas as ações alteram saldos de estoque em massa no Bling e não podem ser desfeitas automaticamente. Use com cautela e apenas quando necessário.</p>',
                        'destructive' => true,
                    ],
                ],
            ],
            [
                'slug' => 'tutorial-conciliacao',
                'title' => 'Tutorial Conciliação',
                'icon' => 'heroicon-o-book-open',
                'questions' => [
                    [
                        'question' => 'O que é a página Tutorial Conciliação e para que serve?',
                        'answer' => '<p>A página Tutorial Conciliação é um guia passo a passo que explica o processo completo de conciliação financeira do sistema. A conciliação é o processo de verificar se todos os valores recebidos dos marketplaces correspondem aos pedidos registrados, garantindo que não há divergências financeiras.</p><p><strong>Conteúdo do tutorial:</strong></p><ul><li>Explicação do fluxo completo de conciliação</li><li>Ordem correta das etapas (importar planilhas → confirmar recebimentos → verificar caixa)</li><li>Dicas para identificar e resolver divergências</li><li>Referências às páginas do sistema utilizadas em cada etapa</li></ul>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Qual é a ordem correta para fazer a conciliação financeira?',
                        'answer' => '<p>A conciliação financeira deve seguir esta ordem para garantir dados corretos:</p><ol><li><strong>Importar pedidos</strong>: certifique-se de que todos os pedidos do período estão no sistema (página Importar Pedidos)</li><li><strong>Buscar NF-e e CT-e</strong>: execute as ações em lote no Dashboard para completar dados fiscais</li><li><strong>Importar planilhas dos marketplaces</strong>: importe as planilhas financeiras de cada canal (Shopee, ML, Magalu, etc.)</li><li><strong>Importar afiliados Shopee</strong>: se aplicável, importe a planilha de comissões de afiliados</li><li><strong>Confirmar recebimentos</strong>: na página Recebimentos ou Lote Recebimentos, confirme os repasses recebidos</li><li><strong>Verificar Caixa</strong>: confira se o fluxo de caixa reflete corretamente as entradas e saídas</li></ol><p><strong>Dependência:</strong> Cada etapa depende da anterior. Importar planilhas antes dos pedidos resultará em "não encontrados". Confirmar recebimentos antes de importar planilhas resultará em margens incorretas.</p>',
                        'destructive' => false,
                    ],
                ],
            ],
            [
                'slug' => 'upload-cte',
                'title' => 'Upload CT-e',
                'icon' => 'heroicon-o-document-arrow-up',
                'questions' => [
                    [
                        'question' => 'O que é a página Upload CT-e e para que serve?',
                        'answer' => '<p>A página Upload CT-e (Conhecimento de Transporte Eletrônico) permite importar arquivos XML de CT-es diretamente para o sistema. Os CT-es contêm informações sobre o serviço de transporte (valor do frete, transportadora, destinatário, chave de acesso) e são utilizados para vincular custos de frete aos pedidos.</p><p><strong>Funcionalidades disponíveis:</strong></p><ul><li>Upload de múltiplos arquivos XML simultaneamente (até 200 arquivos, máximo 50MB total)</li><li>Detecção automática de duplicados (por chave de acesso do CT-e)</li><li>Validação de XML (verifica se é um CT-e válido)</li><li>Processamento automático: salva no banco de dados após upload</li><li>Relatório de resultado: novos, duplicados, inválidos e erros</li></ul>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'Como fazer upload de CT-es?',
                        'answer' => '<p>Para importar CT-es via XML:</p><ol><li>Clique no campo de upload ou arraste os arquivos XML</li><li>Selecione um ou mais arquivos XML de CT-e (até 200 arquivos por vez)</li><li>Clique em <strong>"Processar"</strong></li></ol><p>O sistema irá:</p><ol><li>Validar cada arquivo (verificar se é XML válido e se contém dados de CT-e)</li><li>Verificar duplicados (CT-es com mesma chave de acesso já importados)</li><li>Mover arquivos novos para a pasta de pendentes</li><li>Processar os XMLs pendentes e salvar no banco de dados</li></ol><p>O resultado exibe: quantidade de enviados, novos importados, duplicados ignorados, inválidos e erros.</p><p><strong>Nota:</strong> Após o upload, os CT-es ficam disponíveis na página "Consulta CT-es" para vinculação aos pedidos.</p>',
                        'destructive' => false,
                    ],
                    [
                        'question' => 'O que fazer quando o upload indica "CT-e inválido" ou "chave não encontrada"?',
                        'answer' => '<p>Esses erros indicam problemas com o arquivo XML:</p><ul><li><strong>"XML inválido"</strong>: o arquivo não é um XML bem-formado. Pode estar corrompido ou não ser um arquivo XML real. Verifique se o arquivo foi baixado corretamente</li><li><strong>"Não é um CT-e válido (chave não encontrada)"</strong>: o XML é válido mas não contém a estrutura esperada de um CT-e. Pode ser uma NF-e ou outro documento fiscal. Certifique-se de que está enviando apenas XMLs de CT-e</li><li><strong>"Arquivo não encontrado"</strong>: o arquivo temporário expirou durante o processamento. Faça o upload novamente</li></ul><p><strong>Dica:</strong> CT-es válidos possuem a tag <code>&lt;infCte&gt;</code> com atributo <code>Id</code> contendo a chave de acesso de 44 dígitos precedida por "CTe".</p>',
                        'destructive' => false,
                    ],
                ],
            ],
        ];
    }
}
