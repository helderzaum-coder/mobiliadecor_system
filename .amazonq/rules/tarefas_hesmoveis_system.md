criar o nome do commit ao final de cada atualização.
o restante eu faço manual
A versão local do programa é apenas backup. o sistema está sendo testado e utilizado remotamente
O mysql não roda localmente, somente no servidor online.
Eu tenho acesso SSH ao servidor caso precise rodar algo direto no servidor via terminal
Versão do PHP do servidor:8.3.28
Não esquecer do "cache do worker" quando as atualizações estiverem demorando para surtir efeito
o caminho no servidor é cd var/www/html
Todas as novas páginas com filtro de data devem conter:
- Hoje
- Opção de selecionar um dia especifico
- Esta semana
- Este mês
- Mês Passado
- Selecionar Mês
- Periodo Customizado
Além disto, páginas com filtro devem ter o filtro na URL, para quando atualizar a página não perder a seleção
Já existe um deploy.yml na pasta github/workflows
Somente indicar comandos de deploy no github, caso este comando não esteja no arquivo deploy.yml
