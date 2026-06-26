<?php
namespace App\Models;

use App\Database\Database;

/**
 * Model para RG (Registro Geral)
 * Gerencia dados de RG de funcionários
 */
class RG
{
    private $db;
    private $table = 'rgs';

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->ensureTable();
    }

    /**
     * Garante que a tabela existe
     */
    private function ensureTable()
    {
        try {
            $this->db->query("
                CREATE TABLE IF NOT EXISTS {$this->table} (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    funcionario_id INTEGER NOT NULL,
                    numero_rg TEXT NOT NULL UNIQUE,
                    orgao_expedidor TEXT,
                    uf_expedidor TEXT,
                    data_expedicao DATE,
                    data_validade DATE,
                    mae_nome TEXT,
                    data_nascimento DATE,
                    naturalidade TEXT,
                    filiacao TEXT,
                    status TEXT DEFAULT 'ativo',
                    observacoes TEXT,
                    arquivo_caminho TEXT,
                    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY(funcionario_id) REFERENCES funcionarios(id)
                )
            ");
        } catch (\Exception $e) {
            error_log("Erro ao criar tabela RG: " . $e->getMessage());
        }
    }

    /**
     * Criar novo RG
     */
    public function create($data)
    {
        try {
            $defaultData = [
                'funcionario_id' => $data['funcionario_id'] ?? null,
                'numero_rg' => $data['numero_rg'] ?? '',
                'orgao_expedidor' => $data['orgao_expedidor'] ?? '',
                'uf_expedidor' => $data['uf_expedidor'] ?? '',
                'data_expedicao' => $data['data_expedicao'] ?? null,
                'data_validade' => $data['data_validade'] ?? null,
                'mae_nome' => $data['mae_nome'] ?? '',
                'data_nascimento' => $data['data_nascimento'] ?? null,
                'naturalidade' => $data['naturalidade'] ?? '',
                'filiacao' => $data['filiacao'] ?? '',
                'status' => $data['status'] ?? 'ativo',
                'observacoes' => $data['observacoes'] ?? '',
                'arquivo_caminho' => $data['arquivo_caminho'] ?? null
            ];

            // Validações
            if (empty($defaultData['funcionario_id'])) {
                return ['success' => false, 'message' => 'Funcionário é obrigatório'];
            }

            if (empty($defaultData['numero_rg'])) {
                return ['success' => false, 'message' => 'Número RG é obrigatório'];
            }

            // Verificar se RG já existe
            $exists = $this->db->select($this->table, ['numero_rg' => $defaultData['numero_rg']], true);
            if ($exists) {
                return ['success' => false, 'message' => 'Este RG já está cadastrado'];
            }

            $id = $this->db->insert($this->table, $defaultData);
            
            return [
                'success' => true,
                'message' => 'RG cadastrado com sucesso',
                'id' => $id
            ];

        } catch (\Exception $e) {
            error_log("Erro ao criar RG: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao cadastrar RG'];
        }
    }

    /**
     * Obter RG por ID
     */
    public function getById($id)
    {
        try {
            return $this->db->select($this->table, ['id' => $id], true);
        } catch (\Exception $e) {
            error_log("Erro ao buscar RG: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obter RG por funcionário
     */
    public function getByFuncionario($funcionario_id)
    {
        try {
            return $this->db->select($this->table, ['funcionario_id' => $funcionario_id], false);
        } catch (\Exception $e) {
            error_log("Erro ao buscar RG do funcionário: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Atualizar RG
     */
    public function update($id, $data)
    {
        try {
            // Não permitir alterar numero_rg
            unset($data['numero_rg']);
            unset($data['id']);

            $data['atualizado_em'] = date('Y-m-d H:i:s');

            $success = $this->db->update($this->table, $data, ['id' => $id]);

            if ($success) {
                return ['success' => true, 'message' => 'RG atualizado com sucesso'];
            }

            return ['success' => false, 'message' => 'Erro ao atualizar RG'];

        } catch (\Exception $e) {
            error_log("Erro ao atualizar RG: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao atualizar RG'];
        }
    }

    /**
     * Deletar RG
     */
    public function delete($id)
    {
        try {
            // Soft delete - apenas marcar como inativo
            $success = $this->db->update($this->table, ['status' => 'inativo'], ['id' => $id]);

            if ($success) {
                return ['success' => true, 'message' => 'RG removido com sucesso'];
            }

            return ['success' => false, 'message' => 'Erro ao remover RG'];

        } catch (\Exception $e) {
            error_log("Erro ao deletar RG: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao remover RG'];
        }
    }

    /**
     * Listar todos os RGs
     */
    public function getAll($limit = 100, $offset = 0)
    {
        try {
            return $this->db->select($this->table, [], false, $limit, $offset);
        } catch (\Exception $e) {
            error_log("Erro ao listar RGs: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Buscar RG
     */
    public function search($query)
    {
        try {
            // Busca por número de RG ou nome do funcionário
            $results = $this->db->select($this->table, [], false);
            
            $filtered = array_filter($results, function($rg) use ($query) {
                return strpos(strtolower($rg['numero_rg']), strtolower($query)) !== false ||
                       strpos(strtolower($rg['mae_nome']), strtolower($query)) !== false;
            });

            return array_values($filtered);

        } catch (\Exception $e) {
            error_log("Erro ao buscar RGs: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Contar RGs
     */
    public function count()
    {
        try {
            $all = $this->db->select($this->table, [], false);
            return count($all);
        } catch (\Exception $e) {
            error_log("Erro ao contar RGs: " . $e->getMessage());
            return 0;
        }
    }
}
