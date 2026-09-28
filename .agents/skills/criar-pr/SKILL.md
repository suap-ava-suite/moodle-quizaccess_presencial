---
name: criar-pr
description: Preparar e abrir um pull request no GitHub para uma mudança deste repositório, incluindo branch, commit, push, atribuição ao autor e vínculo com a issue. Use quando solicitado publicar uma mudança como PR ou retomar esse fluxo após uma falha.
---

# Criar PR

Leve a mudança autorizada até um pull request pronto para revisão. Um pedido de análise ou rascunho não autoriza commit, push ou publicação.

1. **Entenda a mudança.** Leia o pedido, a issue e seus comentários, `AGENTS.md` e o estado completo do Git. Identifique repositório, remoto e branch base; confira commits e alterações desde a base. Reutilize a branch atual quando ela estiver claramente ligada à mudança; caso contrário, crie uma branch dedicada a partir da base. Confira se já existe um PR aberto para essa origem e destino.
2. **Prepare a mudança.** Inclua somente arquivos e commits pertinentes, preservando alterações alheias. Execute as verificações adequadas ao escopo. Faça um commit descritivo se ainda não houver commits próprios; não crie commit vazio.
3. **Envie a branch.** Faça push ao remoto de origem e confirme que o SHA remoto corresponde ao commit esperado. Não force o push. Se houver divergência, explique o conflito antes de continuar.
4. **Abra ou atualize o PR.** Informe origem e destino explicitamente. Escreva um título e uma descrição que resumam a mudança, a validação e limitações relevantes. Para fechar uma issue no mesmo repositório, use `Closes #N` quando o PR tiver como destino a branch padrão; para outra origem, use `Closes proprietario/repositorio#N`. Em entrega parcial ou destino diferente da branch padrão, use `Refs` ou o link da issue e tente criar o vínculo formal pela seção Development ou por um conector disponível. Atribua o PR ao login do autor retornado pelo GitHub, preservando responsáveis existentes.
5. **Confira e relate.** Verifique o PR, as branches, o commit, a atribuição e a referência ou vínculo da issue. Informe o link, validações e qualquer etapa que não conseguiu concluir. Se uma operação falhar, retome do primeiro passo incompleto sem duplicar commits ou PRs. A skill termina na abertura ou atualização do PR; não inclui merge.

Use o conector GitHub disponível ou `gh` autenticado para operações no GitHub; Git local continua responsável por branch, commit e push. Se faltar acesso autenticado, conclua as etapas possíveis e informe a pendência.
