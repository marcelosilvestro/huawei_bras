<?php
require_once __DIR__ . '/config.php';
$hwb_pagina = 'roteadores';
$hwb_perm_pagina = 'ver';
include('nav/header.php');
$hwb_pode = $hwb_schema_ok && Permissao::tem('roteador.configurar');
?>
<body>
<?php include('../../topo.php'); ?>

<div class="container-fluid px-3 py-0 hwb-wrap">
<?php include('nav/abas.php'); ?>
<?php if (!$hwb_bloqueado): ?>

    <div class="hwb-aviso info">
        <i class="bi bi-info-circle"></i>
        <div>O teste de acesso só <strong>lê</strong> o roteador: faz login por SSH e consulta a versão e o total de assinantes
            (<span class="hwb-mono">display version</span>, <span class="hwb-mono">display access-user online-total</span>).
            Nenhuma configuração é alterada. Para conhecer a tela sem equipamento, cadastre um roteador com o protocolo <strong>Simulado</strong>.</div>
    </div>

    <div class="lc-section-panel" style="height:auto">
        <div class="lc-section-header">
            <span class="lc-section-title"><i class="bi bi-router"></i> Roteadores cadastrados <span class="lc-badge-count" id="hwb-total">0</span></span>
            <div class="hwb-acoes">
                <?php if ($hwb_pode): ?>
                <button type="button" class="lc-btn-black" id="btn-novo"><i class="bi bi-plus-lg"></i> Novo roteador</button>
                <?php endif; ?>
            </div>
        </div>
        <div class="lc-table-wrap">
            <table class="lc-table">
                <thead><tr>
                    <th>Nome</th><th class="prio-7">Acesso SSH</th><th class="prio-6">NAS no RADIUS</th>
                    <th class="prio-8">Versão</th><th>Online</th><th class="prio-8">Último teste</th><th></th>
                </tr></thead>
                <tbody id="hwb-linhas"><tr><td colspan="7" class="lc-loading">Carregando...</td></tr></tbody>
            </table>
        </div>
    </div>

<?php endif; ?>
</div>

<?php include('../../baixo.php'); ?>
<?php include('nav/comum.php'); ?>

<div class="lc-overlay" id="modal-rot" onclick="HWB.fecharSeFora(event, 'modal-rot')">
    <div class="lc-modal-box lg">
        <div class="lc-modal-header">
            <span class="lc-modal-title" id="rot-titulo">Novo roteador</span>
            <button type="button" class="lc-modal-close" onclick="HWB.fecharModal('modal-rot')">&times;</button>
        </div>
        <div class="lc-modal-body">
            <input type="hidden" id="f-id"><input type="hidden" id="f-versao">
            <div class="hwb-form-2">
                <div><label class="lc-label">Nome</label><input class="lc-input" id="f-nome" maxlength="80" placeholder="ex.: BRAS Centro"></div>
                <div><label class="lc-label">Modelo</label><select class="lc-input" id="f-modelo"></select></div>
                <div><label class="lc-label">Protocolo</label><select class="lc-input" id="f-protocolo"></select></div>
                <div class="so-real"><label class="lc-label">Porta SSH</label><input class="lc-input" id="f-porta" type="number" min="1" max="65535"></div>
                <div class="so-real span2"><label class="lc-label">Endereço de gerência (IP ou hostname)</label><input class="lc-input" id="f-host" maxlength="253">
                    <div class="lc-input-hint">Onde o addon faz o SSH. Pode ser diferente do IP do NAS.</div></div>
                <div class="so-real"><label class="lc-label">Usuário</label><input class="lc-input" id="f-usuario" maxlength="60" autocomplete="off"></div>
                <div class="so-real"><label class="lc-label">Senha</label><input class="lc-input" id="f-senha" type="password" autocomplete="new-password">
                    <div class="lc-input-hint" id="f-senha-dica"></div></div>
                <div class="span2"><label class="lc-label">NAS no RADIUS</label><select class="lc-input" id="f-nas"></select>
                    <div class="lc-input-hint" id="f-nas-dica">O IP com que este BRAS se apresenta ao RADIUS (cadastro de NAS do MK-AUTH). É ele que filtra os assinantes deste roteador.</div></div>
                <div><label class="lc-label">Tempo de conexão (s)</label><input class="lc-input" id="f-tcon" type="number" min="3" max="60"></div>
                <div><label class="lc-label">Tempo por comando (s)</label><input class="lc-input" id="f-tcmd" type="number" min="5" max="120"></div>
                <div class="span2"><label class="lc-label">Observação</label><input class="lc-input" id="f-obs" maxlength="500"></div>
            </div>
            <div class="lc-input-hint hwb-mt">A senha é guardada cifrada e nunca volta para a tela. Use no BRAS um usuário só de consulta e corte de assinante, nunca o de administração.</div>
        </div>
        <div class="lc-modal-footer">
            <button type="button" class="lc-btn-cancel" onclick="HWB.fecharModal('modal-rot')">Cancelar</button>
            <button type="button" class="lc-btn-confirm" id="btn-salvar">Salvar</button>
        </div>
    </div>
