<?php
require_once __DIR__ . '/config.php';
$hwb_pagina = 'assinantes';
// A tela inicial abre para qualquer usuario do MK-AUTH: e nela que o primeiro administrador se
// apresenta. A lista so carrega para quem tem papel (cada operacao confere no servidor).
$hwb_perm_pagina = 'logado';
include('nav/header.php');
$hwb_cfg = $hwb_schema_ok ? [
    'intervalo_s'   => Config::int('trafego_intervalo_s'),
    'max_min'       => Config::int('trafego_max_min'),
    'fabricante'    => Config::ligado('mac_fabricante'),
    'pode_derrubar' => Permissao::tem('assinante.derrubar'),
] : [];
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

    <div class="lc-section-panel" style="height:auto;display:none" id="hwb-config-inicial">
        <div class="lc-section-header">
            <span class="lc-section-title"><i class="bi bi-list-check"></i> Configuração inicial</span>
            <span class="lc-badge-count" id="hwb-passos-conta">–</span>
        </div>
        <ul class="hwb-passos" id="hwb-passos"><li class="lc-loading">Carregando...</li></ul>
    </div>

    <div id="hwb-lista" style="display:none">
        <form class="lc-filter-bar" id="hwb-filtros" onsubmit="return false">
            <div class="lc-filter-group" id="grupo-roteador">
                <span class="lc-filter-label">Roteador</span>
                <select class="lc-input-date" id="f-roteador"></select>
            </div>
            <div class="lc-filter-group">
                <span class="lc-filter-label">Status</span>
                <select class="lc-input-date" id="f-status">
                    <option value="todos">Todos</option><option value="online">Online</option><option value="offline">Offline</option>
                </select>
            </div>
            <div class="lc-filter-group">
                <span class="lc-filter-label">Bloqueio</span>
                <select class="lc-input-date" id="f-bloqueio">
                    <option value="todos">Todos</option><option value="livre">Livres</option><option value="bloqueado">Bloqueados</option>
                </select>
            </div>
            <div class="lc-filter-group hwb-busca">
                <span class="lc-filter-label">Busca</span>
                <input type="text" class="lc-input-date w200" id="f-busca" maxlength="64" placeholder="nome, login, IP ou MAC">
            </div>
            <button type="submit" class="lc-btn-black" id="btn-buscar"><i class="bi bi-search"></i> Buscar</button>
            <a class="lc-btn-outline hwb-dir" id="btn-csv" href="exportar.php" target="_blank"><i class="bi bi-download"></i> Exportar CSV</a>
        </form>

        <div class="lc-section-panel" style="height:auto">
            <div class="lc-section-header">
                <span class="lc-section-title"><i class="bi bi-people"></i> Assinantes
                    <span class="lc-badge-count" id="hwb-total">0</span></span>
                <div class="hwb-acoes">
                    <span class="hwb-res online" id="hwb-online">0 online</span>
                    <span class="hwb-res offline" id="hwb-offline">0 offline</span>
                </div>
            </div>
            <div class="lc-table-wrap">
                <table class="lc-table">
                    <thead><tr>
                        <th>Status</th><th title="Bloqueio"></th><th>Login</th><th class="prio-6">IP</th>
                        <th class="prio-7">Início</th><th class="prio-6">Tempo</th><th class="prio-8">MAC</th>
                        <th class="prio-8">Download / Upload</th><th class="prio-8">Porta NAS</th><th></th>
                    </tr></thead>
                    <tbody id="hwb-linhas"><tr><td colspan="10" class="lc-loading">Carregando...</td></tr></tbody>
                </table>
            </div>
            <div class="hwb-paginacao" id="hwb-paginacao"></div>
        </div>
    </div>

<?php endif; ?>
</div>

<?php include('../../baixo.php'); ?>
<?php include('nav/comum.php'); ?>

<div class="lc-overlay" id="modal-conexoes" onclick="HWB.fecharSeFora(event, 'modal-conexoes')">
    <div class="lc-modal-box lg">
        <div class="lc-modal-header lc-modal-header-dark">
            <span class="lc-modal-title" id="conexoes-titulo">Conexões</span>
            <button type="button" class="lc-modal-close" onclick="HWB.fecharModal('modal-conexoes')">&times;</button>
        </div>
        <div class="lc-modal-body">
            <div class="lc-table-wrap" style="max-height:420px;overflow-y:auto">
                <table class="lc-table">
                    <thead><tr><th>IP</th><th>Início</th><th class="prio-6">Fim</th><th>Tempo</th><th class="prio-7">MAC</th>
                        <th class="prio-8">Download / Upload</th><th class="prio-6">Motivo</th></tr></thead>
                    <tbody id="conexoes-linhas"></tbody>
                </table>
            </div>
        </div>
        <div class="lc-modal-footer">
            <button type="button" class="lc-btn-cancel" onclick="HWB.fecharModal('modal-conexoes')">Fechar</button>
        </div>
    </div>
