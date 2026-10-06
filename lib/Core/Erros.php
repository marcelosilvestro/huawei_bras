<?php
/**
 * huawei_bras :: catalogo de codigos de erro estaveis.
 *
 * O codigo e contrato: interface, logs e testes dependem dele, nunca do texto.
 * A mensagem e a que o USUARIO ve — sem SQL, sem nome de tabela, sem senha, sem stack trace.
 */
final class Erros
{
    public const MENSAGENS = [
        // Sessao e acesso
        'HWB-AUTH-001' => 'Sessão expirada.',
        'HWB-AUTH-002' => 'Você não tem permissão para esta operação.',
        'HWB-AUTH-003' => 'Requisição inválida (token de segurança).',
        'HWB-AUTH-004' => 'O administrador do addon já foi definido.',
        'HWB-AUTH-005' => 'O addon precisa ter ao menos um administrador.',
        'HWB-AUTH-006' => 'Login não encontrado entre os usuários do MK-AUTH.',

        // Cofre de credenciais
        'HWB-COF-001' => 'A chave do cofre de credenciais não foi encontrada no servidor. Rode o instalador.',
        'HWB-COF-002' => 'Esta senha foi gravada com outra chave do cofre e precisa ser cadastrada de novo.',
        'HWB-COF-003' => 'Não foi possível ler a senha guardada: o registro está corrompido. Cadastre de novo.',
        'HWB-COF-004' => 'A chave do cofre é inválida.',

        // Validacao de entrada
        'HWB-VAL-001' => 'Endereço (IP ou hostname) inválido.',
        'HWB-VAL-002' => 'Porta inválida (1 a 65535).',
        'HWB-VAL-005' => 'Login inválido.',
        'HWB-VAL-006' => 'Horário inválido (use HH:MM).',
        'HWB-VAL-007' => 'Valor numérico fora da faixa permitida.',
        'HWB-VAL-008' => 'Campo obrigatório não preenchido.',
        'HWB-VAL-009' => 'Texto contém caracteres não permitidos.',
        'HWB-VAL-010' => 'Login de assinante inválido.',
        'HWB-VAL-011' => 'MAC inválido: são necessários 12 dígitos hexadecimais.',

        // Roteador (BRAS)
        'HWB-ROT-001' => 'Roteador não encontrado.',
        'HWB-ROT-002' => 'Já existe um roteador com este nome.',
        'HWB-ROT-003' => 'A senha de acesso ao roteador não foi cadastrada.',
        'HWB-ROT-004' => 'Já existe um roteador vinculado a este NAS.',
        'HWB-ROT-005' => 'Roteador bloqueado temporariamente após falhas de login seguidas.',
        'HWB-ROT-006' => 'Não foi possível conectar ao roteador (endereço ou porta inacessível).',
        'HWB-ROT-007' => 'O roteador recusou o usuário ou a senha.',
        'HWB-ROT-008' => 'O roteador não respondeu dentro do tempo limite.',
        'HWB-ROT-009' => 'A resposta do roteador veio num formato que o addon não reconhece.',
        'HWB-ROT-010' => 'O NAS escolhido não existe na lista de concentradores do MK-AUTH.',
        'HWB-ROT-011' => 'Este roteador está desativado.',
        'HWB-ROT-012' => 'Não há driver para este modelo.',
        'HWB-ROT-013' => 'Já existe uma operação em andamento neste roteador. Aguarde e tente de novo.',
        'HWB-ROT-014' => 'A conexão com o roteador caiu no meio da operação.',
        'HWB-ROT-015' => 'O roteador respondeu com erro ao comando.',
        'HWB-ROT-016' => 'Para remover, digite o nome exato do roteador.',
        'HWB-ROT-017' => 'A biblioteca SSH do addon não foi encontrada. Rode o instalador.',

        // Assinante
        'HWB-ASS-001' => 'Assinante não encontrado.',
        'HWB-ASS-002' => 'O assinante não está online neste roteador.',
        'HWB-ASS-003' => 'Nenhum roteador ativo cadastrado atende ao NAS da sessão deste assinante.',
        'HWB-ASS-004' => 'O roteador não confirmou a desconexão do assinante.',

        // Configuracao
        'HWB-CFG-001' => 'Configuração desconhecida.',
        'HWB-CFG-002' => 'Esta configuração não pode ser alterada pela interface.',

        // Concorrencia
        'HWB-CONC-001' => 'Este registro foi alterado por outro usuário. Recarregue antes de salvar.',

        // Sistema
        'HWB-SYS-001' => 'Não foi possível concluir a operação.',
        'HWB-SYS-002' => 'Dados inválidos na requisição.',
        'HWB-SYS-003' => 'Operação desconhecida.',
        'HWB-SYS-004' => 'Método HTTP não permitido para esta operação.',
        'HWB-SYS-005' => 'Erro de configuração: banco de dados não configurado. Rode o instalador.',
        'HWB-SYS-006' => 'O banco do addon está incompleto ou desatualizado. Rode o instalador.',
    ];

    public static function mensagem(string $code): string
    {
        return self::MENSAGENS[$code] ?? self::MENSAGENS['HWB-SYS-001'];
    }

    public static function existe(string $code): bool
    {
        return isset(self::MENSAGENS[$code]);
    }
}
