<?php
/**
 * huawei_bras :: cadastro e teste dos roteadores (BRAS).
 *
 * Regras:
 *   - nada de IP/usuario/senha fixo: tudo vem daqui, cadastrado na tela;
 *   - senha so no cofre; a tela recebe apenas "tem senha: sim/nao";
 *   - o NAS (IP com que o BRAS aparece no RADIUS) e escolhido da tabela nas do MK-AUTH, que e
 *     so lida; um NAS pertence a no maximo um roteador;
 *   - versao, modelo e nome do equipamento sao DETECTADOS no teste, nunca digitados;
 *   - se o equipamento que responde muda de nome entre dois testes, o resultado e AVISO: pode
 *     ser outro equipamento no mesmo IP;
 *   - falhas de login seguidas bloqueiam novas tentativas por um tempo (nao travar a conta no BRAS);
 *   - uma operacao por roteador de cada vez (GET_LOCK), contra outra aba.
 */
require_once __DIR__ . '/Transporte.php';
require_once __DIR__ . '/TransporteSsh.php';
require_once __DIR__ . '/TransporteSimulado.php';
require_once __DIR__ . '/Huawei/DriverVrp.php';

final class RoteadorServico
{
    public const PROTOCOLOS = [
        'ssh'      => ['rotulo' => 'SSH', 'porta' => 22],
        'simulado' => ['rotulo' => 'Simulado (sem equipamento)', 'porta' => 22],
    ];

    public const TITULOS_ETAPA = [
        'tcp'           => 'Conexão TCP',
        'login'         => 'Login SSH',
        'identificacao' => 'Identificação do equipamento',
        'modelo'        => 'Modelo e driver',
        'sessoes'       => 'Assinantes online no BRAS',
        'radius'        => 'Sessões abertas no RADIUS',
        'identidade'    => 'Mesmo equipamento do teste anterior',
    ];

    private const CAMPOS_LISTA = 'id, nome, modelo, protocolo, host, porta, usuario, nas_ip, timeout_conexao_s, timeout_comando_s,
        versao_detectada, identificador_detectado, modelo_detectado, sessoes_detectadas, ultimo_teste_em,
        ultimo_teste_resultado, ultimo_teste_detalhe, falhas_auth, bloqueado_ate, observacao, ativo, versao,
        criado_por, criado_em, alterado_por, alterado_em, desativado_em';

    /** @return array<int,array> */
    public static function listar(): array
    {
        $linhas = Db::todos('SELECT ' . self::CAMPOS_LISTA . ' FROM tab_hwb_roteador ORDER BY ativo DESC, nome');
        return array_map([self::class, 'paraTela'], $linhas);
    }

    public static function obter(int $id): array
    {
        return self::paraTela(self::linha($id));
    }

