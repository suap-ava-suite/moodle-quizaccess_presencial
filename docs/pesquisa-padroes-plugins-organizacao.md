# Pesquisa de padrões nos plugins Moodle da organização `suap-ava-suite`

## Objetivo e método

Esta pesquisa foi feita em 19 de setembro de 2026, antes de qualquer implementação da issue 3 do `moodle-quizaccess_presencial`. Foram inspecionados somente fontes primárias: código, documentação e workflows versionados nos seis repositórios indicados. Cada referência abaixo usa o hash exato analisado, para que mudanças futuras na branch `main` não alterem a evidência.

As skills do plugin Moodle Dev sobre desenvolvimento de plugins, PHPUnit, Behat e privacidade foram usadas como grade de avaliação. Elas não foram tratadas como evidência sobre o estado dos repositórios; servem para separar padrões seguros de precedentes legados ou incompletos.

## Revisões analisadas

| Repositório | Revisão permanente | Tipo principal |
|---|---|---|
| `moodle-auth_suap` | [`8aad90f`](https://github.com/suap-ava-suite/moodle-auth_suap/tree/8aad90fc118371eb84c02c1524744809b7ab76af) | `auth_suap` |
| `moodle-local_suap` | [`e9be790`](https://github.com/suap-ava-suite/moodle-local_suap/tree/e9be790ae6a0256b26f72cfc768e48e304631a84) | `local_suap` |
| `moodle-tool_painelava` | [`edebe9f`](https://github.com/suap-ava-suite/moodle-tool_painelava/tree/edebe9f9c6589c3cbab996c681e0231c3f83eecf) | `tool_painelava` |
| `moodle-tool_sga` | [`b65add1`](https://github.com/suap-ava-suite/moodle-tool_sga/tree/b65add13dc81d8bfd344313baabd3032ebcba66e) | `tool_sga` |
| `moodle-tool_authors` | [`cc56bd6`](https://github.com/suap-ava-suite/moodle-tool_authors/tree/cc56bd69e378795d9e433af25879915843da1ba1) | `tool_authors`, com subplugins `filter_authors` e `block_authors` |
| `moodle-mod_attendance_suap` | [`4b03c06`](https://github.com/suap-ava-suite/moodle-mod_attendance_suap/tree/4b03c0604c15c0ce2ef5e627f95302075c28d378) | `mod_attendance_suap` |

## Síntese executiva

Os quatro repositórios com manutenção mais padronizada (`auth_suap`, `local_suap`, `tool_painelava` e `tool_sga`) compartilham:

- `version.php` com `component`, `release`, `version`, `requires` e `maturity`;
- cabeçalhos GPL v3 ou posterior nos arquivos PHP e arquivo `LICENSE` completo;
- README curto e bilíngue que aponta para documentação Sphinx em português e inglês;
- traduções com o mesmo conjunto de chaves em `en`, `pt_br`, `es`, `fr`, `nl` e `zh_cn`;
- CI baseado em `moodlehq/moodle-plugin-ci ^4`, PostgreSQL e MariaDB;
- workflows separados para CI, documentação e release;
- classes autocarregáveis sob o namespace Frankenstyle do componente, embora ainda existam classes legadas fora de namespace.

O melhor precedente de cobertura de versões e bancos é o [`tool_painelava`](https://github.com/suap-ava-suite/moodle-tool_painelava/blob/edebe9f9c6589c3cbab996c681e0231c3f83eecf/.github/workflows/ci.yml): Moodle 4.5, 5.0 e 5.1; PHP 8.1 a 8.4, com exclusões de combinações incompatíveis; PostgreSQL e MariaDB. O melhor precedente de teste focado em regressão é o [`auth_suap`](https://github.com/suap-ava-suite/moodle-auth_suap/blob/8aad90fc118371eb84c02c1524744809b7ab76af/tests/auth_test.php), enquanto o [`tool_painelava`](https://github.com/suap-ava-suite/moodle-tool_painelava/blob/edebe9f9c6589c3cbab996c681e0231c3f83eecf/tests/external/external_test.php) é o melhor exemplo de teste de permissão e contrato de serviço externo.

Não há um único repositório que possa ser copiado integralmente como modelo. Há lacunas recorrentes em privacidade, Behat, cobertura de testes e consistência entre metadados e CI. A linha recomendada é compor os bons padrões da organização e, onde houver conflito, decidir explicitamente se prevalece o precedente local ou a orientação das skills Moodle Dev.

## Comparação geral

| Repositório | Licença no topo | Namespace/i18n | CI | PHPUnit/Behat | Privacy API | README/CHANGELOG |
|---|---|---|---|---|---|---|
| `auth_suap` | GPL-3.0 completa | PSR-4 `auth_suap`; 6 idiomas, 53 chaves em cada | PHP 8.3; Moodle 4.5; PostgreSQL/MariaDB | 1 arquivo, 7 testes; sem Behat | Declara dados enviados ao SUAP | README bilíngue curto + Sphinx; sem CHANGELOG |
| `local_suap` | GPL-3.0 completa | PSR-4 parcial; 6 idiomas, 145 chaves em cada | PHP 8.3; Moodle 4.4/4.5; PostgreSQL/MariaDB | Sem testes; sem Behat | Ausente | README bilíngue curto + Sphinx; sem CHANGELOG |
| `tool_painelava` | GPL-3.0 completa | PSR-4 `tool_painelava`; 6 idiomas, 15 chaves em cada | PHP 8.1–8.4; Moodle 4.5/5.0/5.1; PostgreSQL/MariaDB | 1 arquivo, 12 testes; sem Behat | Ausente, apesar de tabela com IDs e IPs | README bilíngue curto + Sphinx; sem CHANGELOG |
| `tool_sga` | GPL-3.0 completa | PSR-4 parcial; 6 idiomas, 17 chaves em cada | PHP 8.3; Moodle 4.5; PostgreSQL/MariaDB | Sem testes; sem Behat | Ausente | README bilíngue + Sphinx; sem CHANGELOG |
| `tool_authors` | Somente declaração no README/cabeçalhos; sem `LICENSE` | PSR-4 principal; apenas inglês, 35 chaves | Ausente | Sem testes; sem Behat | Parcial: metadados/exportação, sem `core_userlist_provider`; deleção vazia | README detalhado em português; sem CHANGELOG |
| `mod_attendance_suap` | Somente declaração no README/cabeçalhos; sem `LICENSE` | PSR-4 `mod_attendance_suap`; inglês e `pt_br`, 57 chaves em cada | Ausente | 2 arquivos, 7 testes; sem Behat | Ausente, apesar de armazenar progresso por usuário | README detalhado em inglês; sem CHANGELOG |

Os totais de chaves foram obtidos diretamente dos arquivos em `lang/` da revisão indicada. Nos repositórios multilíngues, a contagem é idêntica em todos os idiomas, um padrão útil para detectar traduções incompletas.

## 1. Esqueleto, metadados, licença, namespace e i18n

### Metadados

Todos os seis plugins principais declaram os cinco metadados centrais em `version.php`. Exemplos:

- [`auth_suap/version.php`](https://github.com/suap-ava-suite/moodle-auth_suap/blob/8aad90fc118371eb84c02c1524744809b7ab76af/version.php) usa `auth_suap`, release `4.5.082`, maturidade estável e Moodle 4.5;
- [`tool_painelava/version.php`](https://github.com/suap-ava-suite/moodle-tool_painelava/blob/edebe9f9c6589c3cbab996c681e0231c3f83eecf/version.php) usa release `4.5.020`, versão com separadores numéricos e Moodle 4.5;
- [`mod_attendance_suap/version.php`](https://github.com/suap-ava-suite/moodle-mod_attendance_suap/blob/4b03c0604c15c0ce2ef5e627f95302075c28d378/version.php) usa o formato Moodle convencional `YYYYMMDDXX` e release semântica `1.0.0`.

Há variação suficiente para não inferir uma convenção única de release. `auth_suap`, `local_suap`, `tool_painelava` e `tool_sga` codificam a linha Moodle no release (`4.5.xxx`); `attendance_suap` e `tool_authors` usam `1.0.0`. Para o novo plugin, a convenção precisa ser escolhida e aplicada também no workflow de release.

Há duas inconsistências que não devem ser reproduzidas:

- [`local_suap/version.php`](https://github.com/suap-ava-suite/moodle-local_suap/blob/e9be790ae6a0256b26f72cfc768e48e304631a84/version.php) declara `requires = 2021051700` (Moodle 3.11), mas a CI só testa 4.4 e 4.5;
- [`tool_authors/version.php`](https://github.com/suap-ava-suite/moodle-tool_authors/blob/cc56bd69e378795d9e433af25879915843da1ba1/version.php) usa `2024042200`, correspondente a Moodle 4.4, acompanhado do comentário “Moodle 4.5”.

O README do [`attendance_suap`](https://github.com/suap-ava-suite/moodle-mod_attendance_suap/blob/4b03c0604c15c0ce2ef5e627f95302075c28d378/README.md) também informa PHP 7.4 para um plugin que exige Moodle 4.4, outro exemplo de documentação e requisito que precisam ser validados em conjunto.

### Licença

`auth_suap`, `local_suap`, `tool_painelava` e `tool_sga` contêm o texto integral da GPL v3 no arquivo `LICENSE` e repetem o cabeçalho Moodle nos PHP. `tool_authors` e `mod_attendance_suap` declaram GPL v3 no README e nos cabeçalhos PHP, mas não têm `LICENSE` no topo de suas revisões. O padrão mais completo para reutilizar é manter ambos: arquivo `LICENSE` integral e cabeçalho GPL em cada PHP.

### Namespace e organização

O padrão predominante é namespace igual ao componente Frankenstyle e subnamespaces que seguem `classes/`:

- [`auth_suap/classes/task`](https://github.com/suap-ava-suite/moodle-auth_suap/tree/8aad90fc118371eb84c02c1524744809b7ab76af/classes/task), `auth_suap\task`;
- [`tool_painelava/classes/external/get_user_courses.php`](https://github.com/suap-ava-suite/moodle-tool_painelava/blob/edebe9f9c6589c3cbab996c681e0231c3f83eecf/classes/external/get_user_courses.php), `tool_painelava\external`;
- [`mod_attendance_suap/classes/form`](https://github.com/suap-ava-suite/moodle-mod_attendance_suap/tree/4b03c0604c15c0ce2ef5e627f95302075c28d378/classes/form), `mod_attendance_suap\form`.

Há código legado a evitar como modelo: [`local_suap/classes/observer.php`](https://github.com/suap-ava-suite/moodle-local_suap/blob/e9be790ae6a0256b26f72cfc768e48e304631a84/classes/observer.php) e [`tool_sga/classes/observer.php`](https://github.com/suap-ava-suite/moodle-tool_sga/blob/b65add13dc81d8bfd344313baabd3032ebcba66e/classes/observer.php) mantêm nomes de classe prefixados sem namespace dentro de `classes/`. O plugin novo deve usar PSR-4 nas classes autocarregáveis e reservar funções Frankenstyle globais aos callbacks exigidos pelo tipo de plugin.

`tool_authors` é um caso especial: o repositório agrega um admin tool, um filtro e um bloco, cada subplugin com metadados e arquivos de idioma próprios. Esse formato serve de referência apenas quando a entrega realmente exigir múltiplos componentes instaláveis; não deve ser adotado para um único access rule.

### Internacionalização

Os quatro repositórios com Sphinx mantêm seis idiomas com paridade de chaves: inglês, português brasileiro, espanhol, francês, neerlandês e chinês simplificado. `attendance_suap` mantém inglês e português brasileiro; `tool_authors`, apenas inglês, embora o README esteja em português.

Padrão mínimo seguro para o novo plugin:

- inglês como idioma-base exigido pelo ecossistema Moodle;
- `pt_br` para o público da organização;
- toda mensagem visível obtida por `get_string()` ou helper Mustache;
- paridade automatizável das chaves entre idiomas, caso sejam adicionadas as demais traduções da organização.

## 2. CI, matrizes e `moodle-plugin-ci`

### Matrizes encontradas

| Workflow | Runner | PHP | Moodle | Banco |
|---|---|---|---|---|
| [`auth_suap`](https://github.com/suap-ava-suite/moodle-auth_suap/blob/8aad90fc118371eb84c02c1524744809b7ab76af/.github/workflows/ci.yml) | Ubuntu 22.04 | 8.3 | 4.5 | PostgreSQL 15, MariaDB 10 |
| [`local_suap`](https://github.com/suap-ava-suite/moodle-local_suap/blob/e9be790ae6a0256b26f72cfc768e48e304631a84/.github/workflows/ci.yml) | Ubuntu latest | 8.3 | 4.4, 4.5 | PostgreSQL 13, MariaDB 10.6 |
| [`tool_painelava`](https://github.com/suap-ava-suite/moodle-tool_painelava/blob/edebe9f9c6589c3cbab996c681e0231c3f83eecf/.github/workflows/ci.yml) | Ubuntu latest | 8.1, 8.2, 8.3, 8.4 | 4.5, 5.0, 5.1 | PostgreSQL 15, MariaDB 10.11 |
| [`tool_sga`](https://github.com/suap-ava-suite/moodle-tool_sga/blob/b65add13dc81d8bfd344313baabd3032ebcba66e/.github/workflows/ci.yml) | Ubuntu latest | 8.3 | 4.5 | PostgreSQL 13, MariaDB 10.6 |
| `tool_authors` | — | — | — | — |
| `mod_attendance_suap` | — | — | — | — |

Todos os quatro workflows existentes instalam `moodlehq/moodle-plugin-ci` na linha `^4` e executam, com pequenas variações:

- `phplint`;
- `phpcpd` e/ou `phpmd`, normalmente informativos;
- `codechecker` ou `phpcs`;
- `phpdoc`;
- `validate`;
- `savepoints`;
- `mustache` e `grunt`;
- `phpunit`;
- `behat`.

O workflow de [`auth_suap`](https://github.com/suap-ava-suite/moodle-auth_suap/blob/8aad90fc118371eb84c02c1524744809b7ab76af/.github/workflows/ci.yml) tem dois refinamentos úteis: `fail-fast: false` e detecção prévia da existência de `tests/behat`, instalando Chrome e rodando Behat somente quando houver features. O [`tool_painelava`](https://github.com/suap-ava-suite/moodle-tool_painelava/blob/edebe9f9c6589c3cbab996c681e0231c3f83eecf/.github/workflows/ci.yml) oferece a matriz mais abrangente e explicita exclusões PHP/Moodle inválidas.

As diferenças de severidade precisam ser normalizadas no plugin novo:

- em `local_suap`, `codechecker` é `continue-on-error`, enquanto em `tool_sga` e `tool_painelava` bloqueia a build;
- em `tool_painelava`, PHPDoc, Mustache, Grunt e Behat não bloqueiam; em outros workflows parte desses checks bloqueia;
- `auth_suap` usa `moodle-plugin-ci phpcs --max-warnings 100`; os demais usam `codechecker`, em geral com zero warnings.

Recomendação de composição: matriz compatível com o `requires` escolhido, PostgreSQL e MariaDB, `fail-fast: false`, checks de sintaxe/coding style/validação/savepoints/PHPUnit bloqueantes e Behat condicional, também bloqueante quando houver cenários. Ferramentas heurísticas como PHPMD/PHPCPD podem começar informativas.

## 3. PHPUnit, Behat e padrões de teste

### Cobertura existente

- [`auth_suap/tests/auth_test.php`](https://github.com/suap-ava-suite/moodle-auth_suap/blob/8aad90fc118371eb84c02c1524744809b7ab76af/tests/auth_test.php): 7 testes. Cobre regressões concretas de redirecionamento, consumo do estado de sessão, resolução de e-mail, criação/atualização de usuário e degradação controlada quando uma integração falha. Usa classe `final`, `@covers`, tipos de retorno e `resetAfterTest()`.
- [`tool_painelava/tests/external/external_test.php`](https://github.com/suap-ava-suite/moodle-tool_painelava/blob/edebe9f9c6589c3cbab996c681e0231c3f83eecf/tests/external/external_test.php): 12 testes. Cobre capability ao acessar dados de outro usuário, usuário excluído, classificação de cursos, estrutura do retorno, papéis e coleções vazias. Usa dados reais do harness Moodle e classe `final` com `@covers`.
- [`mod_attendance_suap/tests/lib_test.php`](https://github.com/suap-ava-suite/moodle-mod_attendance_suap/blob/4b03c0604c15c0ce2ef5e627f95302075c28d378/tests/lib_test.php) e [`observer_test.php`](https://github.com/suap-ava-suite/moodle-mod_attendance_suap/blob/4b03c0604c15c0ce2ef5e627f95302075c28d378/tests/observer_test.php): 7 testes no total. Exercitam callbacks CRUD, cálculo de progresso e observers com cursos, usuários, matrículas e módulos criados pelos data generators.
- `local_suap`, `tool_sga` e `tool_authors`: nenhum arquivo PHPUnit na revisão analisada.
- Nenhum dos seis repositórios contém feature Behat.

### Padrões que valem reutilizar

- testes de autorização positivos e negativos, como no `tool_painelava`;
- cenários de regressão nomeados pelo comportamento, como no `auth_suap`;
- `advanced_testcase`, isolamento por `resetAfterTest()`, `$this->setUser()` e data generators Moodle;
- assertivas sobre efeitos persistidos e não apenas valores retornados, como no `attendance_suap`;
- `@covers` e classes de teste `final` para os testes novos;
- teste unitário/integrado do domínio e Behat apenas para jornadas que dependam de navegador, JavaScript ou ciclo HTTP completo.

### Pontos a corrigir ao reutilizar

As skills Moodle Dev divergem de alguns exemplos locais:

- `auth_suap` e `tool_painelava` chamam `parent::setUp()` e depois `resetAfterTest()`. A skill de PHPUnit orienta não chamar `parent::setUp()` para limpeza e usar apenas `resetAfterTest()`;
- os testes de `attendance_suap` não são `final`, não usam `@covers` e vários métodos não têm `: void`;
- os três conjuntos usam `$this->getDataGenerator()`, enquanto a skill prefere `self::getDataGenerator()` para o método estático;
- o teste da função externa em `tool_painelava` chama `execute()` diretamente, sem validar o resultado com `external_api::clean_returnvalue()`, que a skill considera obrigatório para detectar divergências do schema de retorno.

## 4. Lint, codechecker e critérios de falha

O padrão organizacional é executar a cadeia completa do `moodle-plugin-ci`, e não manter uma configuração Composer própria em cada plugin. Isso reduz arquivos de infraestrutura no plugin e garante que o código seja verificado dentro de uma instalação Moodle real.

Para o novo plugin, a combinação mais consistente com as skills e os melhores precedentes é:

1. `phplint` bloqueante;
2. `codechecker` ou `phpcs` Moodle bloqueante, sem tolerância planejada para warnings novos;
3. `phpdoc`, `validate` e `savepoints` bloqueantes;
4. `mustache` e `grunt` bloqueantes quando houver esses artefatos;
5. PHPUnit bloqueante com `--fail-on-warning`;
6. Behat bloqueante quando existirem features;
7. PHPMD/PHPCPD inicialmente informativos, pois esse é o padrão predominante da organização.

Os arquivos [`.pre-commit-config.yaml` do `auth_suap`](https://github.com/suap-ava-suite/moodle-auth_suap/blob/8aad90fc118371eb84c02c1524744809b7ab76af/.pre-commit-config.yaml), [`local_suap`](https://github.com/suap-ava-suite/moodle-local_suap/blob/e9be790ae6a0256b26f72cfc768e48e304631a84/.pre-commit-config.yaml), [`tool_painelava`](https://github.com/suap-ava-suite/moodle-tool_painelava/blob/edebe9f9c6589c3cbab996c681e0231c3f83eecf/.pre-commit-config.yaml) e [`tool_sga`](https://github.com/suap-ava-suite/moodle-tool_sga/blob/b65add13dc81d8bfd344313baabd3032ebcba66e/.pre-commit-config.yaml) executam o workflow com `act`. É um padrão reutilizável como conveniência local, mas não substitui a GitHub Action.

## 5. Privacidade

### O que existe

O [`auth_suap`](https://github.com/suap-ava-suite/moodle-auth_suap/blob/8aad90fc118371eb84c02c1524744809b7ab76af/classes/privacy/provider.php) implementa `core_privacy\local\metadata\provider` e declara uma localização externa `suap`, com username, e-mail, nome, CPF e tipo de usuário. É o melhor exemplo da organização para integração que envia dados a um serviço externo.

O [`tool_authors`](https://github.com/suap-ava-suite/moodle-tool_authors/blob/cc56bd69e378795d9e433af25879915843da1ba1/classes/privacy/provider.php) declara a tabela `tool_authors_log`, encontra contextos e exporta registros. Porém:

- não implementa `core_privacy\local\request\core_userlist_provider`;
- os métodos de deleção são vazios, justificando retenção permanente em comentários;
- não há testes do provider.

Os demais quatro plugins não possuem `classes/privacy/provider.php`. A ausência é especialmente relevante em:

- [`tool_painelava/db/install.xml`](https://github.com/suap-ava-suite/moodle-tool_painelava/blob/edebe9f9c6589c3cbab996c681e0231c3f83eecf/db/install.xml), cuja tabela de logging armazena `userid`, `targetuserid` e endereços IP;
- [`mod_attendance_suap/db/install.xml`](https://github.com/suap-ava-suite/moodle-mod_attendance_suap/blob/4b03c0604c15c0ce2ef5e627f95302075c28d378/db/install.xml), cuja tabela de progresso armazena `userid`.

### Orientação das skills Moodle Dev

As skills consideram obrigatório que todo plugin declare privacidade:

- `null_provider` quando não armazena dados pessoais;
- provider completo de metadata/request/userlist quando mantém dados próprios por usuário;
- declaração de localização externa quando envia dados para outro sistema;
- testes baseados em `core_privacy\tests\provider_testcase` para providers completos.

Portanto, a maioria numérica dos repositórios da organização conflita com a orientação Moodle Dev. O padrão tecnicamente recomendado para `quizaccess_presencial` é implementar o provider adequado ao modelo de dados da issue, e não repetir a ausência encontrada em quatro repositórios.

## 6. README, documentação, release e CHANGELOG

`auth_suap`, `local_suap`, `tool_painelava` e `tool_sga` usam README curto, bilíngue, com links para documentação Sphinx em `docs/pt-br` e `docs/en`. Seus workflows [`docs.yml`](https://github.com/suap-ava-suite/moodle-auth_suap/blob/8aad90fc118371eb84c02c1524744809b7ab76af/.github/workflows/docs.yml) constroem ambos os idiomas com warnings como erro e publicam no GitHub Pages.

`mod_attendance_suap` e `tool_authors` mantêm README autocontido e detalhado, incluindo recursos, instalação, capabilities e requisitos, mas apenas em um idioma. Para um plugin novo, o modelo autocontido é melhor durante a primeira entrega; documentação Sphinx separada só se justifica quando o volume crescer.

Nenhum dos seis repositórios tem `CHANGELOG` na revisão analisada. Os workflows de release dos quatro repositórios mais padronizados geram notas do GitHub e ZIP instalável. Exemplo: [`auth_suap/release.yml`](https://github.com/suap-ava-suite/moodle-auth_suap/blob/8aad90fc118371eb84c02c1524744809b7ab76af/.github/workflows/release.yml) valida a correspondência entre tag, `$plugin->release` e sufixo de `$plugin->version`, e empacota o diretório com o shortname esperado pelo Moodle.

Padrão recomendado:

- README com objetivo, compatibilidade, instalação, configuração, capabilities, fluxo de uso, testes e privacidade;
- `LICENSE` completo;
- notas de release geradas pelo GitHub, aceitando a ausência de CHANGELOG se a equipe confirmar que esse é o padrão desejado;
- ZIP com exatamente o diretório `presencial/` no topo para instalação em `mod/quiz/accessrule/`.

## 7. Padrões reutilizáveis para `quizaccess_presencial`

Sem antecipar a implementação da issue, os seguintes padrões podem ser incorporados ao plano:

1. **Identidade Moodle correta:** componente `quizaccess_presencial`, namespace homônimo e layout próprio de access rule; metadados completos e coerentes com a matriz suportada.
2. **Licenciamento completo:** cabeçalho Moodle em todo PHP e `LICENSE` GPL-3.0 integral.
3. **i18n desde o início:** `lang/en/quizaccess_presencial.php` e `lang/pt_br/quizaccess_presencial.php` com paridade de chaves e nenhuma string visível hard-coded.
4. **Classes pequenas sob `classes/`:** PSR-4 para domínio, eventos, tarefas e privacy; callbacks globais somente onde a API do tipo de plugin exigir.
5. **CI baseada em `moodle-plugin-ci ^4`:** PostgreSQL e MariaDB, versões Moodle/PHP alinhadas a `requires`, coding style e testes bloqueantes.
6. **TDD em seams observáveis:** autorização, regras temporais, transições de estado, persistência e segurança; data generators Moodle para montar quiz, usuários, papéis e tentativas.
7. **Behat seletivo:** jornadas completas que dependam de UI ou JavaScript, sem duplicar regras já cobertas em PHPUnit.
8. **Privacy API explícita:** provider e testes escolhidos a partir dos campos realmente persistidos e de eventuais dados expostos em links/QR codes.
9. **Documentação instalável:** README bilíngue ou, no mínimo, inglês + português na mesma página; instruções de instalação específicas para `mod/quiz/accessrule/presencial`.
10. **Release reproduzível:** ZIP com nome/componente/versão validados e sem artefatos de desenvolvimento.

## 8. Conflitos que exigem decisão antes de implementar

O pedido original determina que conflitos entre padrões existentes e as skills Moodle Dev sejam submetidos ao usuário. Foram encontrados três conflitos diretos:

1. **Privacy provider**
   - Precedente predominante da organização: 4 de 6 plugins não têm provider.
   - Moodle Dev: todo plugin deve ter ao menos `null_provider`; dados próprios por usuário exigem provider completo.
   - Pergunta necessária: devemos seguir Moodle Dev e implementar o provider adequado, mesmo divergindo da maioria dos plugins da organização?

2. **Preparação de testes PHPUnit**
   - Precedente mais recente: `auth_suap` e `tool_painelava` chamam `parent::setUp()`, usam `$this->getDataGenerator()` e depois `resetAfterTest()`.
   - Moodle Dev: não chamar `parent::setUp()` para limpeza, usar `resetAfterTest()` e preferir `self::getDataGenerator()`.
   - Pergunta necessária: devemos seguir a forma indicada pela skill nos testes novos, em vez de copiar literalmente esses dois repositórios?

3. **Validação do retorno de external functions**
   - Precedente local: `tool_painelava` testa `execute()` sem `external_api::clean_returnvalue()`.
   - Moodle Dev: a limpeza/validação do retorno é obrigatória para detectar incompatibilidade com `execute_returns()`.
   - Pergunta necessária: caso a issue resulte em external functions, devemos tornar `clean_returnvalue()` obrigatório nos testes?

Os demais contrastes são variações internas, não conflitos com as skills: abrangência da matriz, `codechecker` versus `phpcs`, severidade de ferramentas heurísticas, quantidade de idiomas e README autocontido versus Sphinx. Para esses pontos, o plano pode adotar os precedentes mais robustos sem bloquear a decisão técnica.

## Conclusão

A base mais segura para o novo plugin é uma composição: metadados e empacotamento dos quatro repositórios mais padronizados; matriz e testes de autorização do `tool_painelava`; testes de regressão do `auth_suap`; uso de data generators e assertivas de persistência do `attendance_suap`; e, nos conflitos identificados, as práticas Moodle Dev após confirmação explícita do usuário.