</div>

<div class="lc-overlay" id="modal-trafego" onclick="HWB.fecharSeFora(event, 'modal-trafego')">
    <div class="lc-modal-box lg">
        <div class="lc-modal-header lc-modal-header-dark">
            <span class="lc-modal-title" id="trafego-titulo">Tráfego</span>
            <button type="button" class="lc-modal-close" id="trafego-x">&times;</button>
        </div>
        <div class="lc-modal-body">
            <div class="hwb-trafego-cards">
                <div class="hwb-trafego-card">
                    <div class="lc-filter-label"><i class="bi bi-arrow-down"></i> Download</div>
                    <div class="hwb-trafego-valor down" id="t-down">–</div>
                    <div class="hwb-sub" id="t-down-det">IPv4 – · IPv6 –</div>
                </div>
                <div class="hwb-trafego-card">
                    <div class="lc-filter-label"><i class="bi bi-arrow-up"></i> Upload</div>
                    <div class="hwb-trafego-valor up" id="t-up">–</div>
                    <div class="hwb-sub" id="t-up-det">IPv4 – · IPv6 –</div>
                </div>
            </div>
            <div class="hwb-grafico"><canvas id="t-grafico"></canvas></div>
            <div class="hwb-sub hwb-mt" id="t-estado"></div>
        </div>
        <div class="lc-modal-footer">
            <button type="button" class="lc-btn-outline" id="t-retomar" style="display:none"><i class="bi bi-play-fill"></i> Retomar</button>
            <button type="button" class="lc-btn-cancel" id="t-fechar">Fechar</button>
        </div>
    </div>
</div>