    /**
     * NAS cadastrados no MK-AUTH (somente leitura), com o roteador que ja usa cada um.
     * Nunca le a coluna de senha da tabela nas.
     * @return array<int,array{ip:string,nome:string,descricao:string,roteador_id:?int,roteador:?string}>
     */
    public static function nasDisponiveis(): array
    {
        if (!Db::tabelaExiste('nas')) {
            return [];
        }
        $uso = [];
        foreach (Db::todos('SELECT id, nome, nas_ip FROM tab_hwb_roteador') as $r) {
            $uso[$r['nas_ip']] = $r;
        }
        $saida = [];
        foreach (Db::todos("SELECT nasname, shortname, description FROM nas
                             WHERE nasname IS NOT NULL AND nasname <> '' ORDER BY shortname, nasname") as $n) {
            $ip = (string) $n['nasname'];
            $saida[] = [
                'ip'          => $ip,
                'nome'        => (string) ($n['shortname'] ?? ''),
                'descricao'   => (string) ($n['description'] ?? ''),
                'roteador_id' => isset($uso[$ip]) ? (int) $uso[$ip]['id'] : null,
                'roteador'    => $uso[$ip]['nome'] ?? null,
            ];
        }
        return $saida;
    }

    /**
     * Cria (id = 0) ou altera. Senha vazia na alteracao mantem a atual.
     * @return array o roteador gravado, no formato da tela
     */
    public static function salvar(array $e, string $usuario): array
    {
        $id = (int) ($e['id'] ?? 0);
        $protocolo = (string) ($e['protocolo'] ?? 'ssh');
        if (!isset(self::PROTOCOLOS[$protocolo])) {
            throw new HwbErro('HWB-SYS-002', ['campo' => 'protocolo']);
        }
        $simulado = $protocolo === 'simulado';

        $modelo = (string) ($e['modelo'] ?? 'NE8000');
        if (!isset(DriverVrp::MODELOS[$modelo])) {
            throw new HwbErro('HWB-ROT-012', ['modelo' => $modelo]);
        }

        $d = [
            'nome'              => Validar::nome($e['nome'] ?? '', 80),
            'modelo'            => $modelo,
            'protocolo'         => $protocolo,
            'host'              => $simulado ? 'simulado' : Validar::host($e['host'] ?? ''),
            'porta'             => $simulado ? 22 : Validar::porta($e['porta'] ?? self::PROTOCOLOS[$protocolo]['porta']),
            'usuario'           => $simulado ? 'simulado' : Validar::usuarioRemoto($e['usuario'] ?? ''),
            'nas_ip'            => self::validarNas($e['nas_ip'] ?? ''),
            'timeout_conexao_s' => Validar::inteiro($e['timeout_conexao_s'] ?? 10, 3, 60),
            'timeout_comando_s' => Validar::inteiro($e['timeout_comando_s'] ?? 15, 5, 120),
            'observacao'        => Validar::texto($e['observacao'] ?? '', 500),
        ];
        $senha = (string) ($e['senha'] ?? '');
        $senha = $senha !== '' && !$simulado ? Validar::senhaRemota($senha) : '';

        return Db::transacao(function () use ($id, $d, $senha, $simulado, $usuario, $e) {
            if ($id === 0) {
                if (!$simulado && $senha === '') {
                    throw new HwbErro('HWB-ROT-003');
                }
                try {
                    Db::exec('INSERT INTO tab_hwb_roteador (nome, modelo, protocolo, host, porta, usuario, nas_ip, timeout_conexao_s,
                                   timeout_comando_s, observacao, criado_por, criado_em)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                        [$d['nome'], $d['modelo'], $d['protocolo'], $d['host'], $d['porta'], $d['usuario'], $d['nas_ip'],
                         $d['timeout_conexao_s'], $d['timeout_comando_s'], $d['observacao'], $usuario]);
                } catch (PDOException $ex) {
                    self::traduzirDuplicado($ex);
                }
                $id = Db::ultimoId();
                $antes = null;
            } else {
                $antes = self::linha($id);
                $versaoLida = Validar::inteiro($e['versao'] ?? 0, 1, PHP_INT_MAX);
                if (!$simulado && $senha === '' && !Cofre::existe('roteador', $id)) {
                    throw new HwbErro('HWB-ROT-003');
                }
                // Mudou como se chega ao roteador: o teste anterior deixa de valer.
                $mudouAcesso = $antes['host'] !== $d['host'] || (int) $antes['porta'] !== $d['porta']
                            || $antes['protocolo'] !== $d['protocolo'] || $antes['usuario'] !== $d['usuario']
                            || $antes['modelo'] !== $d['modelo'];
                try {
                    $n = Db::exec('UPDATE tab_hwb_roteador SET nome = ?, modelo = ?, protocolo = ?, host = ?, porta = ?, usuario = ?,
                                          nas_ip = ?, timeout_conexao_s = ?, timeout_comando_s = ?, observacao = ?,
                                          versao = versao + 1, alterado_por = ?, alterado_em = NOW()'
                                  . ($mudouAcesso ? ', ultimo_teste_resultado = NULL, ultimo_teste_detalhe = NULL' : '')
                                  . ($senha !== '' ? ', falhas_auth = 0, bloqueado_ate = NULL' : '') .
                                  ' WHERE id = ? AND versao = ?',
                        [$d['nome'], $d['modelo'], $d['protocolo'], $d['host'], $d['porta'], $d['usuario'], $d['nas_ip'],
                         $d['timeout_conexao_s'], $d['timeout_comando_s'], $d['observacao'], $usuario, $id, $versaoLida]);
                } catch (PDOException $ex) {
                    self::traduzirDuplicado($ex);
                }
                if ($n !== 1) {
                    throw new HwbErro('HWB-CONC-001', [], null, 409);
                }
            }
            if ($senha !== '') {
                Cofre::guardar('roteador', $id, $senha, $usuario);
            } elseif ($simulado) {
                Cofre::apagar('roteador', $id);
            }

            $depois = self::linha($id);
            Auditoria::registrar($antes === null ? 'roteador_criar' : 'roteador_alterar', 'roteador', $id,
                $antes === null ? null : self::paraAuditoria($antes),
                // "credencial", nao "senha": campo com "senha" no nome e mascarado pelo Log::mascarar.
                self::paraAuditoria($depois) + ['credencial_trocada' => $senha !== '']);
            return self::paraTela($depois);
        });
    }

