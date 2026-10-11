# Rastreamento de trabalho no GitHub

As issues e especificações deste repositório ficam no GitHub Issues de `suap-ava-suite/moodle-quizaccess_presencial`. O quadro de acompanhamento é https://github.com/orgs/suap-ava-suite/projects/3.

Use a CLI `gh`. Ao consultar o projeto da organização, identifique as issues deste repositório pelo endereço completo.

## Operações

- Consultar o quadro: `gh project view 3 --owner suap-ava-suite`.
- Listar os itens do quadro: `gh project item-list 3 --owner suap-ava-suite --format json`.
- Ler uma issue, incluindo comentários: `gh issue view <número> --repo suap-ava-suite/moodle-quizaccess_presencial --comments`.
- Listar issues abertas: `gh issue list --repo suap-ava-suite/moodle-quizaccess_presencial --state open --json number,title,body,labels,assignees`.
- Criar uma issue: `gh issue create --repo suap-ava-suite/moodle-quizaccess_presencial --title "<título>" --body-file <arquivo>`.
- Incluir a issue no quadro: `gh project item-add 3 --owner suap-ava-suite --url <endereço-da-issue>`.
- Comentar: `gh issue comment <número> --repo suap-ava-suite/moodle-quizaccess_presencial --body-file <arquivo>`.
- Aplicar ou remover etiquetas: `gh issue edit <número> --repo suap-ava-suite/moodle-quizaccess_presencial --add-label "<etiqueta>"` ou `--remove-label "<etiqueta>"`.
- Encerrar: `gh issue close <número> --repo suap-ava-suite/moodle-quizaccess_presencial`.

Use arquivos UTF-8 para corpos com várias linhas. Consulte as etiquetas em `triage-labels.md`.

## Publicação e leitura pelas habilidades

“Publicar no rastreador” significa criar uma issue neste repositório e incluí-la no projeto nº 3.

“Buscar o ticket relevante” significa ler a issue e seus comentários.

Se a inclusão no projeto falhar, conserve a issue criada, informe seu endereço e a falha de inclusão. Em uma nova tentativa, inclua a mesma issue.

A leitura de projetos exige o escopo `read:project`; alterações exigem `project`. Quando a autenticação for insuficiente, informe a operação impedida.

## Pull requests

Pull requests como fonte de solicitações de triagem: não.

Issues e pull requests compartilham a numeração no GitHub. Para referências ambíguas, consulte `gh pr view <número>` e, se não for um pull request, `gh issue view <número>`.

## Organização por mapa de trabalho

Quando uma habilidade usar um mapa de trabalho, registre-o como uma issue com a etiqueta `wayfinder:map`.

Vincule os tickets ao mapa como sub-issues. Se esse recurso estiver indisponível, use uma lista de tarefas no mapa e uma referência ao mapa no corpo de cada ticket.

Registre bloqueios com as dependências nativas do GitHub. Se estiverem indisponíveis, escreva `Bloqueado por: #<número>` no ticket.

Considere disponível o primeiro ticket aberto na ordem do mapa que esteja sem responsável e sem bloqueadores abertos. Atribua-o ao responsável antes de iniciar o trabalho. Ao concluir, registre o resultado, encerre o ticket e atualize as decisões do mapa.
