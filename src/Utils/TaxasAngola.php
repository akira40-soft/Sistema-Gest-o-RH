<?php
namespace App\Utils;

class TaxasAngola
{
    private static array $tabelaIRT = [
        ['min' => 0, 'max' => 100000, 'taxa' => 0.00, 'parcela_fixa' => 0],
        ['min' => 100001, 'max' => 150000, 'taxa' => 0.13, 'parcela_fixa' => 0],
        ['min' => 150001, 'max' => 200000, 'taxa' => 0.16, 'parcela_fixa' => 6500],
        ['min' => 200001, 'max' => 300000, 'taxa' => 0.18, 'parcela_fixa' => 14500],
        ['min' => 300001, 'max' => 500000, 'taxa' => 0.19, 'parcela_fixa' => 32500],
        ['min' => 500001, 'max' => 1000000, 'taxa' => 0.20, 'parcela_fixa' => 70500],
        ['min' => 1000001, 'max' => 1500000, 'taxa' => 0.21, 'parcela_fixa' => 170500],
        ['min' => 1500001, 'max' => 2000000, 'taxa' => 0.22, 'parcela_fixa' => 275500],
        ['min' => 2000001, 'max' => 2500000, 'taxa' => 0.23, 'parcela_fixa' => 385500],
        ['min' => 2500001, 'max' => 5000000, 'taxa' => 0.24, 'parcela_fixa' => 500500],
        ['min' => 5000001, 'max' => PHP_INT_MAX, 'taxa' => 0.25, 'parcela_fixa' => 1100500],
    ];

    public static function calcularIRT(float $salarioSujeito): float
    {
        $irt = 0.0;

        foreach (self::$tabelaIRT as $escalao) {
            if ($salarioSujeito >= $escalao['min'] && $salarioSujeito <= $escalao['max']) {
                $limiteInferior = $escalao['min'] > 0 ? $escalao['min'] - 1 : 0;
                $irt = (($salarioSujeito - $limiteInferior) * $escalao['taxa']) + $escalao['parcela_fixa'];
                break;
            }
        }

        return round($irt, 2);
    }

    public static function calcularINSS(float $salarioBase): float
    {
        return round($salarioBase * 0.03, 2);
    }

    public static function calcularHorasExtras(float $salarioBase, float $horas): float
    {
        $valorHora = $salarioBase / 173.33;
        return round(($valorHora * 1.5) * $horas, 2);
    }
}
