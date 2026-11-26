# Exibir o link de convite uma única vez

Decisão substituída pelo [ADR 0012](0012-permitir-nova-copia-do-convite-ativo.md) na issue #48.

O segredo do Convite por link será armazenado somente de forma derivada e o link completo será exibido apenas no momento da geração. Se o professor perder o link, deverá regenerá-lo, invalidando o anterior; essa escolha aceita menor conveniência para evitar que um segredo reutilizável possa ser recuperado do banco de dados.
