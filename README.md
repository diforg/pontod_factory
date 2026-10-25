# Gestao de Producao 3D

Sistema web para pequeno empreendedor gerenciar producao com impressora 3D.
Inclui cadastro de filamentos, pecas, produtos, placas de impressao,
estoque de itens e ledger auditavel de filamento.

## Stack

- PHP 8.3
- Laravel 11
- Inertia 2
- Vue 3
- MySQL 8.4
- Vite 6
- Pest 3
- Tailwind CSS
- Docker

## Pre-requisitos

- Docker Engine 24+
- Docker Compose v2.20+
- Git
- 4 GB de RAM livre

## Instalacao rapida (primeira vez)

```bash
git clone <repo-url>
cd <repo>
./bin/setup
```

O script `bin/setup` automatiza o bootstrap de desenvolvimento local.
No fluxo padrao ele executa as etapas abaixo, em ordem:

1. Copia `.env.example` para `.env`.
2. Sobe o banco MySQL via Docker Compose.
3. Executa `composer install` no container da aplicacao.
4. Gera a chave com `php artisan key:generate`.
5. Roda `php artisan migrate:fresh --seed` para criar schema e dados iniciais.
6. Executa `npm ci` para instalar dependencias frontend.
7. Executa `npm run build` para gerar assets.
8. Mantem os servicos ativos com `docker compose up`.

Notas importantes para primeira execucao:

- O processo pode levar alguns minutos dependendo do download inicial de imagens.
- Se houver conflito de porta local, ajuste antes de subir os containers.
- A aplicacao espera que o arquivo `.env` exista na raiz do projeto.
- O setup e idempotente para uso local, mas reset de banco pode sobrescrever dados.

## Acessar o sistema (dev)

- URL: http://localhost:8000
- O acesso inicial redireciona para `/login`
- Credenciais padrao (criadas pelo seeder):
	- E-mail: test@example.com
	- Senha: password

> **AVISO:**
> A senha `password` e apenas para desenvolvimento local.
> Troque a credencial antes de expor o sistema em qualquer rede publica.

Checklist de acesso rapido:

- Confirmar containers `app`, `node` e `mysql` em execucao.
- Validar que a URL responde com redirecionamento para login.
- Autenticar com o usuario seed e confirmar carregamento do dashboard.

## Estrutura de dados (visao geral)

| Entidade | Papel no dominio |
| --- | --- |
| `filaments` | Rolos de filamento com saldo materializado para leitura rapida. |
| `filament_movements` | Ledger append-only com eventos `ENTRY`, `CONSUMPTION`, `REVERSAL` e `RECONCILIATION_ADJUSTMENT`. |
| `parts` | Pecas imprimiveis com metadados tecnicos de consumo e tempo. |
| `products` | Produtos compostos por uma ou mais pecas. |
| `plates` | Placas de impressao com ciclo `CREATED -> QUEUED -> PRINTING -> COMPLETED`. |
| `items` | Estoque de itens gerado quando placas sao concluidas. |

Visao simplificada das relacoes:

```text
filaments 1 --- N filament_movements
plates    N --- 1 filaments
plates    N --- N parts
products  N --- N parts
plates    1 --- N items
```

Regras de consistencia do dominio:

- Movimentos de filamento sao append-only e nunca devem ser apagados.
- Reabertura de placa nao remove historico: cria evento de compensacao.
- Saldo materializado de `filaments` deve refletir a soma do ledger.
- Processo de reconciliacao existe para corrigir divergencias residuais.

## Fluxo operacional (do zero ao produto pronto)

1. Cadastrar filamento em `Filaments -> Novo filamento`.
2. Cadastrar pecas em `Parts -> Nova peca`.
3. Cadastrar produto com pecas em `Products -> Novo produto`.
4. Criar placa com filamento e pecas em `Plates -> Nova placa`.
5. Avancar status da placa: `Criado -> Fila -> Imprimindo -> Concluido`.
6. Ao concluir, o sistema gera itens em estoque e decrementa saldo do filamento automaticamente.
7. Se necessario, reabrir placa para estorno automatico via evento `REVERSAL` no ledger.

Boas praticas de operacao:

- Evite pular status de placa para preservar rastreabilidade do processo.
- Registre consumos reais para melhorar previsibilidade de producao.
- Execute reconciliacao periodica em ambientes de homologacao.
- Mantenha codigos de pecas e produtos padronizados para busca rapida.

