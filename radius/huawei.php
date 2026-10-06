<?php

class radius {

	############################################################
	### CONTROLE DE PLANOS - EXCLUSIVO HUAWEI ##################
	############################################################

	// INSERE UM REGISTRO EM RADGROUPREPLY
	private static function insert_radgroupreply(string $groupname, string $attribute, string $value, string $op = ':='): void {

		$sql = 'INSERT INTO radgroupreply (groupname, attribute, value, op) VALUES (:groupname, :attribute, :value, :op)';
		
		$params = [
			':groupname' => $groupname, 
			':attribute' => $attribute, 
			':value'     => $value, 
			':op'        => $op
		];

		$stmt = dbase::prepare($sql);
		foreach ($params as $param => $val) {
			$stmt->bindValue($param, $val);
		}
		$stmt->execute();

	}

	// CONFIGURA AS RESPOSTAS RADIUS (RADGROUPREPLY) PARA UM PLANO HUAWEI
	public static function setup_plano($plano): void {

		$dados_plano = new plano($plano);

		// Nome do plano (Groupname no Banco)
		$nome = (string) $dados_plano->nome;

		// Velocidades: Convertendo de Kbps para bps (Huawei exige bps)
		$velup_bps   = (int)$dados_plano->velup * 1000;
		$veldown_bps = (int)$dados_plano->veldown * 1000;

		// Pools (IPv4 e IPv6 conforme sua estrutura)
		$pool  = !empty($dados_plano->pool) ? (string) $dados_plano->pool : 'nenhum';
		$ipv6b = !empty($dados_plano->ipv6b) ? (string) $dados_plano->ipv6b : 'nenhum';

		// 1. Remove TODOS os atributos existentes para este grupo
		$delete_stmt = dbase::prepare('DELETE FROM radgroupreply WHERE groupname = :groupname');
		$delete_stmt->bindValue(':groupname', $nome);
		$delete_stmt->execute();

		// 2. Insere os atributos de velocidade Huawei (bps)
		if ($velup_bps > 0) {
			self::insert_radgroupreply($nome, 'Huawei-Input-Peak-Rate', (string)$velup_bps);
		}

		if ($veldown_bps > 0) {
			self::insert_radgroupreply($nome, 'Huawei-Output-Peak-Rate', (string)$veldown_bps);
		}

		// 3. Insere o Framed-Pool (IPv4) se existir
		if ($pool !== 'nenhum' && !empty($pool)) {
			self::insert_radgroupreply($nome, 'Framed-Pool', $pool);
		}

		// 4. Insere o Framed-IPv6-Pool (usando o valor de $ipv6b)
		if ($ipv6b !== 'nenhum' && !empty($ipv6b)) {
			self::insert_radgroupreply($nome, 'Framed-IPv6-Pool', $ipv6b);
		}

	}

}