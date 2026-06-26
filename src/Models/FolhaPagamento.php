<?php
namespace App\Models;

use App\Database\Database;
use App\Utils\TaxasAngola;
use PDO;

class FolhaPagamento
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function processarFolha(int $funcionarioId, int $mes, int $ano, array $dadosExtras = [], ?int $userId = null): int
    {
        $conn = $this->db->getConnection();

        $stmt = $conn->prepare("SELECT id, salario_atual, departamento_id, cargo_id FROM funcionarios WHERE id = :id");
        $stmt->execute([':id' => $funcionarioId]);
        $func = $stmt->fetch();

        if (!$func) {
            throw new \Exception("Funcionário não encontrado.");
        }

        $salarioBase = (float) $func['salario_atual'];

        $horasExtrasQtd = (float) ($dadosExtras['horas_extras'] ?? 0);
        $valorHorasExtras = TaxasAngola::calcularHorasExtras($salarioBase, $horasExtrasQtd);

        $subAlimentacao = (float) ($dadosExtras['subsidio_alimentacao'] ?? 30000.00);
        $subTransporte = (float) ($dadosExtras['subsidio_transporte'] ?? 30000.00);
        $bonus = (float) ($dadosExtras['bonus'] ?? 0.00);

        $totalProventos = $salarioBase + $valorHorasExtras + $subAlimentacao + $subTransporte + $bonus;

        $baseIncidenciaINSS = $salarioBase + $valorHorasExtras;
        $inss = TaxasAngola::calcularINSS($baseIncidenciaINSS);

        $materiaColetavel = ($salarioBase + $valorHorasExtras + $bonus) - $inss;
        $irt = TaxasAngola::calcularIRT($materiaColetavel);

        $faltas = (float) ($dadosExtras['desconto_faltas'] ?? 0.00);

        $totalDescontos = $inss + $irt + $faltas;
        $salarioLiquido = $totalProventos - $totalDescontos;

        $check = $conn->prepare("SELECT id FROM folha_pagamento WHERE funcionario_id = :fid AND mes = :mes AND ano = :ano");
        $check->execute([':fid' => $funcionarioId, ':mes' => $mes, ':ano' => $ano]);
        $existing = $check->fetch();

        $dadosSave = [
            'salario_base' => $salarioBase,
            'horas_extras' => $valorHorasExtras,
            'subsidio_alimentacao' => $subAlimentacao,
            'subsidio_transporte' => $subTransporte,
            'bonus' => $bonus,
            'desconto_inss_trabalhador' => $inss,
            'desconto_irt' => $irt,
            'desconto_faltas' => $faltas,
            'total_proventos' => $totalProventos,
            'total_descontos' => $totalDescontos,
            'salario_liquido' => $salarioLiquido,
            'status' => 'processado',
            'processado_por' => $userId ?? ($_SESSION['user_id'] ?? 6)
        ];

        if ($existing) {
            $this->db->update('folha_pagamento', $dadosSave, ['id' => $existing['id']]);
            return (int) $existing['id'];
        } else {
            $dadosSave['funcionario_id'] = $funcionarioId;
            $dadosSave['mes'] = $mes;
            $dadosSave['ano'] = $ano;
            return (int) $this->db->insert('folha_pagamento', $dadosSave);
        }
    }

    public function listarPorPeriodo(int $mes, int $ano): array
    {
        $sql = "SELECT fp.*, f.nome_completo, d.nome as departamento
                FROM folha_pagamento fp
                JOIN funcionarios f ON fp.funcionario_id = f.id
                JOIN departamentos d ON f.departamento_id = d.id
                WHERE fp.mes = :mes AND fp.ano = :ano
                ORDER BY f.nome_completo";
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute([':mes' => $mes, ':ano' => $ano]);
        return $stmt->fetchAll();
    }

    public function obterPorId(int $id): ?array
    {
        $sql = "SELECT fp.*, f.nome_completo, f.nif, f.bi, c.nome as cargo, d.nome as departamento
                FROM folha_pagamento fp
                JOIN funcionarios f ON fp.funcionario_id = f.id
                JOIN cargos c ON f.cargo_id = c.id
                JOIN departamentos d ON f.departamento_id = d.id
                WHERE fp.id = :id";
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }
}
