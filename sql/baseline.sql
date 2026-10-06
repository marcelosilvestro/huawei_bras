-- huawei_bras :: schema completo, idempotente.
--
-- Roda inteiro a cada instalacao/atualizacao (lib/Core/Schema.php): o que ja existe fica,
-- o que falta e criado. Mudanca de coluna futura entra aqui como ALTER condicional, nunca
-- como arquivo incremental solto.
--
-- Regras de escrita deste arquivo (o separador de comandos e simples):
--   * todo comando termina com ";" no FIM da linha
--   * comentario so em linha propria comecando com "--", nunca depois do ";"
--
-- Tabelas nativas do MK-AUTH (sis_acesso, sis_cliente, nas, radacct) sao somente leitura e nao aparecem aqui.

CREATE TABLE IF NOT EXISTS `tab_hwb_migration` (
  `migration`   VARCHAR(100) NOT NULL,
  `checksum`    CHAR(64)     NOT NULL,
  `versao`      VARCHAR(20)  NOT NULL DEFAULT '0',
  `executed_at` DATETIME     NOT NULL,
  `executed_by` VARCHAR(60)  NULL,
  `duracao_ms`  INT UNSIGNED NULL,
  `resultado`   VARCHAR(10)  NOT NULL DEFAULT 'ok',
  `erro`        TEXT         NULL,
  PRIMARY KEY (`migration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Configuracao geral chave/valor. Os padroes vivem em lib/Core/Config.php: aqui so fica o
-- que o administrador alterou (e os valores internos, como a digital do cofre).
CREATE TABLE IF NOT EXISTS `tab_hwb_config` (
  `chave`        VARCHAR(64) NOT NULL,
  `valor`        TEXT        NULL,
  `alterado_por` VARCHAR(60) NULL,
  `alterado_em`  DATETIME    NULL,
  PRIMARY KEY (`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Papeis por login do MK-AUTH (sis_acesso.login). Um login tem quantos papeis precisar.
CREATE TABLE IF NOT EXISTS `tab_hwb_permissao` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `login`      VARCHAR(60)  NOT NULL,
  `papel`      VARCHAR(40)  NOT NULL,
  `criado_por` VARCHAR(60)  NULL,
  `criado_em`  DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_login_papel` (`login`, `papel`),
  KEY `ix_papel` (`papel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tab_hwb_auditoria` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `criado_em`   DATETIME        NOT NULL,
  `usuario`     VARCHAR(60)     NOT NULL,
  `ip`          VARCHAR(45)     NULL,
  `acao`        VARCHAR(60)     NOT NULL,
  `entidade`    VARCHAR(40)     NOT NULL,
  `entidade_id` BIGINT UNSIGNED NULL,
  `antes`       MEDIUMTEXT      NULL,
  `depois`      MEDIUMTEXT      NULL,
  `correlacao`  VARCHAR(64)     NULL,
  `request_id`  VARCHAR(32)     NULL,
  PRIMARY KEY (`id`),
  KEY `ix_criado` (`criado_em`),
  KEY `ix_entidade` (`entidade`, `entidade_id`),
  KEY `ix_usuario` (`usuario`),
  KEY `ix_correlacao` (`correlacao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cofre: senhas cifradas (libsodium XChaCha20-Poly1305), separadas dos registros operacionais.
-- A chave mora em /opt/mk-auth/conf/huawei_bras.key, nunca no banco. digital_chave diz com qual
-- chave cada linha foi cifrada: se a chave do servidor mudar, o diagnostico aponta quais
-- credenciais precisam ser recadastradas.
CREATE TABLE IF NOT EXISTS `tab_hwb_credencial` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tipo`          VARCHAR(20)  NOT NULL,
  `dono_id`       INT UNSIGNED NOT NULL,
  `cifrado`       TEXT         NOT NULL,
  `digital_chave` CHAR(16)     NOT NULL,
  `alterado_por`  VARCHAR(60)  NULL,
  `alterado_em`   DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tipo_dono` (`tipo`, `dono_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Roteadores (BRAS). A senha SSH fica no cofre (tipo 'roteador').
-- host/porta = gerencia SSH; nas_ip = o IP com que o BRAS se apresenta ao RADIUS, escolhido da
-- tabela nas do MK-AUTH e usado para filtrar radacct.nasipaddress. Os dois podem ser diferentes.
-- versao/identificador sao DETECTADOS no teste, nunca digitados.
CREATE TABLE IF NOT EXISTS `tab_hwb_roteador` (
  `id`                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome`                    VARCHAR(80)  NOT NULL,
  `modelo`                  VARCHAR(40)  NOT NULL DEFAULT 'NE8000',
  `protocolo`               ENUM('ssh','simulado') NOT NULL DEFAULT 'ssh',
  `host`                    VARCHAR(253) NOT NULL,
  `porta`                   SMALLINT UNSIGNED NOT NULL DEFAULT 22,
  `usuario`                 VARCHAR(60)  NOT NULL,
  `nas_ip`                  VARCHAR(45)  NOT NULL,
  `timeout_conexao_s`       SMALLINT UNSIGNED NOT NULL DEFAULT 10,
  `timeout_comando_s`       SMALLINT UNSIGNED NOT NULL DEFAULT 15,
  `versao_detectada`        VARCHAR(120) NULL,
  `identificador_detectado` VARCHAR(120) NULL,
  `modelo_detectado`        VARCHAR(80)  NULL,
  `sessoes_detectadas`      INT UNSIGNED NULL,
  `ultimo_teste_em`         DATETIME     NULL,
  `ultimo_teste_resultado`  ENUM('ok','aviso','erro') NULL,
  `ultimo_teste_detalhe`    VARCHAR(500) NULL,
  `falhas_auth`             TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `bloqueado_ate`           DATETIME     NULL,
  `observacao`              VARCHAR(500) NULL,
  `ativo`                   TINYINT(1)   NOT NULL DEFAULT 1,
  `versao`                  INT UNSIGNED NOT NULL DEFAULT 1,
  `criado_por`              VARCHAR(60)  NULL,
  `criado_em`               DATETIME     NOT NULL,
  `alterado_por`            VARCHAR(60)  NULL,
  `alterado_em`             DATETIME     NULL,
  `desativado_em`           DATETIME     NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_nome` (`nome`),
  UNIQUE KEY `uq_nas_ip` (`nas_ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Historico dos testes de conexao: uma linha por etapa (tcp, ssh, login, versao, sessoes).
-- correlacao agrupa as etapas de um mesmo teste.
CREATE TABLE IF NOT EXISTS `tab_hwb_teste_conectividade` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `roteador_id` INT UNSIGNED NOT NULL,
  `etapa`       VARCHAR(40)  NOT NULL,
  `resultado`   ENUM('ok','aviso','erro','nao_testavel') NOT NULL,
  `detalhe`     VARCHAR(500) NULL,
  `duracao_ms`  INT UNSIGNED NULL,
  `correlacao`  VARCHAR(64)  NULL,
  `criado_por`  VARCHAR(60)  NULL,
  `criado_em`   DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_roteador` (`roteador_id`, `criado_em`),
  KEY `ix_correlacao` (`correlacao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