</div>

<div class="lc-overlay" id="modal-teste" onclick="HWB.fecharSeFora(event, 'modal-teste')">
    <div class="lc-modal-box lg">
        <div class="lc-modal-header lc-modal-header-dark">
            <span class="lc-modal-title" id="teste-titulo">Teste de acesso</span>
            <button type="button" class="lc-modal-close" onclick="HWB.fecharModal('modal-teste')">&times;</button>
        </div>
        <div class="lc-modal-body" id="teste-corpo"></div>
        <div class="lc-modal-footer">
            <button type="button" class="lc-btn-cancel" onclick="HWB.fecharModal('modal-teste')">Fechar</button>
        </div>
    </div>
</div>

<script src="../../menu.js<?= $ext_mk ?>"></script>
<script>
(function () {
    var PODE = <?= $hwb_pode ? 'true' : 'false' ?>;
    var dados = { roteadores: [], modelos: [], protocolos: [], nas: [] };

    function porId(id) { return dados.roteadores.filter(function (r) { return r.id === id; })[0]; }
    function num(n) { return n === null || n === undefined ? '—' : Number(n).toLocaleString('pt-BR'); }

    function linha(r) {
        var nome = '<strong>' + HWB.esc(r.nome) + '</strong>' +
            (r.protocolo === 'simulado' ? ' ' + HWB.badge('simulado') : '') +
            (!r.ativo ? ' ' + HWB.badge('inativo') : '') + (r.bloqueado ? ' ' + HWB.badge('bloqueado') : '') +
            '<div class="hwb-sub">' + HWB.esc(r.modelo_rotulo) + (r.identificador_detectado ? ' · ' + HWB.esc(r.identificador_detectado) : '') + '</div>';
        var acesso = r.protocolo === 'simulado' ? '<span class="hwb-sub">sem rede</span>'
            : HWB.esc(r.host) + ':' + r.porta + ' <span class="hwb-sub">' + HWB.esc(r.usuario) + '</span>';
        var teste = r.ultimo_teste_resultado
            ? HWB.badge(r.ultimo_teste_resultado) + '<div class="hwb-sub">' + HWB.dataHora(r.ultimo_teste_em) + '</div>'
            : '<span class="hwb-sub">nunca</span>';
        var acoes = '<div class="hwb-acoes">';
        if (PODE && r.ativo) acoes += '<button type="button" class="lc-btn-black" data-acao="testar" data-id="' + r.id + '"><i class="bi bi-plug"></i> Testar</button>';
        acoes += '<button type="button" class="lc-btn-outline" data-acao="menu" data-id="' + r.id + '" title="Mais ações"><i class="bi bi-three-dots"></i></button></div>';
        return '<tr' + (r.ativo ? '' : ' style="opacity:.6"') + '><td>' + nome + '</td><td class="prio-7">' + acesso + '</td>' +
            '<td class="prio-6 hwb-mono">' + HWB.esc(r.nas_ip) + '</td>' +
            '<td class="prio-8 hwb-sub">' + HWB.esc(r.versao_detectada || '—') + '</td>' +
            '<td>' + num(r.sessoes_detectadas) + '</td>' +
            '<td class="prio-8">' + teste + '</td><td>' + acoes + '</td></tr>';
    }

    function render() {
        $('#hwb-total').text(dados.roteadores.length);
        $('#hwb-linhas').html(dados.roteadores.length ? dados.roteadores.map(linha).join('')
            : '<tr><td colspan="7" class="lc-empty">Nenhum roteador cadastrado.' + (PODE ? ' Use "Novo roteador".' : '') + '</td></tr>');
    }

    function carregar() {
        return HWB.api('roteador.listar').then(function (d) { dados = d; render(); }).catch(HWB.erro);
    }

    // ------------------------------------------------------------ formulario
    function ajustarProtocolo() {
        var p = $('#f-protocolo').val();
        $('#modal-rot .so-real').toggle(p !== 'simulado');
        var def = (dados.protocolos.filter(function (x) { return x.id === p; })[0] || {}).porta;
        if (!$('#f-porta').val() && def) $('#f-porta').val(def);
    }

    function opcoesNas(r) {
        var atual = r ? r.nas_ip : '';
        var html = '<option value="">Selecione o NAS...</option>';
        var achou = false;
        dados.nas.forEach(function (n) {
            var ocupado = n.roteador_id !== null && (!r || n.roteador_id !== r.id);
            if (n.ip === atual) achou = true;
            html += '<option value="' + HWB.esc(n.ip) + '"' + (ocupado ? ' disabled' : '') + '>' +
                HWB.esc(n.ip + (n.nome ? ' — ' + n.nome : '') + (ocupado ? ' (já usado por ' + n.roteador + ')' : '')) + '</option>';
        });
        // NAS que saiu do MK-AUTH depois do cadastro: continua visivel para o operador trocar.
        if (atual && !achou) html += '<option value="' + HWB.esc(atual) + '">' + HWB.esc(atual + ' — não existe mais no MK-AUTH') + '</option>';
        $('#f-nas').html(html).val(atual);
        $('#f-nas-dica').toggleClass('hwb-erro-txt', !dados.nas.length);
        if (!dados.nas.length) $('#f-nas-dica').text('Nenhum NAS cadastrado no MK-AUTH. Cadastre o BRAS como NAS no MK-AUTH antes.');
    }

    function abrirForm(r) {
        $('#rot-titulo').text(r ? 'Editar roteador' : 'Novo roteador');
        $('#f-modelo').html(dados.modelos.map(function (m) {
            return '<option value="' + HWB.esc(m.id) + '">' + HWB.esc(m.rotulo) + (m.validado ? '' : ' (não testado)') + '</option>';
        }).join(''));
        $('#f-protocolo').html(dados.protocolos.map(function (p) {
            return '<option value="' + p.id + '">' + HWB.esc(p.rotulo) + '</option>';
        }).join(''));
        $('#f-id').val(r ? r.id : '');
        $('#f-versao').val(r ? r.versao : '');
        $('#f-nome').val(r ? r.nome : '');
        $('#f-modelo').val(r ? r.modelo : 'NE8000');
        $('#f-protocolo').val(r ? r.protocolo : 'ssh');
        $('#f-host').val(r && r.protocolo !== 'simulado' ? r.host : '');
        $('#f-porta').val(r ? r.porta : '');
        $('#f-usuario').val(r && r.protocolo !== 'simulado' ? r.usuario : '');
        $('#f-senha').val('');
        $('#f-senha-dica').text(r && r.tem_senha ? '••• definida — deixe em branco para manter.' : 'Obrigatória.');
        opcoesNas(r);
        $('#f-tcon').val(r ? r.timeout_conexao_s : 10);
        $('#f-tcmd').val(r ? r.timeout_comando_s : 15);
        $('#f-obs').val(r ? (r.observacao || '') : '');
        ajustarProtocolo();
        HWB.abrirModal('modal-rot');
        setTimeout(function () { $('#f-nome').trigger('focus'); }, 50);
    }

    function salvar() {
        var form = {
            id: $('#f-id').val() || 0, versao: $('#f-versao').val(), nome: $('#f-nome').val(),
            modelo: $('#f-modelo').val(), protocolo: $('#f-protocolo').val(), host: $('#f-host').val(),
            porta: $('#f-porta').val(), usuario: $('#f-usuario').val(), senha: $('#f-senha').val(),
            nas_ip: $('#f-nas').val(), timeout_conexao_s: $('#f-tcon').val(), timeout_comando_s: $('#f-tcmd').val(),
            observacao: $('#f-obs').val()
        };
        HWB.loading('Salvando...');
        HWB.api('roteador.salvar', form, 'POST')
            .then(function (r) {
                $('#f-senha').val('');
                HWB.fecharModal('modal-rot');
                HWB.toast('ok', 'Roteador ' + r.nome + ' salvo.', 'Use "Testar" para conferir o acesso.');
                carregar();
            })
            .catch(HWB.erro)
            .finally(HWB.fimLoading);
    }

    // ------------------------------------------------------------ teste
    function etapasHtml(etapas) {
        return etapas.map(function (e) {
            var ms = e.ms || e.duracao_ms;
            return '<div class="hwb-etapa">' + HWB.badge(e.resultado) + '<div><strong>' + HWB.esc(e.titulo) + '</strong>' +
                '<div class="hwb-sub hwb-quebra">' + HWB.esc(e.detalhe) + (ms ? ' · ' + ms + ' ms' : '') + '</div></div></div>';
        }).join('');
    }

    function testar(r) {
        HWB.loading('Testando ' + r.nome + '... (até ' + (r.timeout_conexao_s * 2 + r.timeout_comando_s * 3) + ' s)');
        HWB.api('roteador.testar', { id: r.id }, 'POST')
            .then(function (t) {
                $('#teste-titulo').text('Teste de acesso — ' + r.nome);
                $('#teste-corpo').html('<div style="margin-bottom:8px">' + HWB.badge(t.resultado) +
                    ' <span class="hwb-sub hwb-mono">' + HWB.esc(t.correlacao) + '</span></div>' + etapasHtml(t.etapas));
                HWB.abrirModal('modal-teste');
                carregar();
            })
            .catch(function (e) { HWB.erro(e); carregar(); })
            .finally(HWB.fimLoading);
    }

    function historico(r) {
        HWB.api('roteador.testes', { id: r.id }).then(function (d) {
            $('#teste-titulo').text('Histórico de testes — ' + r.nome);
            $('#teste-corpo').html(d.testes.length ? d.testes.map(function (t) {
                return '<div class="lc-section-panel hwb-mt" style="height:auto"><div class="lc-section-header"><span class="lc-section-title">' +
                    HWB.dataHora(t.em) + ' · ' + HWB.esc(t.por || '') + '</span>' + HWB.badge(t.resultado) + '</div>' +
                    '<div style="padding:4px 14px">' + etapasHtml(t.etapas) + '</div></div>';
            }).join('') : '<div class="lc-empty">Nenhum teste ainda.</div>');
            HWB.abrirModal('modal-teste');
        }).catch(HWB.erro);
    }

    function ativar(r) {
        HWB.confirmar({ titulo: r.ativo ? 'Desativar roteador' : 'Reativar roteador', textoOk: r.ativo ? 'Desativar' : 'Reativar',
                        msg: (r.ativo ? 'Desativar ' : 'Reativar ') + r.nome + '?',
                        sub: r.ativo ? 'Um roteador desativado não é testado nem consultado, e os assinantes dele somem da lista. O histórico fica.' : '' })
            .then(function (ok) {
                if (!ok) return;
                HWB.api('roteador.ativar', { id: r.id, ativo: r.ativo ? 0 : 1 }, 'POST').then(carregar).catch(HWB.erro);
            });
    }

    function remover(r) {
        HWB.confirmar({ titulo: 'Remover roteador', perigo: true, digitar: r.nome, textoOk: 'Remover',
                        msg: 'Remover ' + r.nome + ', a senha guardada e o histórico de testes?',
                        sub: 'A auditoria continua registrando o que foi feito com ele. Para só parar de usar, prefira desativar.' })
            .then(function (conf) {
                if (!conf) return;
                HWB.api('roteador.remover', { id: r.id, confirmacao: conf }, 'POST')
                    .then(function () { HWB.toast('ok', 'Roteador removido.'); carregar(); })
                    .catch(HWB.erro);
            });
    }

    $(function () {
        carregar();
        $('#btn-novo').on('click', function () { abrirForm(null); });
        $('#btn-salvar').on('click', salvar);
        $('#f-protocolo').on('change', function () { $('#f-porta').val(''); ajustarProtocolo(); });
        $('#hwb-linhas').on('click', 'button[data-acao]', function () {
            var r = porId(parseInt($(this).attr('data-id'), 10));
            if ($(this).attr('data-acao') === 'testar') { testar(r); return; }
            var itens = [{ texto: 'Histórico de testes', icone: 'bi-clock-history', acao: function () { historico(r); } }];
            if (PODE) {
                itens.push({ texto: 'Editar', icone: 'bi-pencil', acao: function () { abrirForm(r); } },
                           { texto: r.ativo ? 'Desativar' : 'Reativar', icone: r.ativo ? 'bi-pause-circle' : 'bi-play-circle', acao: function () { ativar(r); } },
                           '-', { texto: 'Remover', icone: 'bi-trash', perigo: true, acao: function () { remover(r); } });
            }
            HWB.menu(this, itens);
        });
    });
})();
</script>
</body>
</html>
