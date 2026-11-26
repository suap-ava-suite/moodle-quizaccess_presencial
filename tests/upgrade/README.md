# Teste de atualização com convite legado

Este procedimento usa uma instalação Moodle descartável, com banco e diretório de dados vazios. Não o execute em uma instalação existente: ele instala o Moodle e substitui os arquivos do plugin. É necessário ter PHP e os requisitos do Moodle instalados, além de uma cópia deste repositório com o commit `9be9d95` disponível.

1. Configure `MOODLE_DIR` para uma cópia limpa do Moodle compatível e `PLUGIN_SOURCE` para esta cópia do repositório. Configure nela um `config.php` que aponte para o banco e diretório de dados descartáveis. A pasta `mod/quiz/accessrule/presencial` não deve existir.
2. Instale o código anterior e o banco:

   ```sh
   mkdir "$MOODLE_DIR/mod/quiz/accessrule/presencial"
   git -C "$PLUGIN_SOURCE" archive 9be9d95 | tar -x -C "$MOODLE_DIR/mod/quiz/accessrule/presencial"
   php "$MOODLE_DIR/admin/cli/install_database.php" --agree-license --adminpass='Moodle-Test_2026!' --fullname='Teste de atualização' --shortname=teste-upgrade
   ```

3. Guarde temporariamente o convite criado pela API pública. `STATE_FILE` deve ser um caminho ainda inexistente em um diretório privado, acessível ao PHP. O arquivo contém um segredo e deve ser apagado se o procedimento falhar.

   ```sh
   export MOODLE_DIR PRESENCIAL_DISPOSABLE_SITE=yes
   STATE_DIR="$(mktemp -d)"
   STATE_FILE="$STATE_DIR/legacy-invitation.json"
   php "$PLUGIN_SOURCE/tests/upgrade/legacy_invitation.php" create "$STATE_FILE"
   ```

4. Substitua somente na instalação descartável o diretório do plugin pela versão atual desta cópia de trabalho e execute o upgrade real:

   ```sh
   rm -rf "$MOODLE_DIR/mod/quiz/accessrule/presencial"
   mkdir "$MOODLE_DIR/mod/quiz/accessrule/presencial"
   tar -C "$PLUGIN_SOURCE" --exclude=.git --exclude=.agents --exclude=.codex -cf - . | tar -x -C "$MOODLE_DIR/mod/quiz/accessrule/presencial"
   php "$MOODLE_DIR/admin/cli/upgrade.php" --non-interactive
   php "$PLUGIN_SOURCE/tests/upgrade/legacy_invitation.php" verify "$STATE_FILE"
   rmdir "$(dirname "$STATE_FILE")"
   ```

A segunda fase confirma que o link anterior continua válido, mas não pode ser copiado; então regenera o convite e verifica que o anterior deixou de ser válido e o novo pode ser recuperado. O arquivo temporário é apagado quando todas as verificações passam.