    public static function definirAtivo(int $id, bool $ativo, string $usuario): array
    {
        $antes = self::linha($id);
        Db::exec('UPDATE tab_hwb_roteador SET ativo = ?, desativado_em = ?, versao = versao + 1, alterado_por = ?, alterado_em = NOW()
                   WHERE id = ?', [$ativo ? 1 : 0, $ativo ? null : date('Y-m-d H:i:s'), $usuario, $id]);
        Auditoria::registrar($ativo ? 'roteador_reativar' : 'roteador_desativar', 'roteador', $id,
            ['ativo' => (int) $antes['ativo']], ['ativo' => $ativo ? 1 : 0]);
        return self::obter($id);
    }

    /**
     * Remove o roteador, a senha e o historico de testes. Exige o nome digitado. A auditoria
     * (inclusive as quedas de assinante feitas por ele) fica.
     */
    public static function remover(int $id, string $confirmacao, string $usuario): void
    {
        $r = self::linha($id);
        if ($confirmacao !== $r['nome']) {
            throw new HwbErro('HWB-ROT-016');
        }
        Db::transacao(function () use ($id, $r) {
            Cofre::apagar('roteador', $id);
            Db::exec('DELETE FROM tab_hwb_teste_conectividade WHERE roteador_id = ?', [$id]);
            Db::exec('DELETE FROM tab_hwb_roteador WHERE id = ?', [$id]);
            Auditoria::registrar('roteador_remover', 'roteador', $id, self::paraAuditoria($r), null);
        });
    }

    /**
     * Teste de acesso: TCP -> login -> identificacao -> modelo -> sessoes -> RADIUS -> identidade.
     * Falha de rede/login NAO lanca: vira etapa com erro, gravada no historico.
     * Lanca HwbErro so quando o teste nem pode comecar (desativado, bloqueado, em uso, sem senha).
     */
    public static function testar(int $id, string $usuario, ?Transporte $transporte = null): array
    {
        $r = self::linha($id);
        if (!(int) $r['ativo']) {
            throw new HwbErro('HWB-ROT-011');
        }
        self::exigirDesbloqueado($r);
        $trava = 'hwb_rot_' . $id;
        if (!Db::travar($trava, 0)) {
            throw new HwbErro('HWB-ROT-013', [], null, 409);
        }

        $correlacao = 'TST-ROT' . $id . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(2));
        $etapas = [];
        $ident = null;
        $sessoes = null;
        $falhaAuth = false;
        $loginOk = false;
        $t = null;

        try {
            // 1. TCP: separa "nao alcanca" de "alcanca mas recusa o login".
            if ($transporte === null && $r['protocolo'] === 'ssh') {
                $t0 = microtime(true);
                $erroTcp = self::tcp($r['host'], (int) $r['porta'], (int) $r['timeout_conexao_s']);
                $etapas[] = self::etapa('tcp', $erroTcp === null ? 'ok' : 'erro',
                    $erroTcp === null ? $r['host'] . ':' . $r['porta'] . ' respondeu.' : Erros::mensagem('HWB-ROT-006') . ' (' . $erroTcp . ')', $t0);
            }

            // 2. Login.
            if (self::semErro($etapas)) {
                $t0 = microtime(true);
                try {
                    $t = $transporte ?? self::transporte($r);
                    $t->conectar();
                    $loginOk = true;
                    $etapas[] = self::etapa('login', 'ok', 'Conectado como ' . $r['usuario'] . ' (' . $r['protocolo'] . ').', $t0);
                } catch (BrasFalha $f) {
                    $falhaAuth = $f->tipo() === 'autenticacao';
                    $etapas[] = self::etapa('login', 'erro', self::textoFalha($f), $t0);
                } catch (HwbErro $z) {
                    $etapas[] = self::etapa('login', 'erro', $z->getMessage(), $t0);
                }
            }

            if ($loginOk) {
                $drv = new DriverVrp($t);

                // 3. Identificacao.
                $t0 = microtime(true);
                try {
                    $ident = $drv->identificar();
                    $etapas[] = self::etapa('identificacao', 'ok',
                        sprintf('%s — %s, VRP %s%s.', $ident['identificador'] ?: 'sem nome', $ident['modelo'] ?: 'modelo não informado',
                            $ident['versao'], $ident['uptime'] ? ', ligado há ' . $ident['uptime'] : ''), $t0);
                } catch (BrasFalha $f) {
                    $etapas[] = self::etapa('identificacao', 'erro', self::textoFalha($f), $t0);
                }

                // 4. Modelo cadastrado x detectado x driver.
                if ($ident !== null) {
                    $m = DriverVrp::MODELOS[$r['modelo']];
                    if (!DriverVrp::modeloConfere($r['modelo'], $ident['modelo'])) {
                        $etapas[] = self::etapa('modelo', 'aviso', 'O equipamento responde como "' . $ident['modelo'] .
                            '", mas o cadastro diz ' . $m['rotulo'] . '. Confira o cadastro.', microtime(true));
                    } elseif (!$m['validado']) {
                        $etapas[] = self::etapa('modelo', 'aviso', $m['rotulo'] . ' usa o mesmo VRP do NE8000, mas ainda não foi conferido num equipamento real.', microtime(true));
                    } else {
                        $etapas[] = self::etapa('modelo', 'ok', $m['rotulo'] . ': driver validado.', microtime(true));
                    }
                }

                // 5. Sessoes no BRAS (informativo: permissao do usuario pode nao alcancar o comando).
                $t0 = microtime(true);
                try {
                    $sessoes = $drv->sessoesOnline();
                    $etapas[] = $sessoes === null
                        ? self::etapa('sessoes', 'aviso', 'O BRAS respondeu num formato que o addon não reconhece.', $t0)
                        : self::etapa('sessoes', 'ok', number_format($sessoes, 0, ',', '.') . ' assinante(s) online.', $t0);
                } catch (BrasFalha $f) {
                    $etapas[] = self::etapa('sessoes', 'aviso', self::textoFalha($f) . ' Confira o nível de acesso do usuário no BRAS.', $t0);
                }

                // 6. RADIUS: o NAS cadastrado tem as sessoes que o BRAS diz ter?
                $etapas[] = self::etapaRadius($r['nas_ip'], $sessoes);

                // 7. Identidade.
                $anterior = (string) ($r['identificador_detectado'] ?? '');
                if ($ident !== null && $anterior !== '' && $ident['identificador'] !== '' && $anterior !== $ident['identificador']) {
                    $etapas[] = self::etapa('identidade', 'aviso', 'Antes respondia como "' . $anterior . '", agora como "' .
                        $ident['identificador'] . '". Confirme que o endereço aponta para o roteador certo.', microtime(true));
                }
            }
        } finally {
            if ($t !== null) {
                $t->fechar();
            }
            Db::destravar($trava);
        }

        $etapasValidas = array_filter($etapas, fn($e) => $e['resultado'] !== 'nao_testavel');
        $resultado = Diagnostico::pior($etapasValidas);
        $resumo = implode(' | ', array_map(fn($e) => $e['titulo'] . ': ' . $e['detalhe'],
                      array_filter($etapas, fn($e) => !in_array($e['resultado'], ['ok', 'nao_testavel'], true))))
               ?: 'Acesso e identificação ok.';

        Db::transacao(function () use ($id, $r, $ident, $sessoes, $resultado, $resumo, $falhaAuth, $loginOk, $etapas, $correlacao, $usuario) {
            $sets = ['ultimo_teste_em = NOW()', 'ultimo_teste_resultado = ?', 'ultimo_teste_detalhe = ?'];
            $p = [$resultado, mb_substr($resumo, 0, 500)];
            if ($ident !== null) {
                array_push($sets, 'versao_detectada = ?', 'identificador_detectado = ?', 'modelo_detectado = ?');
                array_push($p, mb_substr($ident['versao'], 0, 120), mb_substr($ident['identificador'], 0, 120), mb_substr($ident['modelo'], 0, 80));
            }
            if ($loginOk) {
                $sets[] = 'sessoes_detectadas = ?';
                $p[] = $sessoes;
            }
            if ($falhaAuth) {
                $falhas = (int) $r['falhas_auth'] + 1;
                $sets[] = 'falhas_auth = ?';
                $p[] = $falhas;
                if ($falhas >= Config::int('bloqueio_auth_falhas')) {
                    $sets[] = 'bloqueado_ate = NOW() + INTERVAL ? MINUTE';
                    $p[] = Config::int('bloqueio_auth_min');
                }
            } elseif ($loginOk) {
                $sets[] = 'falhas_auth = 0';
                $sets[] = 'bloqueado_ate = NULL';
            }
            $p[] = $id;
            Db::exec('UPDATE tab_hwb_roteador SET ' . implode(', ', $sets) . ' WHERE id = ?', $p);

            foreach ($etapas as $e) {
                Db::exec('INSERT INTO tab_hwb_teste_conectividade (roteador_id, etapa, resultado, detalhe, duracao_ms, correlacao, criado_por, criado_em)
                          VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
                    [$id, $e['etapa'], $e['resultado'], mb_substr($e['detalhe'], 0, 500), $e['ms'], $correlacao, $usuario]);
            }
            Auditoria::registrar('roteador_testar', 'roteador', $id, null,
                ['resultado' => $resultado, 'versao' => $ident['versao'] ?? null, 'sessoes' => $sessoes], $correlacao);
        });
        Log::info('roteador.testar', ['roteador' => $id, 'resultado' => $resultado, 'correlacao' => $correlacao]);

        return [
            'resultado'  => $resultado,
            'etapas'     => $etapas,
            'versao'     => $ident['versao'] ?? null,
            'sessoes'    => $sessoes,
            'correlacao' => $correlacao,
            'roteador'   => self::obter($id),
        ];
    }

