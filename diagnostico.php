<?php
require_once __DIR__ . '/config.php';
$hwb_pagina = 'diagnostico';
$hwb_perm_pagina = 'ver';
include('nav/header.php');
?>
<body>
<?php include('../../topo.php'); ?>

<div class="container-fluid px-3 py-0 hwb-wrap">
<?php include('nav/abas.php'); ?>
<?php if (!$hwb_bloqueado): ?>

    <div class="lc-filter-bar">
        <div class="lc-filter-group">
            <span class="lc-filter-label">Diagnóstico por componente</span>
            <span class="hwb-sub" id="hwb-diag-quando">–</span>
        </div>
        <div style="flex:1"></div>
        <button type="button" class="lc-btn-black" id="btn-atualizar"><i class="bi bi-arrow-clockwise"></i> Atualizar</button>
        <?php if (Permissao::tem('admin')): ?>
        <button type="button" class="lc-btn-outline" id="btn-pacote" title="Arquivo JSON para suporte remoto, sem senhas">
            <i class="bi bi-download"></i> Pacote de suporte</button>
        <?php endif; ?>
    </div>

    <div id="hwb-diag"><div class="lc-loading">Verificando...</div></div>

<?php endif; ?>
</div>

<?php include('../../baixo.php'); ?>
<?php include('nav/comum.php'); ?>
<script src="../../menu.js<?= $ext_mk ?>"></script>
<script>
(function () {
    function render(d) {
        $('#hwb-diag-quando').text('Gerado em ' + d.gerado_em);
        var html = '';
        d.componentes.forEach(function (c) {
            html += '<div class="lc-section-panel hwb-mt" style="height:auto">' +
                '<div class="lc-section-header"><span class="lc-section-title">' + HWB.esc(c.titulo) + '</span>' + HWB.badge(c.resultado) + '</div>' +
                '<div class="lc-table-wrap" style="min-height:0"><table class="lc-table"><tbody>';
            c.itens.forEach(function (i) {
                html += '<tr><td style="width:240px"><strong>' + HWB.esc(i.titulo) + '</strong></td>' +
                    '<td style="width:110px">' + HWB.badge(i.resultado) + '</td>' +
                    '<td class="hwb-quebra">' + HWB.esc(i.detalhe) +
                    (i.acao ? '<div class="hwb-acao"><i class="bi bi-lightbulb"></i> ' + HWB.esc(i.acao) + '</div>' : '') +
                    '</td></tr>';
            });
            html += '</tbody></table></div></div>';
        });
        $('#hwb-diag').html(html);
    }

    function carregar() {
        $('#hwb-diag').html('<div class="lc-loading">Verificando...</div>');
        HWB.api('diagnostico.componentes').then(render).catch(function (e) {
            $('#hwb-diag').html('<div class="lc-empty">Não foi possível gerar o diagnóstico.</div>');
            HWB.erro(e);
        });
    }

    $(function () {
        carregar();
        $('#btn-atualizar').on('click', carregar);
        $('#btn-pacote').on('click', function () {
            HWB.loading('Montando o pacote...');
            HWB.api('diagnostico.pacote')
                .then(function (d) {
                    HWB.baixarJson('huawei_bras-suporte-' + new Date().toISOString().slice(0, 19).replace(/[:T]/g, '') + '.json', d);
                })
                .catch(HWB.erro)
                .finally(HWB.fimLoading);
        });
    });
})();
</script>
</body>
</html>
