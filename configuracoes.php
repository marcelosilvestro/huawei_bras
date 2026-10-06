<?php
require_once __DIR__ . '/config.php';
$hwb_pagina = 'configuracoes';
$hwb_perm_pagina = 'ver';
include('nav/header.php');
$hwb_admin = $hwb_schema_ok && Permissao::tem('admin');
?>
<body>
<?php include('../../topo.php'); ?>

<div class="container-fluid px-3 py-0 hwb-wrap">
<?php include('nav/abas.php'); ?>
<?php if (!$hwb_bloqueado): ?>

    <?php if (!$hwb_admin): ?>
    <div class="hwb-aviso info"><i class="bi bi-info-circle"></i>
        <div>Somente o administrador do addon altera configurações e permissões. Você está vendo os valores em vigor.</div></div>
    <?php endif; ?>

    <div class="lc-section-panel" style="height:auto">
        <div class="lc-section-header">
            <span class="lc-section-title"><i class="bi bi-sliders"></i> Configurações gerais</span>
            <?php if ($hwb_admin): ?>
            <button type="button" class="lc-btn-black" id="btn-salvar"><i class="bi bi-check2"></i> Salvar</button>
            <?php endif; ?>
        </div>
        <div id="hwb-config"><div class="lc-loading">Carregando...</div></div>
    </div>

    <?php if ($hwb_admin): ?>
    <div class="lc-section-panel hwb-mt" style="height:auto">
        <div class="lc-section-header">
            <span class="lc-section-title"><i class="bi bi-people"></i> Permissões</span>
            <button type="button" class="lc-btn-black" id="btn-novo-usuario"><i class="bi bi-person-plus"></i> Conceder acesso</button>
        </div>
        <div class="lc-table-wrap">
            <table class="lc-table">
                <thead><tr><th>Login</th><th class="prio-6">Nome</th><th>Papéis</th><th></th></tr></thead>
                <tbody id="hwb-perms"><tr><td colspan="4" class="lc-loading">Carregando...</td></tr></tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

<?php endif; ?>
</div>

<?php include('../../baixo.php'); ?>
<?php include('nav/comum.php'); ?>

<?php if ($hwb_admin): ?>
<div class="lc-overlay" id="modal-perm" onclick="HWB.fecharSeFora(event, 'modal-perm')">
    <div class="lc-modal-box lg">
        <div class="lc-modal-header">
            <span class="lc-modal-title">Permissões do usuário</span>
            <button type="button" class="lc-modal-close" onclick="HWB.fecharModal('modal-perm')">&times;</button>
        </div>
        <div class="lc-modal-body">
            <label class="lc-label">Usuário do MK-AUTH</label>
            <select class="lc-input" id="perm-login"></select>
            <div class="lc-input-hint" id="perm-login-dica"></div>
            <label class="lc-label hwb-mt">Papéis</label>
            <div class="hwb-papeis-grid" id="perm-papeis"></div>
        </div>
        <div class="lc-modal-footer">
            <button type="button" class="lc-btn-cancel" onclick="HWB.fecharModal('modal-perm')">Cancelar</button>
            <button type="button" class="lc-btn-confirm" id="perm-salvar">Salvar</button>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="../../menu.js<?= $ext_mk ?>"></script>
