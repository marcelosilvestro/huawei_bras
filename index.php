<?php
require_once __DIR__ . '/config.php';
$hwb_pagina = 'assinantes';
// A tela inicial abre para qualquer usuario do MK-AUTH: e nela que o primeiro administrador se
// apresenta. Os dados vem de inicio.estado, que so devolve contagens.
$hwb_perm_pagina = 'logado';
include('nav/header.php');
?>
<body>
<?php include('../../topo.php'); ?>

<div class="container-fluid px-3 py-0 hwb-wrap">
<?php include('nav/abas.php'); ?>
<?php if (!$hwb_bloqueado): ?>

    <div id="hwb-sem-admin" class="hwb-aviso info" style="display:none">
        <i class="bi bi-person-badge"></i>
        <div style="flex:1">
            <strong>Este addon ainda não tem administrador.</strong>
            O administrador cadastra os roteadores e concede as permissões dos outros usuários.
            Assuma a administração para começar a configurar.
        </div>
        <button type="button" class="lc-btn-black" id="btn-assumir"><i class="bi bi-shield-check"></i> Assumir administração</button>
    </div>

    <div id="hwb-sem-acesso" class="hwb-aviso" style="display:none">
        <i class="bi bi-shield-lock"></i>
        <div><strong>Você ainda não tem acesso a este addon.</strong>
            Peça ao administrador para liberar o seu login em Configurações › Permissões.</div>
    </div>

    <div class="hwb-cards" id="hwb-cards"></div>

    <div class="lc-section-panel" style="height:auto" id="hwb-config-inicial">
        <div class="lc-section-header">
            <span class="lc-section-title"><i class="bi bi-list-check"></i> Configuração inicial</span>
            <span class="lc-badge-count" id="hwb-passos-conta">–</span>
        </div>
        <ul class="hwb-passos" id="hwb-passos"><li class="lc-loading">Carregando...</li></ul>
    </div>

<?php endif; ?>
</div>

<?php include('../../baixo.php'); ?>
<?php include('nav/comum.php'); ?>
<script src="../../menu.js<?= $ext_mk ?>"></script>
<script>
(function () {
    function card(ico, cor, rotulo, valor, sub) {
        return '<div class="hwb-card"><div class="hwb-card-ico ' + cor + '"><i class="bi ' + ico + '"></i></div>' +
               '<div class="hwb-card-txt"><div class="hwb-card-rotulo">' + HWB.esc(rotulo) + '</div>' +
               '<div class="hwb-card-valor">' + HWB.esc(valor) + '</div>' +
               (sub ? '<div class="hwb-card-sub">' + HWB.esc(sub) + '</div>' : '') + '</div></div>';
    }

    function render(d) {
        $('#hwb-sem-admin').toggle(!d.ha_admin);
        $('#hwb-sem-acesso').toggle(d.ha_admin && !d.pode_ver);

        var c = d.contagens;
        if (d.pode_ver) {
            $('#hwb-cards').html(
                card('bi-router', '', 'Roteadores', c.roteadores, 'ativos') +
                card('bi-plug', c.roteadores_testados ? 'verde' : 'cinza', 'Testados', c.roteadores_testados, 'com o último teste OK') +
                card('bi-people', 'roxo', 'Operadores', c.usuarios, 'com permissão no addon') +
                card('bi-info-circle', 'cinza', 'Versão', d.versao, '')
            );
        } else {
            $('#hwb-cards').empty();
        }

        var feitos = 0, html = '';
        d.passos.forEach(function (p, i) {
            if (p.estado === 'feito') feitos++;
            var acao = p.estado === 'pendente' && p.link
                ? '<a class="lc-btn-outline" href="' + HWB.esc(p.link) + '">Abrir <i class="bi bi-arrow-right"></i></a>'
                : HWB.badge(p.estado);
            html += '<li class="hwb-passo ' + HWB.esc(p.estado) + '"><span class="hwb-passo-num">' +
                    (p.estado === 'feito' ? '<i class="bi bi-check-lg"></i>' : (i + 1)) + '</span>' +
                    '<div class="hwb-passo-txt"><div class="hwb-passo-titulo">' + HWB.esc(p.titulo) + '</div>' +
                    '<div class="hwb-passo-desc">' + HWB.esc(p.descricao) + '</div></div>' + acao + '</li>';
        });
        $('#hwb-passos').html(html);
        $('#hwb-passos-conta').text(feitos + '/' + d.passos.length);
    }

    function carregar() {
        HWB.api('inicio.estado').then(render).catch(HWB.erro);
    }

    $(function () {
        carregar();
        $('#btn-assumir').on('click', function () {
            HWB.confirmar({
                titulo: 'Assumir administração',
                msg: 'Você será o administrador deste addon.',
                sub: 'Esta ação fica registrada na auditoria e só pode ser feita enquanto não houver administrador.',
                textoOk: 'Assumir'
            }).then(function (ok) {
                if (!ok) return;
                HWB.loading('Gravando...');
                HWB.api('permissao.assumir_admin', {}, 'POST')
                    .then(function () { HWB.toast('ok', 'Você agora é o administrador do addon.'); carregar(); })
                    .catch(HWB.erro)
                    .finally(HWB.fimLoading);
            });
        });
    });
})();
</script>
</body>
</html>
