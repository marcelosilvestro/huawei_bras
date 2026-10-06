<?php
require_once __DIR__ . '/config.php';
$hwb_pagina = 'auditoria';
$hwb_perm_pagina = 'admin';
include('nav/header.php');
?>
<body>
<?php include('../../topo.php'); ?>

<div class="container-fluid px-3 py-0 hwb-wrap">
<?php include('nav/abas.php'); ?>
<?php if (!$hwb_bloqueado): ?>

    <div class="lc-filter-bar">
        <div class="lc-filter-group">
            <span class="lc-filter-label">De</span>
            <input type="date" class="lc-input-date" id="f-de">
        </div>
        <div class="lc-filter-group">
            <span class="lc-filter-label">Até</span>
            <input type="date" class="lc-input-date" id="f-ate">
        </div>
        <div class="lc-filter-group">
            <span class="lc-filter-label">Usuário</span>
            <input type="text" class="lc-input-date w200" id="f-usuario" placeholder="login">
        </div>
        <div class="lc-filter-group">
            <span class="lc-filter-label">Entidade</span>
            <select class="lc-input-date w200" id="f-entidade"><option value="">Todas</option></select>
        </div>
        <div class="lc-filter-group">
            <span class="lc-filter-label">Ação</span>
            <select class="lc-input-date w200" id="f-acao"><option value="">Todas</option></select>
        </div>
        <button type="button" class="lc-btn-black" id="btn-filtrar"><i class="bi bi-search"></i> Filtrar</button>
    </div>

    <div class="lc-section-panel" style="height:auto">
        <div class="lc-section-header">
            <span class="lc-section-title"><i class="bi bi-journal-text"></i> Trilha de auditoria</span>
            <span class="lc-badge-count" id="hwb-total">0</span>
        </div>
        <div class="lc-table-wrap">
            <table class="lc-table">
                <thead><tr>
                    <th>Quando</th><th>Usuário</th><th>Ação</th><th>Entidade</th>
                    <th class="prio-7">IP</th><th class="prio-6">Requisição</th><th></th>
                </tr></thead>
                <tbody id="hwb-linhas"><tr><td colspan="7" class="lc-loading">Carregando...</td></tr></tbody>
            </table>
        </div>
        <div class="hwb-paginacao" id="hwb-pag"></div>
    </div>

<?php endif; ?>
</div>

<?php include('../../baixo.php'); ?>
<?php include('nav/comum.php'); ?>

<div class="lc-overlay" id="modal-detalhe" onclick="HWB.fecharSeFora(event, 'modal-detalhe')">
    <div class="lc-modal-box lg">
        <div class="lc-modal-header lc-modal-header-dark">
            <span class="lc-modal-title">Detalhe do registro</span>
            <button type="button" class="lc-modal-close" onclick="HWB.fecharModal('modal-detalhe')">&times;</button>
        </div>
        <div class="lc-modal-body" id="detalhe-corpo"></div>
        <div class="lc-modal-footer">
            <button type="button" class="lc-btn-cancel" onclick="HWB.fecharModal('modal-detalhe')">Fechar</button>
        </div>
    </div>
</div>

<script src="../../menu.js<?= $ext_mk ?>"></script>
<script>
(function () {
    var linhas = [];
    var pagina = 1;

    function opcoes($sel, lista) {
        var atual = $sel.val();
        $sel.find('option:not(:first)').remove();
        lista.forEach(function (v) { $sel.append($('<option>').val(v).text(v)); });
        $sel.val(atual);
    }

    function render(d) {
        linhas = d.linhas;
        $('#hwb-total').text(d.total);
        opcoes($('#f-entidade'), d.entidades);
        opcoes($('#f-acao'), d.acoes);
        if (!linhas.length) {
            $('#hwb-linhas').html('<tr><td colspan="7" class="lc-empty">Nenhum registro.</td></tr>');
        } else {
            $('#hwb-linhas').html(linhas.map(function (l, i) {
                var ent = HWB.esc(l.entidade) + (l.entidade_id ? ' #' + HWB.esc(l.entidade_id) : '');
                return '<tr><td style="white-space:nowrap">' + HWB.dataHora(l.criado_em) + '</td>' +
                    '<td>' + HWB.esc(l.usuario) + '</td><td><strong>' + HWB.esc(l.acao) + '</strong></td>' +
                    '<td>' + ent + '</td><td class="prio-7 hwb-sub">' + HWB.esc(l.ip || '') + '</td>' +
                    '<td class="prio-6 hwb-mono hwb-sub">' + HWB.esc(l.request_id || '') + '</td>' +
                    '<td style="text-align:right"><button type="button" class="lc-btn-outline" data-i="' + i + '"><i class="bi bi-eye"></i></button></td></tr>';
            }).join(''));
        }
        HWB.paginacao($('#hwb-pag'), d.pagina, d.por_pagina, d.total, function (p) { pagina = p; carregar(); });
    }

    function carregar() {
        HWB.api('auditoria.listar', {
            pagina: pagina, de: $('#f-de').val(), ate: $('#f-ate').val(), usuario: $('#f-usuario').val(),
            entidade: $('#f-entidade').val(), acao_filtro: $('#f-acao').val()
        }).then(render).catch(HWB.erro);
    }

    function bloco(titulo, obj) {
        if (obj === null || obj === undefined) return '';
        return '<label class="lc-label hwb-mt">' + titulo + '</label><pre class="hwb-detalhe-json hwb-mono">' +
               HWB.esc(JSON.stringify(obj, null, 2)) + '</pre>';
    }

    $(function () {
        carregar();
        $('#btn-filtrar').on('click', function () { pagina = 1; carregar(); });
        $('#hwb-linhas').on('click', 'button[data-i]', function () {
            var l = linhas[parseInt($(this).attr('data-i'), 10)];
            $('#detalhe-corpo').html(
                '<div><strong>' + HWB.esc(l.acao) + '</strong> em ' + HWB.esc(l.entidade) +
                (l.entidade_id ? ' #' + HWB.esc(l.entidade_id) : '') + '</div>' +
                '<div class="hwb-sub">' + HWB.dataHora(l.criado_em) + ' · ' + HWB.esc(l.usuario) + ' · ' + HWB.esc(l.ip || '') +
                (l.correlacao ? ' · correlação ' + HWB.esc(l.correlacao) : '') + '</div>' +
                bloco('Antes', l.antes) + bloco('Depois', l.depois));
            HWB.abrirModal('modal-detalhe');
        });
    });
})();
</script>
</body>
</html>
