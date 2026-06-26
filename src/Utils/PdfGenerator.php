<?php
namespace App\Utils;

use TCPDF;

class PdfGenerator
{
    private $pdf;

    public function __construct()
    {
        $this->pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->pdf->SetCreator('SG Farmácia Gingongo');
        $this->pdf->SetAuthor('Sistema de Gestão RG');
        $this->pdf->SetTitle('Documento - Farmácia Gingongo');
        $this->pdf->SetSubject('Documento oficial');
        $this->pdf->SetKeywords('farmácia, gingongo, rh, gestão');
        
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        $this->pdf->SetMargins(15, 15, 15);
        $this->pdf->SetAutoPageBreak(true, 15);
    }

    public function reciboSalario($dados)
    {
        $this->pdf->AddPage();
        
        $html = '
        <style>
            .header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 15px; }
            .logo { font-size: 18px; font-weight: bold; color: #1a5c2e; }
            .info-empresa { text-align: right; font-size: 10px; }
            .dados-func { margin-bottom: 15px; font-size: 10px; }
            .table-valores { width: 100%; border-collapse: collapse; margin: 15px 0; }
            .table-valores th { background: #f0f0f0; border: 1px solid #ddd; padding: 6px; font-size: 9px; }
            .table-valores td { border: 1px solid #ddd; padding: 6px; font-size: 9px; }
            .total-row { font-weight: bold; background: #e9ecef !important; }
            .assinaturas { margin-top: 60px; display: flex; justify-content: space-between; }
            .assinatura-box { border-top: 1px solid #000; width: 40%; text-align: center; padding-top: 10px; font-size: 10px; }
        </style>
        
        <div class="header">
            <table>
                <tr>
                    <td width="60%">
                        <div class="logo">FARMÁCIA VALÓDIA RG</div>
                        <div style="font-size: 9px; color: #666;">Recibo de Vencimento</div>
                    </td>
                    <td width="40%" class="info-empresa">
                        <strong>Mês/Ano:</strong> ' . $dados['mes'] . '/' . $dados['ano'] . '<br>
                        <strong>Processado em:</strong> ' . date('d/m/Y', strtotime($dados['created_at'])) . '<br>
                        <strong>Nº:</strong> ' . str_pad($dados['id'], 6, '0', STR_PAD_LEFT) . '
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="dados-func">
            <table>
                <tr>
                    <td width="60%">
                        <strong>Funcionário:</strong> ' . $dados['nome_completo'] . '<br>
                        <strong>Cargo:</strong> ' . $dados['cargo'] . '<br>
                        <strong>Departamento:</strong> ' . $dados['departamento'] . '
                    </td>
                    <td width="40%">
                        <strong>NIF:</strong> ' . ($dados['nif'] ?? 'N/A') . '<br>
                        <strong>BI:</strong> ' . ($dados['bi'] ?? 'N/A') . '
                    </td>
                </tr>
            </table>
        </div>
        
        <table class="table-valores">
            <thead>
                <tr>
                    <th width="50%">Descrição</th>
                    <th width="25%" style="text-align: right;">Ganhos (+)</th>
                    <th width="25%" style="text-align: right;">Descontos (-)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Salário Base</td>
                    <td style="text-align: right;">' . number_format($dados['salario_base'], 2, ',', '.') . ' Kz</td>
                    <td></td>
                </tr>
                <tr>
                    <td>Subsídio Alimentação</td>
                    <td style="text-align: right;">' . number_format($dados['subsidio_alimentacao'], 2, ',', '.') . ' Kz</td>
                    <td></td>
                </tr>
                <tr>
                    <td>Subsídio Transporte</td>
                    <td style="text-align: right;">' . number_format($dados['subsidio_transporte'], 2, ',', '.') . ' Kz</td>
                    <td></td>
                </tr>';
        
        if ($dados['horas_extras'] > 0) {
            $html .= '
                <tr>
                    <td>Horas Extras</td>
                    <td style="text-align: right;">' . number_format($dados['horas_extras'], 2, ',', '.') . ' Kz</td>
                    <td></td>
                </tr>';
        }
        
        if ($dados['bonus'] > 0) {
            $html .= '
                <tr>
                    <td>Bônus / Prêmios</td>
                    <td style="text-align: right;">' . number_format($dados['bonus'], 2, ',', '.') . ' Kz</td>
                    <td></td>
                </tr>';
        }
        
        $html .= '
                <tr>
                    <td>Segurança Social (3%)</td>
                    <td></td>
                    <td style="text-align: right;">' . number_format($dados['desconto_inss_trabalhador'], 2, ',', '.') . ' Kz</td>
                </tr>
                <tr>
                    <td>IRT (Imposto de Renda)</td>
                    <td></td>
                    <td style="text-align: right;">' . number_format($dados['desconto_irt'], 2, ',', '.') . ' Kz</td>
                </tr>';
        
        if ($dados['desconto_faltas'] > 0) {
            $html .= '
                <tr>
                    <td>Faltas / Atrasos</td>
                    <td></td>
                    <td style="text-align: right;">' . number_format($dados['desconto_faltas'], 2, ',', '.') . ' Kz</td>
                </tr>';
        }
        
        $html .= '
                <tr style="background: #f8f9fa;">
                    <td><strong>Totais</strong></td>
                    <td style="text-align: right;"><strong>' . number_format($dados['total_proventos'], 2, ',', '.') . ' Kz</strong></td>
                    <td style="text-align: right;"><strong>' . number_format($dados['total_descontos'], 2, ',', '.') . ' Kz</strong></td>
                </tr>
                <tr class="total-row">
                    <td colspan="2" style="text-align: right;"><strong>LÍQUIDO A RECEBER</strong></td>
                    <td style="text-align: right; color: #1a5c2e;"><strong>' . number_format($dados['salario_liquido'], 2, ',', '.') . ' Kz</strong></td>
                </tr>
            </tbody>
        </table>
        
        <div style="font-size: 9px; color: #666; margin-top: 15px;">
            Este recibo foi processado eletronicamente. Declaro ter recebido a importância líquida discriminada neste recibo.
        </div>
        
        <div class="assinaturas">
            <div class="assinatura-box">
                Entidade Empregadora
            </div>
            <div class="assinatura-box">
                O Funcionário
            </div>
        </div>';
        
        $this->pdf->writeHTML($html, true, false, true, false, '');
        
        return $this->pdf->Output('recibo_salario_' . $dados['id'] . '.pdf', 'I');
    }

    public function mapaINSS($folhas, $mes, $ano)
    {
        $this->pdf->AddPage();
        
        $html = '
        <style>
            .header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 15px; }
            .logo { font-size: 18px; font-weight: bold; color: #1a5c2e; }
            .table-map { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 9px; }
            .table-map th { background: #1a5c2e; color: white; border: 1px solid #14532d; padding: 6px; }
            .table-map td { border: 1px solid #ddd; padding: 6px; }
            .total-row { background: #e9ecef; font-weight: bold; }
        </style>
        
        <div class="header">
            <table>
                <tr>
                    <td width="60%">
                        <div class="logo">FARMÁCIA VALÓDIA RG</div>
                        <div style="font-size: 9px; color: #666;">Mapa de Contribuição INSS</div>
                    </td>
                    <td width="40%" style="text-align: right; font-size: 10px;">
                        <strong>Período:</strong> ' . $mes . '/' . $ano . '<br>
                        <strong>Data:</strong> ' . date('d/m/Y') . '
                    </td>
                </tr>
            </table>
        </div>
        
        <table class="table-map">
            <thead>
                <tr>
                    <th>Nº</th>
                    <th>Nome do Funcionário</th>
                    <th>BI</th>
                    <th>Salário Base</th>
                    <th>INSS Trabalhador (3%)</th>
                    <th>INSS Empregador (8%)</th>
                    <th>Total INSS</th>
                </tr>
            </thead>
            <tbody>';
        
        $totalBase = 0;
        $totalINSS_trab = 0;
        $totalINSS_emp = 0;
        $totalINSS = 0;
        $n = 1;
        
        foreach ($folhas as $f) {
            $inss_trab = $f['desconto_inss_trabalhador'];
            $inss_emp = $f['salario_base'] * 0.08;
            $total = $inss_trab + $inss_emp;
            
            $totalBase += $f['salario_base'];
            $totalINSS_trab += $inss_trab;
            $totalINSS_emp += $inss_emp;
            $totalINSS += $total;
            
            $html .= '
                <tr>
                    <td>' . $n++ . '</td>
                    <td>' . $f['nome_completo'] . '</td>
                    <td>' . ($f['bi'] ?? 'N/A') . '</td>
                    <td style="text-align: right;">' . number_format($f['salario_base'], 2, ',', '.') . '</td>
                    <td style="text-align: right;">' . number_format($inss_trab, 2, ',', '.') . '</td>
                    <td style="text-align: right;">' . number_format($inss_emp, 2, ',', '.') . '</td>
                    <td style="text-align: right;">' . number_format($total, 2, ',', '.') . '</td>
                </tr>';
        }
        
        $html .= '
                <tr class="total-row">
                    <td colspan="3">TOTAIS</td>
                    <td style="text-align: right;">' . number_format($totalBase, 2, ',', '.') . '</td>
                    <td style="text-align: right;">' . number_format($totalINSS_trab, 2, ',', '.') . '</td>
                    <td style="text-align: right;">' . number_format($totalINSS_emp, 2, ',', '.') . '</td>
                    <td style="text-align: right;">' . number_format($totalINSS, 2, ',', '.') . '</td>
                </tr>
            </tbody>
        </table>
        
        <div style="font-size: 9px; color: #666; margin-top: 20px;">
            <strong>Nota:</strong> O INSS do trabalhador (3%) é descontado do salário. O INSS do empregador (8%) é custo adicional para a empresa.
            <br>Base de cálculo: Lei nº 15/23 de 12 de outubro (Código da Segurança Social).
        </div>';
        
        $this->pdf->writeHTML($html, true, false, true, false, '');
        
        return $this->pdf->Output('mapa_inss_' . $mes . '_' . $ano . '.pdf', 'I');
    }

    public function mapaIRT($folhas, $mes, $ano)
    {
        $this->pdf->AddPage();
        
        $html = '
        <style>
            .header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 15px; }
            .logo { font-size: 18px; font-weight: bold; color: #1a5c2e; }
            .table-map { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 9px; }
            .table-map th { background: #1a5c2e; color: white; border: 1px solid #14532d; padding: 6px; }
            .table-map td { border: 1px solid #ddd; padding: 6px; }
            .total-row { background: #e9ecef; font-weight: bold; }
        </style>
        
        <div class="header">
            <table>
                <tr>
                    <td width="60%">
                        <div class="logo">FARMÁCIA VALÓDIA RG</div>
                        <div style="font-size: 9px; color: #666;">Mapa de Retenção na Fonte - IRT</div>
                    </td>
                    <td width="40%" style="text-align: right; font-size: 10px;">
                        <strong>Período:</strong> ' . $mes . '/' . $ano . '<br>
                        <strong>Data:</strong> ' . date('d/m/Y') . '
                    </td>
                </tr>
            </table>
        </div>
        
        <table class="table-map">
            <thead>
                <tr>
                    <th>Nº</th>
                    <th>Nome do Funcionário</th>
                    <th>NIF</th>
                    <th>Salário Bruto</th>
                    <th>INSS</th>
                    <th>Matéria Coletável</th>
                    <th>IRT Retido</th>
                </tr>
            </thead>
            <tbody>';
        
        $totalBruto = 0;
        $totalINSS = 0;
        $totalMC = 0;
        $totalIRT = 0;
        $n = 1;
        
        foreach ($folhas as $f) {
            $bruto = $f['salario_base'] + $f['horas_extras'] + $f['bonus'];
            $inss = $f['desconto_inss_trabalhador'];
            $mc = $bruto - $inss;
            $irt = $f['desconto_irt'];
            
            $totalBruto += $bruto;
            $totalINSS += $inss;
            $totalMC += $mc;
            $totalIRT += $irt;
            
            $html .= '
                <tr>
                    <td>' . $n++ . '</td>
                    <td>' . $f['nome_completo'] . '</td>
                    <td>' . ($f['nif'] ?? 'N/A') . '</td>
                    <td style="text-align: right;">' . number_format($bruto, 2, ',', '.') . '</td>
                    <td style="text-align: right;">' . number_format($inss, 2, ',', '.') . '</td>
                    <td style="text-align: right;">' . number_format($mc, 2, ',', '.') . '</td>
                    <td style="text-align: right;">' . number_format($irt, 2, ',', '.') . '</td>
                </tr>';
        }
        
        $html .= '
                <tr class="total-row">
                    <td colspan="3">TOTAIS</td>
                    <td style="text-align: right;">' . number_format($totalBruto, 2, ',', '.') . '</td>
                    <td style="text-align: right;">' . number_format($totalINSS, 2, ',', '.') . '</td>
                    <td style="text-align: right;">' . number_format($totalMC, 2, ',', '.') . '</td>
                    <td style="text-align: right;">' . number_format($totalIRT, 2, ',', '.') . '</td>
                </tr>
            </tbody>
        </table>
        
        <div style="font-size: 9px; color: #666; margin-top: 20px;">
            <strong>Nota:</strong> O IRT é retido na fonte e deve ser entregue ao Ministério das Finanças até o dia 20 do mês seguinte.
            <br>Base de cálculo: Lei nº 15/23 de 12 de outubro (Lei do IRT).
            <br>Matéria Coletável = Salário Bruto - INSS Trabalhador.
        </div>';
        
        $this->pdf->writeHTML($html, true, false, true, false, '');
        
        return $this->pdf->Output('mapa_irt_' . $mes . '_' . $ano . '.pdf', 'I');
    }

    public function folhaPagamento($folhas, $mes, $ano, $totais)
    {
        $this->pdf->AddPage();
        
        $html = '
        <style>
            .header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 15px; }
            .logo { font-size: 18px; font-weight: bold; color: #1a5c2e; }
            .table-folha { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 8px; }
            .table-folha th { background: #1a5c2e; color: white; border: 1px solid #14532d; padding: 4px; }
            .table-folha td { border: 1px solid #ddd; padding: 4px; }
            .total-row { background: #e9ecef; font-weight: bold; }
            .stats { display: flex; gap: 20px; margin: 15px 0; }
            .stat-box { border: 1px solid #ddd; padding: 10px; flex: 1; text-align: center; }
            .stat-label { font-size: 9px; color: #666; }
            .stat-value { font-size: 12px; font-weight: bold; }
        </style>
        
        <div class="header">
            <table>
                <tr>
                    <td width="60%">
                        <div class="logo">FARMÁCIA VALÓDIA RG</div>
                        <div style="font-size: 9px; color: #666;">Folha de Pagamento Mensal</div>
                    </td>
                    <td width="40%" style="text-align: right; font-size: 10px;">
                        <strong>Mês/Ano:</strong> ' . $mes . '/' . $ano . '<br>
                        <strong>Funcionários:</strong> ' . count($folhas) . '<br>
                        <strong>Data:</strong> ' . date('d/m/Y') . '
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="stats">
            <div class="stat-box">
                <div class="stat-label">Total Proventos</div>
                <div class="stat-value" style="color: #1a5c2e;">' . number_format($totais['proventos'], 2, ',', '.') . ' Kz</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Total INSS</div>
                <div class="stat-value" style="color: #dc3545;">' . number_format($totais['inss'], 2, ',', '.') . ' Kz</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Total IRT</div>
                <div class="stat-value" style="color: #ffc107;">' . number_format($totais['irt'], 2, ',', '.') . ' Kz</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Total Descontos</div>
                <div class="stat-value" style="color: #dc3545;">' . number_format($totais['descontos'], 2, ',', '.') . ' Kz</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Líquido a Pagar</div>
                <div class="stat-value" style="color: #198754;">' . number_format($totais['liquido'], 2, ',', '.') . ' Kz</div>
            </div>
        </div>
        
        <table class="table-folha">
            <thead>
                <tr>
                    <th>Nº</th>
                    <th>Funcionário</th>
                    <th>Cargo</th>
                    <th>Salário Base</th>
                    <th>H.Extras</th>
                    <th>Subsídios</th>
                    <th>INSS</th>
                    <th>IRT</th>
                    <th>Líquido</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>';
        
        $n = 1;
        foreach ($folhas as $f) {
            $status = $f['status'] === 'pago' ? 'Pago' : 'Pendente';
            $html .= '
                <tr>
                    <td>' . $n++ . '</td>
                    <td>' . $f['nome_completo'] . '</td>
                    <td>' . $f['cargo'] . '</td>
                    <td style="text-align: right;">' . number_format($f['salario_base'], 2, ',', '.') . '</td>
                    <td style="text-align: right;">' . number_format($f['horas_extras'], 2, ',', '.') . '</td>
                    <td style="text-align: right;">' . number_format($f['subsidio_alimentacao'] + $f['subsidio_transporte'], 2, ',', '.') . '</td>
                    <td style="text-align: right;">' . number_format($f['desconto_inss_trabalhador'], 2, ',', '.') . '</td>
                    <td style="text-align: right;">' . number_format($f['desconto_irt'], 2, ',', '.') . '</td>
                    <td style="text-align: right;">' . number_format($f['salario_liquido'], 2, ',', '.') . '</td>
                    <td>' . $status . '</td>
                </tr>';
        }
        
        $html .= '
                <tr class="total-row">
                    <td colspan="3">TOTAIS</td>
                    <td style="text-align: right;">' . number_format(array_sum(array_column($folhas, 'salario_base')), 2, ',', '.') . '</td>
                    <td style="text-align: right;">' . number_format(array_sum(array_column($folhas, 'horas_extras')), 2, ',', '.') . '</td>
                    <td style="text-align: right;">' . number_format(array_sum(array_column($folhas, 'subsidio_alimentacao')) + array_sum(array_column($folhas, 'subsidio_transporte')), 2, ',', '.') . '</td>
                    <td style="text-align: right;">' . number_format(array_sum(array_column($folhas, 'desconto_inss_trabalhador')), 2, ',', '.') . '</td>
                    <td style="text-align: right;">' . number_format(array_sum(array_column($folhas, 'desconto_irt')), 2, ',', '.') . '</td>
                    <td style="text-align: right;">' . number_format(array_sum(array_column($folhas, 'salario_liquido')), 2, ',', '.') . '</td>
                    <td></td>
                </tr>
            </tbody>
        </table>
        
        <div style="font-size: 9px; color: #666; margin-top: 20px;">
            <strong>Legislação Aplicável:</strong>
            <br>- INSS: Lei nº 15/23 de 12 de outubro (Código da Segurança Social)
            <br>- IRT: Lei nº 15/23 de 12 de outubro (Lei do Imposto sobre o Rendimento das pessoas Singulares)
            <br>- Salário Mínimo: 70.000 Kz (referência)
        </div>';
        
        $this->pdf->writeHTML($html, true, false, true, false, '');
        
        return $this->pdf->Output('folha_pagamento_' . $mes . '_' . $ano . '.pdf', 'I');
    }

    public function salvarPDF($dados, $nomeArquivo, $diretorio = 'uploads/pdfs/')
    {
        if (!is_dir($diretorio)) {
            mkdir($diretorio, 0755, true);
        }
        
        $caminho = $diretorio . $nomeArquivo;
        $this->pdf->Output($caminho, 'F');
        
        return $caminho;
    }
}