## Comandos de desenvolvimento

```bash
# Rodar testes
docker compose -f docker/docker-compose.yml --env-file .env exec app ./vendor/bin/pest

# Rodar testes com coverage
docker compose -f docker/docker-compose.yml --env-file .env exec app ./vendor/bin/pest --coverage --min=80

# Formatar codigo (Pint)
docker compose -f docker/docker-compose.yml --env-file .env exec app ./vendor/bin/pint

# Lint frontend
docker compose -f docker/docker-compose.yml --env-file .env exec node npm run lint

# Build de assets em watch (desenvolvimento)
docker compose -f docker/docker-compose.yml --env-file .env exec node npm run dev

# Reconciliar saldos com ledger
docker compose -f docker/docker-compose.yml --env-file .env exec app php artisan filaments:reconcile

# Aplicar correcoes de reconciliacao
docker compose -f docker/docker-compose.yml --env-file .env exec app php artisan filaments:reconcile --apply

# Re-seed (reset do banco de dev com dados demo)
docker compose -f docker/docker-compose.yml --env-file .env exec app php artisan migrate:fresh --seed
```

## Rodando o CI localmente

O pipeline de CI valida cenarios que dependem de MySQL,
incluindo validacoes de integridade que nao sao cobertas em SQLite in-memory.
Para reproduzir localmente, execute:

```bash
docker compose -f docker/docker-compose.yml --env-file .env exec mysql mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS gestao_producao_testing; GRANT ALL PRIVILEGES ON gestao_producao_testing.* TO 'laravel'@'%'; FLUSH PRIVILEGES;"
docker compose -f docker/docker-compose.yml --env-file .env exec app sh -c "DB_CONNECTION=mysql DB_HOST=mysql DB_DATABASE=gestao_producao_testing DB_USERNAME=laravel DB_PASSWORD=secret ./vendor/bin/pest --compact"
```

Notas operacionais:

- O banco `gestao_producao_testing` pode ser recriado quantas vezes necessario.
- O comando de teste acima sobrepoe variaveis de ambiente apenas para aquela execucao.
- O fluxo oficial de CI continua sendo a referencia para validacao final.

Diferenca entre execucao local e pipeline:

- Localmente, testes de desenvolvimento normalmente usam SQLite in-memory.
- No CI, a execucao em MySQL garante validacao de constraints SQL reais.
- Falhas em MySQL devem ser tratadas como prioritarias para merge.

## Estrutura do projeto (breve)

Arvore resumida dos diretorios principais:

```text
.
|- app/
|  |- Actions/
|  |  |- CreateFilament.php
|  |  |- CompletePlate.php
|  |  |- ReopenPlate.php
|  |  |- ReconcileFilaments.php
|  |- Models/
|  |- Http/Controllers/
|  |- Services/
|- resources/
|  |- js/
|  |  |- Pages/
|  |  |- Components/
|- database/
|  |- migrations/
|  |- seeders/
|- tests/
|  |- Feature/
|  |- Unit/
|- docker/
|- .github/workflows/
```

Referencias rapidas por area:

- `app/Actions/`: regras transacionais de criacao, conclusao, reabertura e reconciliacao.
- `app/Models/`: mapeamento das entidades de dominio e relacoes.
- `app/Http/Controllers/`: endpoints web e integracao com Inertia.
- `app/Services/`: servicos de apoio ao dominio e snapshots.
- `resources/js/Pages/` e `resources/js/Components/`: interface em Vue 3.
- `database/migrations/` e `database/seeders/`: schema, constraints e dados de bootstrap.
- `tests/Feature/` e `tests/Unit/`: cobertura funcional e unitaria.
- `docker/`: imagem e orquestracao de containers locais.
- `.github/workflows/`: automacoes de CI.

## Documentacao adicional

- [Spec funcional](specs/001-gestao-producao-3d/spec.md)
- [Plano tecnico](specs/001-gestao-producao-3d/plan.md)
- [Data model e schema](specs/001-gestao-producao-3d/data-model.md)
- [Quickstart de validacao end-to-end](specs/001-gestao-producao-3d/quickstart.md)
- [Checklist de aderencia a constituicao](specs/001-gestao-producao-3d/CONSTITUTION_CHECK.md)
- [Constituicao do projeto](.specify/memory/constitution.md)

## Licenca

Uso privado. Todos os direitos reservados.
