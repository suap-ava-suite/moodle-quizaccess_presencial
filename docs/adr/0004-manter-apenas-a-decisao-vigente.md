# Manter apenas a decisão vigente na tabela operacional

Enquanto uma Solicitação de liberação for revisável, a decisão confirmada mais recentemente substitui seu estado operacional anterior. A tabela do plugin conserva somente essa Decisão vigente, mas cada alteração gera um evento no log do Moodle; assim, a operação permanece simples sem apagar a trilha institucional de auditoria.
