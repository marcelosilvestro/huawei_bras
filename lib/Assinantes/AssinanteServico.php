<?php
/**
 * huawei_bras :: assinantes PPPoE dos BRAS cadastrados.
 *
 * Tudo parte da ULTIMA sessao de cada assinante ativo no radacct, filtrada pelo NAS dos
 * roteadores ativos (tab_hwb_roteador.nas_ip = radacct.nasipaddress):
 *   online  = acctstoptime IS NULL AND acctsessiontime > 0
 *   offline = o resto.
 * O "acctsessiontime > 0" e essencial no Huawei: sem ele, sessoes fantasma entram como online.
 *
 * Desempenho (medido em producao, ~300 mil linhas de radacct): a ultima sessao sai de um JOIN
 * com o derivado MAX(radacctid) por username, e o filtro de assinantes ATIVOS entra DENTRO do
 * derivado. So as duas coisas juntas levam o MySQL a usar o indice por username: 4,2 s -> 0,6 s.
 *
 * Tabelas do MK-AUTH (sis_cliente, sis_adicional, radacct, nas) sao SOMENTE LEITURA.
 */
require_once __DIR__ . '/../Bras/RoteadorServico.php';

final class AssinanteServico
{
    public const STATUS = ['todos', 'online', 'offline'];
    public const BLOQUEIO = ['todos', 'livre', 'bloqueado'];

    public const MOTIVOS = [
        'User-Request' => 'Solicitado pelo usuário', 'Lost-Carrier' => 'Queda de conexão',
        'Lost-Service' => 'Serviço perdido', 'Idle-Timeout' => 'Tempo ocioso',
        'Session-Timeout' => 'Tempo de sessão esgotado', 'Admin-Reset' => 'Derrubado pelo administrador',
        'Admin-Reboot' => 'Reinício pelo administrador', 'Port-Error' => 'Erro na porta',
        'NAS-Error' => 'Erro no NAS', 'NAS-Request' => 'Solicitado pelo NAS', 'NAS-Reboot' => 'Reinício do NAS',
        'Port-Unneeded' => 'Porta desnecessária', 'Port-Preempted' => 'Porta ocupada',
        'Port-Suspended' => 'Porta suspensa', 'Service-Unavailable' => 'Serviço indisponível',
        'Callback' => 'Retorno de chamada', 'User-Error' => 'Erro do usuário', 'Host-Request' => 'Solicitado pelo host',
        'Supplicant-Restart' => 'Reinício do suplicante', 'Reauthentication-Failure' => 'Falha na reautenticação',
        'Port-Reinit' => 'Reinicialização da porta', 'MAC-Limit' => 'Limite de MAC',
    ];

    /**
     * Assinantes ativos: clientes (sis_cliente) + logins adicionais (sis_adicional), ambos com o
     * contrato ativo. O adicional entra pelo proprio login PPPoE e herda o cli_ativado do contrato.
     */
    private const ATIVOS = "
        SELECT sc.login, sc.nome, sc.bloqueado
          FROM sis_cliente sc
         WHERE sc.cli_ativado = 's'
        UNION ALL
        SELECT adi.username AS login, COALESCE(NULLIF(adi.nome, ''), cli.nome) AS nome, adi.bloqueado
          FROM sis_adicional adi
          JOIN sis_cliente cli ON adi.login = cli.login
         WHERE cli.cli_ativado = 's'";

    private const ONLINE = '(r1.acctstoptime IS NULL AND r1.acctsessiontime > 0)';