    /**
     * Abre o roteador, entrega o driver a $fn e fecha — sempre com a trava do roteador. Unico
     * caminho para as telas de assinante usarem o BRAS. Falha de login conta para o bloqueio.
     *
     * @param callable(DriverVrp, Transporte):mixed $fn
     */
    public static function executarLeitura(int $id, callable $fn, ?Transporte $transporte = null, int $esperaTravaS = 10)
    {
        $r = self::linha($id);
        if (!(int) $r['ativo']) {
            throw new HwbErro('HWB-ROT-011');
        }
        self::exigirDesbloqueado($r);
        $trava = 'hwb_rot_' . $id;
        if (!Db::travar($trava, $esperaTravaS)) {
            throw new HwbErro('HWB-ROT-013', [], null, 409);
        }
        $t = null;
        try {
            $t = $transporte ?? self::transporte($r);
            try {
                $t->conectar();
            } catch (BrasFalha $f) {
                if ($f->tipo() === 'autenticacao') {
                    $falhas = (int) $r['falhas_auth'] + 1;
                    $bloquear = $falhas >= Config::int('bloqueio_auth_falhas');
                    Db::exec('UPDATE tab_hwb_roteador SET falhas_auth = ?' . ($bloquear ? ', bloqueado_ate = NOW() + INTERVAL ? MINUTE' : '') . ' WHERE id = ?',
                        $bloquear ? [$falhas, Config::int('bloqueio_auth_min'), $id] : [$falhas, $id]);
                }
                throw $f;
            }
            return $fn(new DriverVrp($t), $t);
        } finally {
            if ($t !== null) {
                $t->fechar();
            }
            Db::destravar($trava);
        }
    }

