---
name: criar-pr
description: Preparar e abrir um pull request no GitHub para uma mudança deste repositório, incluindo branch, commit, push, atribuição ao autor e vínculo com a issue. Use quando solicitado publicar uma mudança como PR ou retomar esse fluxo após uma falha.
---

# Criar PR

Conduza a mudança solicitada até um PR pronto para revisão. Use a autorização já dada pelo usuário para commit, push e criação do PR; pedir apenas uma análise ou um rascunho não autoriza sua publicação. Respeite restrições explícitas da sessão.

## 1. Identificar a mudança e o destino

Leia `AGENTS.md`, o pedido e a issue com seus comentários pelo fluxo em [issue-tracker.md](../../../docs/agents/issue-tracker.md). Examine o estado do Git, inclusive alterações preparadas, não preparadas, arquivos novos, commits locais e remotos. Se houver merge ou rebase em andamento, informe o impedimento antes de alterar o Git.

Determine o repositório de destino, o remoto de push e a branch base a partir do pedido e das convenções existentes. Consulte a branch padrão no GitHub quando não houver destino indicado; não presuma `main` nem que `origin` seja o destino em um fork. Atualize as referências remotas e examine os commits e o diff desde a base. Se faltarem a issue ou informações indispensáveis para distinguir os destinos, pergunte somente o que não puder obter do contexto.

Use um conector GitHub disponível ou `gh` autenticado. Git local continua responsável por branch, commit e push. Verifique as operações disponíveis antes de iniciar; a ausência de `gh` não impede usar o conector. Se não houver meio autenticado para uma etapa, conclua o trabalho independente autorizado e registre a pendência.

**Concluído quando:** escopo, issue, base, repositórios de origem/destino e meios de execução estão identificados.

## 2. Escolher a branch

Reutilize a branch atual quando seu vínculo com a mudança estiver demonstrado pelo contexto, pela issue ou pelo PR existente, e pelos commits e diff em relação à base. O nome ajuda, mas estar fora da branch padrão não prova que ela é dedicada. Uma branch recém-criada para a issue pode ainda não ter commits próprios.

Se estiver na base ou em HEAD destacado, crie uma branch dedicada com o nome pedido ou `codex/<numero>-<resumo>`, preservando as alterações pertinentes. Ao partir de outra branch de trabalho, não leve seus commits alheios para o novo PR: use um checkout isolado a partir da base e transfira apenas os commits ou trechos pertinentes, preservando o trabalho original. Se não conseguir separá-los com segurança, explique a ambiguidade e peça a informação necessária. Verifique colisões de nomes antes de criar a branch.

Consulte PRs da mesma origem e destino. Reutilize um PR aberto da mesma mudança; um PR fechado ou integrado não é um PR aberto a atualizar. Se a mudança já estiver integrada, informe isso antes de tentar criar outro PR.

**Concluído quando:** a branch contém somente a linha de trabalho pretendida e o PR existente, se houver, está identificado.

## 3. Preparar o commit

Selecione arquivos e, quando necessário, trechos relacionados ao pedido. Examine também o que já estava no índice: alterações alheias preparadas pelo usuário devem permanecer fora do commit e preservadas para ele. Use seleção por trechos ou um índice temporário quando um arquivo misturar trabalhos; não inclua o arquivo inteiro por conveniência. Preserve tanto o conteúdo quanto a preparação prévia do trabalho alheio.

Execute as verificações adequadas à mudança conforme `AGENTS.md` e a configuração do projeto. Para alterações somente documentais, valide as instruções e referências; para alterações do plugin, use os testes pertinentes. Registre o resultado real e qualquer verificação indisponível.

Confira o diff exato que será registrado, faça o commit com mensagem que descreva a mudança e confira seu conteúdo. Se tudo já estiver em commits pertinentes, reutilize-os sem criar um commit vazio. Examine também todos os commits que o PR apresentará em relação à base; um último commit correto não elimina commits alheios anteriores.

**Concluído quando:** os commits contêm apenas o escopo solicitado, as verificações estão registradas e o trabalho alheio está preservado.

## 4. Enviar a branch e abrir o PR

Envie a branch ao remoto de origem confirmado e configure seu upstream quando necessário. Verifique que o SHA remoto corresponde ao commit pretendido. Em caso de rejeição por divergência, leia o estado remoto e informe o conflito; não faça force-push nem reescreva histórico alheio para concluir esta skill.

Antes de criar o PR, consulte novamente se já existe um PR aberto para essa origem e destino. Prepare título e descrição a partir do diff completo: problema, comportamento resultante, validação realizada e limitações relevantes. Use o template do repositório, se houver. Preserve conteúdo humano ao atualizar um PR existente. Envie a descrição como argumento estruturado do conector ou arquivo com `gh ... --body-file`, preservando as quebras de linha.

Vincule a issue na descrição:

- Quando o PR resolver a issue e tiver como destino a branch padrão, use `Closes #N`; para outro repositório, `Closes proprietario/repositorio#N`.
- Para entrega parcial ou destino diferente da branch padrão, inclua `Refs proprietario/repositorio#N` ou o link completo da issue. Isso registra a referência sem prometer fechamento automático. Como essa referência não cria o vínculo formal, tente vincular o PR à issue pela operação disponível na seção Development do GitHub ou por um conector e verifique o resultado. Se não houver meio de criar o vínculo, informe essa pendência.

As palavras-chave de fechamento só são interpretadas em PRs destinados à branch padrão. Consulte a [documentação do GitHub](https://docs.github.com/en/issues/tracking-your-work-with-issues/using-issues/linking-a-pull-request-to-an-issue) se houver dúvida sobre o vínculo.

Crie o PR com origem e base explícitas, ou atualize o PR aberto identificado. Leia o autor retornado pelo GitHub e adicione esse login aos responsáveis, preservando os existentes. O autor do PR pode ser diferente do autor do commit, do autor da issue e do usuário desta sessão. Se a conta autora não puder ser atribuída, informe a limitação sem escolher outra pessoa por conta própria.

Quando disponível, anexe o PR à tarefa pelo recurso do ambiente. Esta skill termina na abertura ou atualização do PR; não inclui merge.

**Concluído quando:** PR, origem, destino, título, descrição, atribuição ao autor e referência ou vínculo da issue foram conferidos no GitHub.

## 5. Relatar e retomar

Retorne o link do PR, branch, commit, validações e pendências. Diferencie referência textual, vínculo formal e fechamento automático da issue.

Em falha parcial, diga qual etapa falhou, o motivo observado e o que já foi persistido: commit local, branch remota, PR e atribuição. Interrompa etapas dependentes da falha. Se uma operação retornar resultado incerto, consulte o estado antes de repeti-la. Na retomada, continue da primeira etapa incompleta: reutilize commits, branch e PR confirmados, evitando duplicação. Não anuncie sucesso de atribuição, vínculo ou publicação sem confirmação.