    /**
     * Lista paginada.
     * @param array{roteador_id?:int,busca?:string,status?:string,bloqueio?:string} $f
     * @param int $porPagina 0 = sem paginacao (exportacao)
     */
    public static function listar(array $f, int $pagina = 1, int $porPagina = 50): array
    {
        $rots = self::roteadoresDoFiltro((int) ($f['roteador_id'] ?? 0));
        $vazio = ['total' => 0, 'online' => 0, 'offline' => 0, 'pagina' => 1, 'por_pagina' => $porPagina, 'linhas' => []];
        if (!$rots) {
            return $vazio;
        }
        [$base, $where, $p] = self::montar($rots, $f);

        $tot = Db::um('SELECT COUNT(*) AS total, COALESCE(SUM(CASE WHEN ' . self::ONLINE . ' THEN 1 ELSE 0 END), 0) AS online '
                      . $base . $where, $p);
        $total = (int) $tot['total'];
        $online = (int) $tot['online'];

        $pagina = max(1, $pagina);
        $limite = '';
        if ($porPagina > 0) {
            $paginas = max(1, (int) ceil($total / $porPagina));
            $pagina = min($pagina, $paginas);
            $limite = ' LIMIT ' . $porPagina . ' OFFSET ' . (($pagina - 1) * $porPagina);
        }
        $linhas = Db::todos('SELECT r1.radacctid, r1.username, r1.nasipaddress, r1.nasportid, r1.acctstarttime, r1.acctstoptime,
                                    r1.acctsessiontime, r1.acctinputoctets, r1.acctoutputoctets, r1.callingstationid,
                                    r1.framedipaddress, r1.delegatedipv6address, r1.acctterminatecause, sc.nome, sc.bloqueado '
                            . $base . $where . ' ORDER BY r1.radacctid DESC' . $limite, $p);

        $porNas = array_column($rots, null, 'nas_ip');
        $saida = [];
        foreach ($linhas as $l) {
            $saida[] = self::paraTela($l, $porNas[$l['nasipaddress']] ?? null);
        }
        return ['total' => $total, 'online' => $online, 'offline' => $total - $online,
                'pagina' => $pagina, 'por_pagina' => $porPagina, 'linhas' => $saida];
    }