<script>
(function () {
    var ADMIN = <?= $hwb_admin ? 'true' : 'false' ?>;
    var itens = [];
    var permDados = null;

    // ------------------------------------------------------------ gerais
    function campo(i) {
        var id = 'cfg-' + i.chave, dis = ADMIN ? '' : ' disabled', html;
        if (i.tipo === 'bool') {
            html = '<label class="hwb-chk"><input type="checkbox" id="' + id + '"' + (i.valor === '1' ? ' checked' : '') + dis + '> ' + HWB.esc(i.rotulo) + '</label>';
        } else if (i.tipo === 'enum') {
            html = '<label class="lc-label" for="' + id + '">' + HWB.esc(i.rotulo) + '</label><select class="lc-input" id="' + id + '"' + dis + '>' +
                i.opcoes.map(function (o) { return '<option' + (o === i.valor ? ' selected' : '') + '>' + HWB.esc(o) + '</option>'; }).join('') + '</select>';
        } else if (i.tipo === 'hora') {
            html = '<label class="lc-label" for="' + id + '">' + HWB.esc(i.rotulo) + '</label><input type="time" class="lc-input" id="' + id + '" value="' + HWB.esc(i.valor) + '"' + dis + '>';
        } else {
            html = '<label class="lc-label" for="' + id + '">' + HWB.esc(i.rotulo) + '</label><input type="number" class="lc-input" id="' + id + '" value="' + HWB.esc(i.valor) + '"' +
                (i.min !== null ? ' min="' + i.min + '"' : '') + (i.max !== null ? ' max="' + i.max + '"' : '') + dis + '>';
        }
        return '<div class="hwb-form-item">' + html + '<div class="lc-input-hint">' + HWB.esc(i.ajuda) + '</div></div>';
    }

    function renderConfig(d) {
        itens = d.itens;
        var grupos = {}, ordem = [];
        itens.forEach(function (i) { if (!grupos[i.grupo]) { grupos[i.grupo] = []; ordem.push(i.grupo); } grupos[i.grupo].push(i); });
        $('#hwb-config').html(ordem.map(function (g) {
            return '<div class="lc-label" style="padding:12px 16px 0">' + HWB.esc(g) + '</div><div class="hwb-form-grid">' + grupos[g].map(campo).join('') + '</div>';
        }).join(''));
    }

    function valoresDoForm() {
        var v = {};
        itens.forEach(function (i) {
            var $c = $('#cfg-' + i.chave);
            v[i.chave] = i.tipo === 'bool' ? ($c.is(':checked') ? '1' : '0') : $c.val();
        });
        return v;
    }

    function salvarConfig() {
        HWB.loading('Salvando...');
        HWB.api('config.salvar', { valores: valoresDoForm() }, 'POST')
            .then(function (d) {
                renderConfig({ itens: d.itens });
                HWB.toast('ok', d.alteradas.length ? 'Configurações salvas.' : 'Nada foi alterado.');
            })
            .catch(HWB.erro)
            .finally(HWB.fimLoading);
    }

    // ------------------------------------------------------------ permissoes
    function nomeDe(login) {
        var u = (permDados.usuarios || []).filter(function (x) { return x.login === login; })[0];
        return u ? u.nome : '';
    }

    function renderPerms(d) {
        permDados = d;
        if (!d.permissoes.length) {
            $('#hwb-perms').html('<tr><td colspan="4" class="lc-empty">Nenhum usuário com acesso.</td></tr>');
            return;
        }
        $('#hwb-perms').html(d.permissoes.map(function (p) {
            return '<tr><td><strong>' + HWB.esc(p.login) + '</strong>' + (p.login === d.eu ? ' <span class="hwb-sub">(você)</span>' : '') + '</td>' +
                '<td class="prio-6">' + HWB.esc(nomeDe(p.login)) + '</td>' +
                '<td class="hwb-quebra">' + p.papeis.map(function (x) { return '<span class="hwb-papel' + (x === 'admin' ? ' admin' : '') + '">' + HWB.esc(x) + '</span>'; }).join('') + '</td>' +
                '<td style="text-align:right"><button type="button" class="lc-btn-outline" data-login="' + HWB.esc(p.login) + '"><i class="bi bi-pencil"></i> Editar</button></td></tr>';
        }).join(''));
    }

    function carregarPerms() {
        if (!ADMIN) return;
        HWB.api('permissao.listar').then(renderPerms).catch(HWB.erro);
    }

    function abrirPerm(login) {
        var atuais = [];
        (permDados.permissoes || []).forEach(function (p) { if (p.login === login) atuais = p.papeis; });
        var $sel = $('#perm-login').empty();
        if (login) {
            $sel.append($('<option>').val(login).text(login + (nomeDe(login) ? ' — ' + nomeDe(login) : ''))).prop('disabled', true);
        } else {
            $sel.prop('disabled', false).append('<option value="">Selecione...</option>');
            var comAcesso = {};
            permDados.permissoes.forEach(function (p) { comAcesso[p.login] = 1; });
            permDados.usuarios.forEach(function (u) {
                if (!comAcesso[u.login]) $sel.append($('<option>').val(u.login).text(u.login + (u.nome ? ' — ' + u.nome : '') + (u.ativo ? '' : ' (inativo)')));
            });
        }
        $('#perm-login-dica').text(permDados.usuarios.length ? '' : 'Não consegui ler os usuários do MK-AUTH.');
        $('#perm-papeis').html(permDados.papeis.map(function (p) {
            return '<label><input type="checkbox" value="' + HWB.esc(p.id) + '"' + (atuais.indexOf(p.id) !== -1 ? ' checked' : '') + '>' +
                   '<span><strong>' + HWB.esc(p.id) + '</strong><small>' + HWB.esc(p.descricao) + '</small></span></label>';
        }).join(''));
        HWB.abrirModal('modal-perm');
    }

    function salvarPerm() {
        var login = $('#perm-login').val();
        if (!login) { HWB.toast('avis', 'Selecione o usuário.'); return; }
        var papeis = $('#perm-papeis input:checked').map(function () { return this.value; }).get();
        HWB.loading('Salvando...');
        HWB.api('permissao.definir', { login: login, papeis: papeis }, 'POST')
            .then(function () { HWB.fecharModal('modal-perm'); HWB.toast('ok', 'Permissões de ' + login + ' salvas.'); carregarPerms(); })
            .catch(HWB.erro)
            .finally(HWB.fimLoading);
    }

    $(function () {
        HWB.api('config.listar').then(renderConfig).catch(HWB.erro);
        carregarPerms();
        $('#btn-salvar').on('click', salvarConfig);
        $('#btn-novo-usuario').on('click', function () { abrirPerm(''); });
        $('#hwb-perms').on('click', 'button[data-login]', function () { abrirPerm($(this).attr('data-login')); });
        $('#perm-salvar').on('click', salvarPerm);
    });
})();
</script>
</body>
</html>
