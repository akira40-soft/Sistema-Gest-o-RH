<?php
/**
 * Utilitários Angolanos: BI, NIF, validações, formatações
 */

namespace App\Utils;

class Angola
{
    /**
     * Valida número de BI Angolano
     * Formato: 9 dígitos + 2 letras + 3 dígitos (ex: 123456789LA012)
     */
    public static function validarBI(string $bi): bool
    {
        $bi = preg_replace('/\s+/', '', strtoupper($bi));
        return (bool)preg_match('/^[0-9]{9}[A-Z]{2}[0-9]{3}$/', $bi);
    }

    /**
     * Formata BI: 123456789LA012 -> 123456789 LA 012
     */
    public static function formatarBI(string $bi): string
    {
        $bi = preg_replace('/\s+/', '', strtoupper($bi));
        if (strlen($bi) === 14) {
            return substr($bi, 0, 9) . ' ' . substr($bi, 9, 2) . ' ' . substr($bi, 11);
        }
        return $bi;
    }

    /**
     * Valida NIF Angolano (9 dígitos + 1 letra de controle)
     */
    public static function validarNIF(string $nif): bool
    {
        $nif = preg_replace('/\s+/', '', strtoupper($nif));
        if (!preg_match('/^[0-9]{8}[A-Z0-9]$/', $nif)) return false;

        // Pesos para cálculo (simplificado)
        $pesos = [9, 8, 7, 6, 5, 4, 3, 2];
        $soma = 0;
        for ($i = 0; $i < 8; $i++) {
            $soma += intval($nif[$i]) * $pesos[$i];
        }
        $resto = $soma % 23;
        $letras = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $digitoEsperado = $letras[$resto];
        return $nif[8] === $digitoEsperado;
    }

    /**
     * Formata NIF: 123456789 -> 1 234 567 89
     */
    public static function formatarNIF(string $nif): string
    {
        $nif = preg_replace('/\s+/', '', $nif);
        if (strlen($nif) === 9) {
            return substr($nif, 0, 1) . ' ' . substr($nif, 1, 3) . ' ' . substr($nif, 4, 3) . ' ' . substr($nif, 7);
        }
        return $nif;
    }

    /**
     * Valida IBAN Angolano
     * AO + 2 check digits + 21 dígitos = 25 chars total
     */
    public static function validarIBAN(string $iban): bool
    {
        $iban = strtoupper(preg_replace('/\s+/', '', $iban));
        return (bool)preg_match('/^AO[0-9]{2}[0-9]{21}$/', $iban);
    }

    public static function formatarIBAN(string $iban): string
    {
        $iban = strtoupper(preg_replace('/\s+/', '', $iban));
        return chunk_split($iban, 4, ' ');
    }

    /**
     * Valida telefone angolano
     * Aceita: +244 9XX XXX XXX, 9XXXXXXXX, 222XXXXXX
     */
    public static function validarTelefone(string $tel): bool
    {
        $tel = preg_replace('/[^0-9]/', '', $tel);
        // 9 dígitos (rede móvel) ou 9 dígitos (rede fixa)
        return preg_match('/^(244)?(9[0-9]{8}|2[0-9]{7})$/', $tel) === 1;
    }

    public static function formatarTelefone(string $tel): string
    {
        $tel = preg_replace('/[^0-9]/', '', $tel);
        if (strlen($tel) === 9) {
            if ($tel[0] === '9') {
                return '+244 ' . substr($tel, 0, 3) . ' ' . substr($tel, 3, 3) . ' ' . substr($tel, 6);
            }
            return '+244 ' . substr($tel, 0, 3) . ' ' . substr($tel, 3, 3) . ' ' . substr($tel, 6);
        }
        if (strlen($tel) === 12 && substr($tel, 0, 3) === '244') {
            return '+' . substr($tel, 0, 3) . ' ' . substr($tel, 3, 3) . ' ' . substr($tel, 6, 3) . ' ' . substr($tel, 9);
        }
        return $tel;
    }

    /**
     * Calcula idade a partir da data de nascimento
     */
    public static function idade(string $dataNascimento): int
    {
        $nasc = new \DateTime($dataNascimento);
        $hoje = new \DateTime();
        return $hoje->diff($nasc)->y;
    }

    /**
     * Calcula tempo de serviço em anos/meses
     */
    public static function tempoServico(string $dataAdmissao, ?string $dataDemissao = null): array
    {
        $ini = new \DateTime($dataAdmissao);
        $fim = $dataDemissao ? new \DateTime($dataDemissao) : new \DateTime();
        $diff = $fim->diff($ini);
        return [
            'anos'   => $diff->y,
            'meses'  => $diff->m,
            'dias'   => $diff->d,
            'texto'  => $diff->y . 'a ' . $diff->m . 'm',
            'total_meses' => ($diff->y * 12) + $diff->m
        ];
    }

    /**
     * Gera número de processo/protocolo
     */
    public static function gerarProtocolo(string $prefixo = 'RG'): string
    {
        return $prefixo . '-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
    }

    /**
     * Lista de províncias de Angola
     */
    public static function provincias(): array
    {
        return [
            'Bengo', 'Benguela', 'Bié', 'Cabinda', 'Cuando-Cubango',
            'Cuanza Norte', 'Cuanza Sul', 'Cunene', 'Huambo', 'Huíla',
            'Luanda', 'Lunda Norte', 'Lunda Sul', 'Malanje', 'Moxico',
            'Namibe', 'Uíge', 'Zaire'
        ];
    }

    /**
     * Calcula dias úteis (excluindo sábados e domingos) entre duas datas
     */
    public static function calcularDiasUteis(string $inicio, string $fim): int
    {
        try {
            $start = new \DateTime($inicio);
            $end = new \DateTime($fim);
            if ($end < $start) return 0;
            $dias = 0;
            $current = clone $start;
            while ($current <= $end) {
                $dow = (int)$current->format('N');
                if ($dow < 6) $dias++;
                $current->modify('+1 day');
            }
            return $dias;
        } catch (\Exception $e) {
            return 0;
        }
    }
}