    /** Ultimas conexoes de um login (qualquer NAS), para o modal de historico. */
    public static function conexoes(string $login, int $limite = 20): array
    {
        $login = Validar::assinante($login);
        $nomes = [];
        foreach (Db::todos('SELECT nome, nas_ip FROM tab_hwb_roteador') as $r) {
            $nomes[$r['nas_ip']] = $r['nome'];
        }
        $saida = [];
        foreach (Db::todos('SELECT radacctid, nasipaddress, framedipaddress, acctstarttime, acctstoptime, acctsessiontime,
                                   callingstationid, acctinputoctets, acctoutputoctets, acctterminatecause
                              FROM radacct WHERE username = ? ORDER BY radacctid DESC LIMIT ' . max(1, min(100, $limite)), [$login]) as $c) {
            $saida[] = [
                'inicio'    => $c['acctstarttime'],
                'fim'       => $c['acctstoptime'],
                'segundos'  => (int) $c['acctsessiontime'],
                'online'    => $c['acctstoptime'] === null && (int) $c['acctsessiontime'] > 0,
                'ip'        => (string) $c['framedipaddress'],
                'mac'       => (string) $c['callingstationid'],
                'download'  => (int) $c['acctoutputoctets'],
                'upload'    => (int) $c['acctinputoctets'],
                'motivo'    => self::motivo($c['acctterminatecause']),
                'nas'       => (string) $c['nasipaddress'],
                'roteador'  => $nomes[$c['nasipaddress']] ?? null,
            ];
        }
        return $saida;
    }

    /** Velocidade em tempo real (le o BRAS da sessao aberta do assinante). */
    public static function trafego(string $login): array
    {
        [$sessao, $rot, $mac] = self::sessaoAberta($login);
        $t = RoteadorServico::executarLeitura((int) $rot['id'], fn(DriverVrp $d) => $d->trafegoPorMac($mac), null, 3);
        if ($t === null) {
            throw new HwbErro('HWB-ASS-002', ['login' => $sessao['username']]);
        }
        return $t + ['mac' => $mac, 'roteador' => $rot['nome'], 'lido_em' => date('H:i:s')];
    }

    /**
     * Derruba a sessao aberta do assinante no BRAS dela. Auditado sempre, com sucesso ou nao.
     * O transporte injetado serve aos testes.
     */
    public static function derrubar(string $login, string $usuario, ?Transporte $transporte = null): array
    {
        [$sessao, $rot, $mac] = self::sessaoAberta($login);
        $ctx = ['login' => $sessao['username'], 'mac' => $mac, 'roteador' => $rot['nome'], 'nas' => $rot['nas_ip'],
                'ip' => $sessao['framedipaddress']];
        try {
            $r = RoteadorServico::executarLeitura((int) $rot['id'], fn(DriverVrp $d) => $d->cortarPorMac($mac), $transporte);
        } catch (Throwable $e) {
            Auditoria::registrar('assinante_derrubar', 'assinante', null, null,
                $ctx + ['resultado' => 'erro', 'erro' => $e instanceof HwbErro ? $e->codigo() : get_class($e)]);
            throw $e;
        }
        $ok = ($r['cortados'] ?? 0) >= 1;
        Auditoria::registrar('assinante_derrubar', 'assinante', null, null,
            $ctx + ['resultado' => $ok ? 'ok' : 'nao_confirmado', 'cortados' => $r['cortados'],
                    'resposta' => mb_substr(trim($r['saida']), 0, 300)]);
        Log::info('assinante.derrubar', $ctx + ['ok' => $ok]);
        if (!$ok) {
            throw new HwbErro('HWB-ASS-004', ['resposta' => mb_substr(trim($r['saida']), 0, 200)], null, 502);
        }
        return ['login' => $sessao['username'], 'mac' => $mac, 'roteador' => $rot['nome']];
    }

    /**
     * Fabricante do MAC pelo api.macvendors.com (consulta feita pelo servidor; o navegador nao
     * fala com terceiros). Desligavel em Configuracoes.
     */
    public static function fabricante(string $mac): array
    {
        if (!Config::ligado('mac_fabricante')) {
            throw new HwbErro('HWB-SYS-002', [], 'A consulta do fabricante do MAC está desligada em Configurações.');
        }
        $macHw = Validar::macHuawei($mac);
        if (!function_exists('curl_init')) {
            throw new HwbErro('HWB-SYS-001', [], 'O PHP deste servidor não tem a extensão curl.');
        }
        $hex = str_replace('-', '', $macHw);
        $consulta = implode(':', str_split(substr($hex, 0, 6), 2));
        $ch = curl_init('https://api.macvendors.com/' . rawurlencode($consulta));
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_USERAGENT => 'huawei_bras-mkauth']);
        $corpo = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($http === 200 && is_string($corpo) && trim($corpo) !== '') {
            return ['mac' => $macHw, 'fabricante' => mb_substr(trim(strip_tags($corpo)), 0, 120)];
        }
        if ($http === 404) {
            return ['mac' => $macHw, 'fabricante' => null];
        }
        throw new HwbErro('HWB-SYS-001', ['http' => $http],
            $http === 429 ? 'Limite de consultas do macvendors.com atingido. Aguarde alguns segundos.' : 'Não foi possível consultar o fabricante agora.');
    }

    /** Roteadores ativos para o seletor da tela. */
    public static function roteadoresAtivos(): array
    {
        return array_map(fn($r) => ['id' => (int) $r['id'], 'nome' => $r['nome'], 'nas_ip' => $r['nas_ip']],
            Db::todos('SELECT id, nome, nas_ip FROM tab_hwb_roteador WHERE ativo = 1 ORDER BY nome'));
    }

    // ---------------------------------------------------------------- apoio

    /** @return array<int,array{id:int,nome:string,nas_ip:string}> */
    private static function roteadoresDoFiltro(int $roteadorId): array
    {
        $rots = self::roteadoresAtivos();
        if ($roteadorId > 0) {
            $rots = array_values(array_filter($rots, fn($r) => $r['id'] === $roteadorId));
            if (!$rots) {
                throw new HwbErro('HWB-ROT-011');
            }
        }
        return $rots;
    }

    /** @return array{0:string,1:string,2:array} FROM, WHERE e parametros (posicionais). */
    private static function montar(array $rots, array $f): array
    {
        $in = implode(',', array_fill(0, count($rots), '?'));
        $base = ' FROM (' . self::ATIVOS . ') sc
                  INNER JOIN (
                      SELECT ra.username, MAX(ra.radacctid) AS maxid
                        FROM radacct ra
                        JOIN (' . self::ATIVOS . ') a ON a.login = ra.username
                       WHERE ra.nasipaddress IN (' . $in . ')
                       GROUP BY ra.username
                  ) ult ON ult.username = sc.login
                  INNER JOIN radacct r1 ON r1.radacctid = ult.maxid';
        $p = array_column($rots, 'nas_ip');

        $cond = [];
        $busca = Validar::texto($f['busca'] ?? '', 64);
        if ($busca !== '') {
            $like = '%' . addcslashes($busca, '%_\\') . '%';
            $cond[] = '(sc.login LIKE ? OR sc.nome LIKE ? OR r1.framedipaddress LIKE ? OR r1.callingstationid LIKE ?)';
            array_push($p, $like, $like, $like, $like);
        }
        $status = (string) ($f['status'] ?? 'todos');
        if (!in_array($status, self::STATUS, true)) {
            throw new HwbErro('HWB-SYS-002', ['campo' => 'status']);
        }
        if ($status === 'online') {
            $cond[] = self::ONLINE;
        } elseif ($status === 'offline') {
            $cond[] = 'NOT ' . self::ONLINE;
        }
        $bloq = (string) ($f['bloqueio'] ?? 'todos');
        if (!in_array($bloq, self::BLOQUEIO, true)) {
            throw new HwbErro('HWB-SYS-002', ['campo' => 'bloqueio']);
        }
        if ($bloq === 'livre') {
            $cond[] = "sc.bloqueado = 'nao'";
        } elseif ($bloq === 'bloqueado') {
            $cond[] = "sc.bloqueado = 'sim'";
        }
        return [$base, $cond ? ' WHERE ' . implode(' AND ', $cond) : '', $p];
    }

    /**
     * Sessao aberta do login, o roteador ativo dono do NAS dela e o MAC no formato Huawei.
     * @return array{0:array,1:array,2:string}
     */
    private static function sessaoAberta(string $login): array
    {
        $login = Validar::assinante($login);
        $s = Db::um('SELECT radacctid, username, nasipaddress, callingstationid, framedipaddress, acctstoptime, acctsessiontime
                       FROM radacct WHERE username = ? ORDER BY radacctid DESC LIMIT 1', [$login]);
        if ($s === null) {
            throw new HwbErro('HWB-ASS-001', ['login' => $login], null, 404);
        }
        if (!($s['acctstoptime'] === null && (int) $s['acctsessiontime'] > 0)) {
            throw new HwbErro('HWB-ASS-002', ['login' => $login]);
        }
        $rot = Db::um('SELECT * FROM tab_hwb_roteador WHERE nas_ip = ? AND ativo = 1', [$s['nasipaddress']]);
        if ($rot === null) {
            throw new HwbErro('HWB-ASS-003', ['nas' => $s['nasipaddress']]);
        }
        return [$s, $rot, Validar::macHuawei($s['callingstationid'])];
    }

    private static function paraTela(array $l, ?array $rot): array
    {
        $online = $l['acctstoptime'] === null && (int) $l['acctsessiontime'] > 0;
        return [
            'login'     => (string) $l['username'],
            'nome'      => (string) ($l['nome'] ?? ''),
            'bloqueado' => strtolower((string) $l['bloqueado']) === 'sim',
            'online'    => $online,
            'ip'        => (string) $l['framedipaddress'],
            'ipv6'      => (string) ($l['delegatedipv6address'] ?? ''),
            'inicio'    => $l['acctstarttime'],
            'fim'       => $l['acctstoptime'],
            'segundos'  => (int) $l['acctsessiontime'],
            'mac'       => (string) $l['callingstationid'],
            // Visto do NAS: input = o que o assinante ENVIOU (upload); output = o que ele RECEBEU (download).
            'download'  => (int) $l['acctoutputoctets'],
            'upload'    => (int) $l['acctinputoctets'],
            'porta'     => (string) $l['nasportid'],
            'motivo'    => $online ? '' : self::motivo($l['acctterminatecause']),
            'roteador'  => $rot['nome'] ?? null,
        ];
    }

    private static function motivo($m): string
    {
        $m = (string) $m;
        return self::MOTIVOS[$m] ?? ($m !== '' ? $m : '—');
    }
}
