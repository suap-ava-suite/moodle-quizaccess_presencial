# Permitir nova cópia do convite ativo

Para atender à issue #48, o Professor poderá copiar novamente o mesmo Convite por link ativo em visitas futuras, sem invalidar links distribuídos; essa decisão substitui o [ADR 0005](0005-exibir-o-link-de-convite-uma-unica-vez.md) e foi confirmada no planejamento em 24/11/2026. O segredo passará a ser armazenado também de forma criptografada pela API nativa do Moodle, com recuperação restrita à gestão do Questionário e remoção ao encerrar o convite, mantendo a derivação para validação e aceitando a dependência da chave do servidor em troca da conveniência de nova cópia. Convites anteriores à mudança continuam válidos, mas exigem regeneração manual para habilitar essa cópia, pois sua derivação atual não permite reconstruir o segredo.

Decisão aceita e implementada conforme o [plano da issue #48](../plano-issue-48.md).

A recuperação bem-sucedida do link por um Professor autorizado gera um evento no log do Moodle com o Questionário e a geração, sem o segredo. O evento registra a recuperação pelo servidor, não uma cópia efetiva para a área de transferência.