    /** Ultimos testes, agrupados por execucao. */
    public static function historicoTestes(int $id, int $limite = 10): array
    {
        self::linha($id);
        $linhas = Db::todos('SELECT correlacao, etapa, resultado, detalhe, duracao_ms, criado_por, criado_em
                               FROM tab_hwb_teste_conectividade WHERE roteador_id = ?
                           ORDER BY id DESC LIMIT 300', [$id]);
        $grupos = [];
        foreach ($linhas as $l) {
            $c = (string) $l['correlacao'];
            if (!isset($grupos[$c])) {
                if (count($grupos) >= $limite) {
                    break;
                }
                $grupos[$c] = ['correlacao' => $c, 'em' => $l['criado_em'], 'por' => $l['criado_por'], 'etapas' => []];
            }
            $l['titulo'] = self::TITULOS_ETAPA[$l['etapa']] ?? $l['etapa'];
            array_unshift($grupos[$c]['etapas'], $l);
        }
        foreach ($grupos as &$g) {
            $g['resultado'] = Diagnostico::pior(array_filter($g['etapas'], fn($e) => $e['resultado'] !== 'nao_testavel'));
        }
        unset($g);
        return array_values($grupos);
    }

    // ---------------------------------------------------------------- apoio

    public static function linha(int $id): array
    {
        $r = Db::um('SELECT * FROM tab_hwb_roteador WHERE id = ?', [$id]);
        if ($r === null) {
            throw new HwbErro('HWB-ROT-001', [], null, 404);
        }
        return $r;
    }

    /** Monta o transporte do cadastro (a senha sai do cofre so aqui). */
    public static function transporte(array $r): Transporte
    {
        if ($r['protocolo'] === 'simulado') {
            return new TransporteSimulado();
        }
        $senha = Cofre::ler('roteador', (int) $r['id']);
        if ($senha === null) {
            throw new HwbErro('HWB-ROT-003');
        }
        return new TransporteSsh($r['host'], (int) $r['porta'], $r['usuario'], $senha,
            (int) $r['timeout_conexao_s'], (int) $r['timeout_comando_s']);
    }

    /** null = TCP ok; string = motivo da falha. */
    public static function tcp(string $host, int $porta, int $timeout): ?string
    {
        $errno = 0;
        $errstr = '';
        $s = @fsockopen($host, $porta, $errno, $errstr, $timeout);
        if ($s === false) {
            return $errstr !== '' ? $errstr : 'sem resposta em ' . $timeout . ' s';
        }
        fclose($s);
        return null;
    }

    private static function validarNas($v): string
    {
        $v = trim((string) $v);
        if ($v === '') {
            throw new HwbErro('HWB-VAL-008', ['campo' => 'nas_ip'], 'Escolha o NAS do RADIUS.');
        }
        if (!Db::tabelaExiste('nas')) {
            // Banco sem as tabelas do MK-AUTH (testes): exige ao menos um IP valido.
            return Validar::ip($v);
        }
        if ((int) Db::valor('SELECT COUNT(*) FROM nas WHERE nasname = ?', [$v]) === 0) {
            throw new HwbErro('HWB-ROT-010', ['nas' => $v]);
        }
        return $v;
    }

    private static function etapaRadius(string $nasIp, ?int $sessoesBras): array
    {
        $t0 = microtime(true);
        if (!Db::tabelaExiste('radacct')) {
            return self::etapa('radius', 'nao_testavel', 'Tabela radacct não encontrada neste banco.', $t0);
        }
        $abertas = (int) Db::valor('SELECT COUNT(*) FROM radacct WHERE nasipaddress = ? AND acctstoptime IS NULL', [$nasIp]);
        $txt = number_format($abertas, 0, ',', '.') . ' sessão(ões) abertas no RADIUS para o NAS ' . $nasIp . '.';
        if ($sessoesBras !== null && $sessoesBras > 0 && $abertas === 0) {
            return self::etapa('radius', 'aviso', $txt . ' O BRAS tem assinantes online: o NAS escolhido provavelmente não é o IP com que este BRAS fala com o RADIUS.', $t0);
        }
        return self::etapa('radius', 'ok', $txt, $t0);
    }

    private static function exigirDesbloqueado(array $r): void
    {
        if ($r['bloqueado_ate'] !== null && strtotime($r['bloqueado_ate']) > time()) {
            throw new HwbErro('HWB-ROT-005', ['ate' => $r['bloqueado_ate']],
                Erros::mensagem('HWB-ROT-005') . ' Nova tentativa a partir de ' . date('H:i', strtotime($r['bloqueado_ate'])) . '.', 423);
        }
    }

    private static function paraTela(array $r): array
    {
        $id = (int) $r['id'];
        $r['id'] = $id;
        $r['porta'] = (int) $r['porta'];
        $r['ativo'] = (int) $r['ativo'];
        $r['versao'] = (int) $r['versao'];
        $r['timeout_conexao_s'] = (int) $r['timeout_conexao_s'];
        $r['timeout_comando_s'] = (int) $r['timeout_comando_s'];
        $r['sessoes_detectadas'] = $r['sessoes_detectadas'] === null ? null : (int) $r['sessoes_detectadas'];
        $r['tem_senha'] = Cofre::existe('roteador', $id);
        $r['bloqueado'] = $r['bloqueado_ate'] !== null && strtotime($r['bloqueado_ate']) > time();
        $r['modelo_rotulo'] = DriverVrp::MODELOS[$r['modelo']]['rotulo'] ?? $r['modelo'];
        return $r;
    }

    private static function paraAuditoria(array $r): array
    {
        return array_intersect_key($r, array_flip(['nome', 'modelo', 'protocolo', 'host', 'porta', 'usuario', 'nas_ip',
            'timeout_conexao_s', 'timeout_comando_s', 'observacao', 'ativo']));
    }

    private static function etapa(string $etapa, string $resultado, string $detalhe, float $inicio): array
    {
        return ['etapa' => $etapa, 'titulo' => self::TITULOS_ETAPA[$etapa] ?? $etapa, 'resultado' => $resultado,
                'detalhe' => $detalhe, 'ms' => max(0, (int) ((microtime(true) - $inicio) * 1000))];
    }

    private static function semErro(array $etapas): bool
    {
        foreach ($etapas as $e) {
            if ($e['resultado'] === 'erro') {
                return false;
            }
        }
        return true;
    }

    private static function textoFalha(BrasFalha $f): string
    {
        $tec = $f->detalhes()['tecnico'] ?? '';
        return $f->getMessage() . ($tec !== '' ? ' (' . Log::mascararTexto($tec) . ')' : '');
    }

    private static function traduzirDuplicado(PDOException $ex): void
    {
        if ((int) ($ex->errorInfo[1] ?? 0) === 1062) {
            $msg = (string) ($ex->errorInfo[2] ?? '');
            throw new HwbErro(str_contains($msg, 'uq_nas_ip') ? 'HWB-ROT-004' : 'HWB-ROT-002', [], null, 409);
        }
        throw $ex;
    }
}
