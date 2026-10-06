<?php
/**
 * huawei_bras :: barra de navegacao do addon + guarda de acesso da pagina.
 *
 * Antes de incluir, a pagina define:
 *   $hwb_pagina       id da aba atual
 *   $hwb_perm_pagina  papel exigido ('logado' = qualquer usuario do painel)
 *
 * Depois do include, $hwb_bloqueado diz se o conteudo pode ser mostrado. A guarda aqui e de
 * conveniencia: cada operacao AJAX confere a permissao de novo no servidor.
 */
$hwb_abas = [
    'assinantes'    => ['index.php',         'bi-people',           'Assinantes'],
    'roteadores'    => ['roteadores.php',    'bi-router',           'Roteadores'],
    'diagnostico'   => ['diagnostico.php',   'bi-heart-pulse',      'Diagnóstico'],
    'auditoria'     => ['auditoria.php',     'bi-journal-text',     'Auditoria'],
    'configuracoes' => ['configuracoes.php', 'bi-gear',             'Configurações'],
];
// Telas ja entregues; as demais aparecem apagadas, com "em breve".
$hwb_abas_prontas = ['assinantes', 'roteadores', 'diagnostico', 'auditoria', 'configuracoes'];

$hwb_bloqueado = false;
$hwb_motivo = '';
if (!$hwb_schema_ok) {
    $hwb_bloqueado = true;
    $hwb_motivo = 'schema';
} elseif (($hwb_perm_pagina ?? 'ver') !== 'logado' && !Permissao::tem($hwb_perm_pagina ?? 'ver')) {
    $hwb_bloqueado = true;
    $hwb_motivo = 'permissao';
}
?>
<nav class="hwb-abas">
    <span class="hwb-marca"><i class="bi bi-hdd-rack-fill"></i> BRAS Huawei</span>
    <?php foreach ($hwb_abas as $id => [$arq, $ico, $rot]): ?>
        <?php if (in_array($id, $hwb_abas_prontas, true)): ?>
            <a href="<?= $arq ?>" class="hwb-aba<?= ($hwb_pagina ?? '') === $id ? ' ativa' : '' ?>"><i class="bi <?= $ico ?>"></i> <?= $rot ?></a>
        <?php else: ?>
            <span class="hwb-aba em-breve" title="<?= $rot ?> — disponível numa próxima etapa"><i class="bi <?= $ico ?>"></i></span>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>

<?php if ($hwb_motivo === 'schema'): ?>
    <div class="hwb-aviso erro">
        <i class="bi bi-database-exclamation"></i>
        <div><strong>O banco do addon não está instalado.</strong>
            Rode o instalador no terminal do servidor (como root) para criar as tabelas e a chave do cofre.</div>
    </div>
<?php elseif ($hwb_motivo === 'permissao'): ?>
    <div class="hwb-aviso">
        <i class="bi bi-shield-lock"></i>
        <div><strong>Você não tem acesso a esta tela.</strong>
            Peça ao administrador do addon para liberar o seu login (<?= hwb_h(Permissao::login()) ?>) em Configurações › Permissões.</div>
    </div>
<?php endif; ?>