<script src="js/vendor/chart.umd.min.js"></script>
<script src="../../menu.js<?= $ext_mk ?>"></script>
<script>
(function () {
    var CFG = <?= json_encode($hwb_cfg) ?>;
    var dados = { linhas: [], roteadores: [] };
    var pagina = 1;

    // ------------------------------------------------------------ formatacao
    function bytes(n) {
        if (!n) return '0 B';
        var u = ['B', 'KB', 'MB', 'GB', 'TB'], i = Math.min(u.length - 1, Math.floor(Math.log(n) / Math.log(1024)));
        return (n / Math.pow(1024, i)).toFixed(i ? 1 : 0).replace('.', ',') + ' ' + u[i];
    }
    function tempo(s) {
        if (!s) return '—';
        var d = Math.floor(s / 86400), h = Math.floor(s % 86400 / 3600), m = Math.floor(s % 3600 / 60);
        return d ? d + 'd ' + h + 'h' : (h ? h + 'h ' + m + 'm' : (m ? m + 'm ' + (s % 60) + 's' : s + 's'));
    }
    function curta(iso) {
        var m = String(iso || '').match(/^\d{4}-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
        return m ? m[2] + '/' + m[1] + ' ' + m[3] + ':' + m[4] : '—';
    }
    function mbps(v) { return Number(v || 0).toFixed(2).replace('.', ',') + ' Mbps'; }

    // ------------------------------------------------------------ inicio / configuracao
    function renderInicio(d) {
        $('#hwb-sem-admin').toggle(!d.ha_admin);
        $('#hwb-sem-acesso').toggle(d.ha_admin && !d.pode_ver);
        var c = d.contagens;
        var incompleto = !d.ha_admin || c.roteadores === 0 || c.roteadores_testados === 0;
        $('#hwb-config-inicial').toggle(incompleto && (d.pode_ver || !d.ha_admin));
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
        if (d.pode_ver && c.roteadores > 0) { $('#hwb-lista').show(); carregar(1); }
    }

    // ------------------------------------------------------------ lista
    function filtros() {
        return { roteador_id: $('#f-roteador').val() || 0, status: $('#f-status').val(),
                 bloqueio: $('#f-bloqueio').val(), busca: $.trim($('#f-busca').val()) };
    }

    function linha(a) {
        var cad = a.bloqueado ? '<i class="bi bi-lock-fill" style="color:#c0392b" title="Bloqueado"></i>'
                              : '<i class="bi bi-unlock" style="color:#1d9e75" title="Livre"></i>';
        var ip = a.ip ? (a.online ? '<a class="hwb-mono hwb-link" href="http://' + HWB.esc(a.ip) + '" target="_blank" rel="noopener" title="Acessar o equipamento">' + HWB.esc(a.ip) + '</a>'
                                  : '<span class="hwb-mono hwb-sub">' + HWB.esc(a.ip) + '</span>') : '—';
        if (a.ipv6) ip += ' <span class="hwb-tag" title="IPv6: ' + HWB.esc(a.ipv6) + '">v6</span>';
        var mac = a.mac ? (CFG.fabricante ? '<a class="hwb-mono hwb-link" data-acao="fabricante" data-mac="' + HWB.esc(a.mac) + '" title="Consultar o fabricante">' + HWB.esc(a.mac) + '</a>'
                                         : '<span class="hwb-mono">' + HWB.esc(a.mac) + '</span>') : '—';
        var acoes = '<div class="hwb-acoes">' +
            (CFG.pode_derrubar ? '<button type="button" class="lc-btn-outline hwb-btn-ico perigo" data-acao="derrubar" title="Derrubar a sessão"' + (a.online ? '' : ' disabled') + '><i class="bi bi-power"></i></button>' : '') +
            '<button type="button" class="lc-btn-outline hwb-btn-ico" data-acao="conexoes" title="Últimas conexões"><i class="bi bi-clock-history"></i></button>' +
            '<button type="button" class="lc-btn-outline hwb-btn-ico" data-acao="trafego" title="Tráfego em tempo real"' + (a.online ? '' : ' disabled') + '><i class="bi bi-graph-up"></i></button></div>';
        return '<tr data-login="' + HWB.esc(a.login) + '"><td>' + HWB.badge(a.online ? 'online' : 'offline') + '</td>' +
            '<td style="text-align:center">' + cad + '</td>' +
            '<td><strong>' + HWB.esc(a.login) + '</strong>' + (a.nome ? '<div class="hwb-sub">' + HWB.esc(a.nome) + '</div>' : '') + '</td>' +
            '<td class="prio-6">' + ip + '</td>' +
            '<td class="prio-7">' + curta(a.inicio) + '</td>' +
            '<td class="prio-6">' + (a.online ? tempo(a.segundos) : '<span class="hwb-sub" title="' + HWB.esc(a.motivo) + '">desde ' + curta(a.fim) + '</span>') + '</td>' +
            '<td class="prio-8">' + mac + '</td>' +
            '<td class="prio-8 hwb-sub"><span style="color:#3266ad">&darr;</span> ' + bytes(a.download) + ' &nbsp; <span style="color:#1d9e75">&uarr;</span> ' + bytes(a.upload) + '</td>' +
            '<td class="prio-8 hwb-mono hwb-sub">' + HWB.esc(a.porta) + '</td>' +
            '<td>' + acoes + '</td></tr>';
    }

    function render(d) {
        dados = d;
        var $r = $('#f-roteador'), atual = $r.val();
        if (!$r.children().length || $r.children().length !== d.roteadores.length + 1) {
            $r.html('<option value="0">Todos</option>' + d.roteadores.map(function (r) {
                return '<option value="' + r.id + '">' + HWB.esc(r.nome) + '</option>';
            }).join('')).val(atual || '0');
        }
        $('#grupo-roteador').toggle(d.roteadores.length > 1);
        $('#hwb-total').text(d.total);
        $('#hwb-online').text(d.online + ' online');
        $('#hwb-offline').text(d.offline + ' offline');
        $('#hwb-linhas').html(d.linhas.length ? d.linhas.map(linha).join('')
            : '<tr><td colspan="10" class="lc-empty">Nenhum assinante encontrado.</td></tr>');
        HWB.paginacao($('#hwb-paginacao'), d.pagina, d.por_pagina, d.total, carregar);
        $('#btn-csv').attr('href', 'exportar.php?' + $.param(filtros()));
    }

    function carregar(p) {
        pagina = p || 1;
        HWB.loading('Carregando assinantes...');
        return HWB.api('assinante.listar', $.extend(filtros(), { pagina: pagina }))
            .then(render).catch(HWB.erro).finally(HWB.fimLoading);
    }

    function porLogin(login) { return dados.linhas.filter(function (a) { return a.login === login; })[0]; }

    // ------------------------------------------------------------ acoes
    function conexoes(a) {
        HWB.api('assinante.conexoes', { login: a.login }).then(function (d) {
            $('#conexoes-titulo').text('Conexões — ' + a.login);
            $('#conexoes-linhas').html(d.conexoes.length ? d.conexoes.map(function (c) {
                var cor = c.online ? 'rgba(29,158,117,.08)' : (c.segundos < 600 ? 'rgba(192,57,43,.07)' : (c.segundos < 3600 ? 'rgba(224,161,0,.09)' : ''));
                return '<tr style="background:' + cor + '"><td class="hwb-mono">' + HWB.esc(c.ip || '—') + '</td><td>' + curta(c.inicio) + '</td>' +
                    '<td class="prio-6">' + (c.online ? HWB.badge('online') : curta(c.fim)) + '</td><td>' + tempo(c.segundos) + '</td>' +
                    '<td class="prio-7 hwb-mono hwb-sub">' + HWB.esc(c.mac || '—') + '</td>' +
                    '<td class="prio-8 hwb-sub">&darr; ' + bytes(c.download) + ' &nbsp; &uarr; ' + bytes(c.upload) + '</td>' +
                    '<td class="prio-6">' + HWB.esc(c.online ? '' : c.motivo) + '</td></tr>';
            }).join('') : '<tr><td colspan="7" class="lc-empty">Nenhuma conexão registrada.</td></tr>');
            HWB.abrirModal('modal-conexoes');
        }).catch(HWB.erro);
    }

    function derrubar(a) {
        HWB.confirmar({ titulo: 'Derrubar sessão', perigo: true, textoOk: 'Derrubar',
                        msg: 'Derrubar a sessão de ' + a.login + '?',
                        sub: 'O BRAS desconecta o assinante agora (MAC ' + a.mac + '); o equipamento dele reconecta sozinho. A ação fica na auditoria.' })
            .then(function (ok) {
                if (!ok) return;
                HWB.loading('Enviando ao BRAS...');
                HWB.api('assinante.derrubar', { login: a.login }, 'POST')
                    .then(function (r) {
                        HWB.toast('ok', 'Sessão de ' + r.login + ' derrubada.', 'MAC ' + r.mac + ' · ' + r.roteador);
                        setTimeout(function () { carregar(pagina); }, 1500);
                    })
                    .catch(HWB.erro)
                    .finally(HWB.fimLoading);
            });
    }

    function fabricante(mac) {
        HWB.api('assinante.fabricante', { mac: mac })
            .then(function (r) { HWB.toast(r.fabricante ? 'info' : 'avis', r.fabricante || 'Fabricante não encontrado na base pública.', 'MAC ' + mac); })
            .catch(HWB.erro);
    }

    // ------------------------------------------------------------ trafego
    var graf = null, timer = null, emCurso = false, falhas = 0, inicioT = 0, loginT = null;
    var MAX_PONTOS = 40;

    function criarGrafico() {
        if (graf) graf.destroy();
        var base = { tension: .35, borderWidth: 2, pointRadius: 0, pointHoverRadius: 4, fill: true };
        graf = new Chart(document.getElementById('t-grafico'), {
            type: 'line',
            data: { labels: [], datasets: [
                $.extend({ label: 'Download', data: [], borderColor: '#3266ad', backgroundColor: 'rgba(50,102,173,.10)' }, base),
                $.extend({ label: 'Upload', data: [], borderColor: '#1d9e75', backgroundColor: 'rgba(29,158,117,.10)' }, base)
            ] },
            options: {
                animation: false, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                scales: { y: { beginAtZero: true, ticks: { callback: function (v) { return v + ' Mbps'; } }, grid: { color: 'rgba(0,0,0,.06)' } },
                          x: { grid: { display: false }, ticks: { maxTicksLimit: 8 } } },
                plugins: { legend: { position: 'bottom' },
                           tooltip: { callbacks: { label: function (c) { return c.dataset.label + ': ' + mbps(c.parsed.y); } } } }
            }
        });
    }

    function lerTrafego() {
        if (emCurso || !loginT) return;
        if (Date.now() - inicioT > CFG.max_min * 60000) { pararTrafego('Parado automaticamente depois de ' + CFG.max_min + ' min.'); return; }
        emCurso = true;
        HWB.api('assinante.trafego', { login: loginT }, 'POST').then(function (t) {
            falhas = 0;
            var down = t.ipv4_down + t.ipv6_down, up = t.ipv4_up + t.ipv6_up;
            $('#t-down').text(mbps(down)); $('#t-up').text(mbps(up));
            $('#t-down-det').text('IPv4 ' + mbps(t.ipv4_down) + ' · IPv6 ' + mbps(t.ipv6_down));
            $('#t-up-det').text('IPv4 ' + mbps(t.ipv4_up) + ' · IPv6 ' + mbps(t.ipv6_up));
            graf.data.labels.push(t.lido_em);
            graf.data.datasets[0].data.push(down);
            graf.data.datasets[1].data.push(up);
            if (graf.data.labels.length > MAX_PONTOS) {
                graf.data.labels.shift(); graf.data.datasets[0].data.shift(); graf.data.datasets[1].data.shift();
            }
            graf.update('none');
            $('#t-estado').text('Lendo do ' + t.roteador + ' a cada ' + CFG.intervalo_s + ' s (MAC ' + t.mac + ').');
        }).catch(function (e) {
            falhas++;
            $('#t-estado').html('<span class="hwb-erro-txt">' + HWB.esc(e.mensagem) + '</span>');
            if (falhas >= 3 || e.codigo === 'HWB-ASS-002') pararTrafego('Leitura interrompida.');
        }).finally(function () { emCurso = false; });
    }

    function abrirTrafego(a) {
        loginT = a.login; falhas = 0; inicioT = Date.now();
        $('#trafego-titulo').text('Tráfego — ' + a.login);
        $('#t-down, #t-up').text('–'); $('#t-down-det, #t-up-det').text('IPv4 – · IPv6 –');
        $('#t-estado').text('Conectando ao BRAS...'); $('#t-retomar').hide();
        criarGrafico();
        HWB.abrirModal('modal-trafego');
        lerTrafego();
        timer = setInterval(lerTrafego, CFG.intervalo_s * 1000);
    }

    function pararTrafego(motivo) {
        if (timer) { clearInterval(timer); timer = null; }
        if (motivo) { $('#t-estado').append(' ' + HWB.esc(motivo)); $('#t-retomar').show(); }
    }

    function fecharTrafego() { pararTrafego(); loginT = null; HWB.fecharModal('modal-trafego'); }

    // ------------------------------------------------------------ eventos
    $(function () {
        HWB.api('inicio.estado').then(renderInicio).catch(HWB.erro);
        $('#hwb-filtros').on('submit', function () { carregar(1); return false; });
        $('#f-roteador, #f-status, #f-bloqueio').on('change', function () { carregar(1); });
        $('#hwb-linhas').on('click', '[data-acao]', function () {
            var $b = $(this), a = porLogin($b.closest('tr').attr('data-login'));
            switch ($b.attr('data-acao')) {
                case 'derrubar': derrubar(a); break;
                case 'conexoes': conexoes(a); break;
                case 'trafego': abrirTrafego(a); break;
                case 'fabricante': fabricante($b.attr('data-mac')); break;
            }
        });
        $('#trafego-x, #t-fechar').on('click', fecharTrafego);
        $('#t-retomar').on('click', function () {
            falhas = 0; inicioT = Date.now(); $(this).hide(); $('#t-estado').text('Retomando...');
            lerTrafego(); timer = setInterval(lerTrafego, CFG.intervalo_s * 1000);
        });
        // Aba esquecida aberta nao fica consultando o BRAS.
        document.addEventListener('visibilitychange', function () { if (document.hidden && timer) pararTrafego('Pausado: a aba ficou em segundo plano.'); });
        $('#btn-assumir').on('click', function () {
            HWB.confirmar({ titulo: 'Assumir administração', msg: 'Você será o administrador deste addon.',
                            sub: 'Esta ação fica registrada na auditoria e só pode ser feita enquanto não houver administrador.', textoOk: 'Assumir' })
                .then(function (ok) {
                    if (!ok) return;
                    HWB.loading('Gravando...');
                    HWB.api('permissao.assumir_admin', {}, 'POST')
                        .then(function () { HWB.toast('ok', 'Você agora é o administrador do addon.'); location.reload(); })
                        .catch(HWB.erro).finally(HWB.fimLoading);
                });
        });
    });
})();
</script>
</body>
</html>
