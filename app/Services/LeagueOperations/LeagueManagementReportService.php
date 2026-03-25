<?php

namespace App\Services\LeagueOperations;

class LeagueManagementReportService
{
    /**
     * @param  array<string, mixed>  $module
     */
    public function makePdf(string $leagueName, array $module): string
    {
        $summary = $module['summary'];
        $payments = $module['payments'];
        $expenses = $module['expenses'];
        $board = $module['board'];
        $referrals = $module['referrals'];

        $lines = [
            'VAMO AL GAME',
            sprintf('Reporte de gestion - %s', $leagueName),
            sprintf('Corte: %s', $summary['selected_cut']['label']),
            sprintf('Periodo: %s a %s', $summary['selected_cut']['starts_on'], $summary['selected_cut']['ends_on']),
            sprintf('Fecha limite: %s', $summary['selected_cut']['due_on']),
            '',
            'BALANCE DE LA LIGA',
            sprintf('Entradas por cuotas: %s', $this->money($summary['income']['cash_payments_cents'])),
            sprintf('Entradas por invitados: %s', $this->money($summary['income']['guest_income_cents'])),
            sprintf('Total ingresos: %s', $this->money($summary['income']['total_cents'])),
            sprintf('Total gastos: %s', $this->money($summary['expenses']['total_cents'])),
            sprintf('Balance del corte: %s', $this->money($summary['balance_cents'])),
            '',
            'REGISTRO DE PAGOS',
        ];

        foreach ($payments as $payment) {
            $lines[] = sprintf(
                '%s | %s | pagado %s | a favor %s | deuda previa %s',
                $payment['player']['name'],
                strtoupper($payment['balance']['status']),
                $this->money($payment['balance']['amount_paid_cents']),
                $this->money($payment['balance']['extra_credit_cents']),
                $this->money($payment['balance']['previous_debt_cents']),
            );
        }

        $lines[] = '';
        $lines[] = 'GASTOS DEL CORTE';

        foreach ($expenses as $expense) {
            $lines[] = sprintf(
                '%s | %s%s',
                $expense['name'],
                $this->money($expense['amount_cents']),
                $expense['is_system_generated'] ? ' | automatico' : '',
            );
        }

        $lines[] = '';
        $lines[] = 'DIRECTIVA';

        foreach ($board['members'] as $member) {
            $lines[] = sprintf(
                '%s | aporte proyectado %s',
                $member['name'],
                $this->money($member['share_cents']),
            );
        }

        $lines[] = '';
        $lines[] = 'REFERIDOS';

        if ($referrals === []) {
            $lines[] = 'Sin referidos registrados.';
        } else {
            foreach ($referrals as $referral) {
                $names = collect($referral['members'])
                    ->pluck('name')
                    ->implode(', ');

                $lines[] = sprintf(
                    '%s | credito disponible %s | %s',
                    $referral['referrer']['name'],
                    $this->money($referral['available_credit_cents']),
                    $names,
                );
            }
        }

        return $this->buildPdf($lines);
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function buildPdf(array $lines): string
    {
        $lineChunks = array_chunk($lines, 38);
        $objects = [];
        $pageIds = [];
        $fontId = 1;
        $pagesId = 2;
        $nextId = 3;

        $objects[$fontId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

        foreach ($lineChunks as $chunk) {
            $content = $this->pageContent($chunk);
            $contentId = $nextId++;
            $pageId = $nextId++;
            $stream = sprintf("<< /Length %d >>\nstream\n%s\nendstream", strlen($content), $content);
            $objects[$contentId] = $stream;
            $objects[$pageId] = sprintf(
                '<< /Type /Page /Parent %d 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 %d 0 R >> >> /Contents %d 0 R >>',
                $pagesId,
                $fontId,
                $contentId,
            );
            $pageIds[] = $pageId;
        }

        $objects[$pagesId] = sprintf(
            '<< /Type /Pages /Count %d /Kids [%s] >>',
            count($pageIds),
            implode(' ', array_map(fn (int $pageId): string => sprintf('%d 0 R', $pageId), $pageIds)),
        );

        $catalogId = $nextId;
        $objects[$catalogId] = sprintf('<< /Type /Catalog /Pages %d 0 R >>', $pagesId);

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= sprintf("%d 0 obj\n%s\nendobj\n", $id, $object);
        }

        $xrefOffset = strlen($pdf);
        $pdf .= sprintf("xref\n0 %d\n", count($objects) + 1);
        $pdf .= "0000000000 65535 f \n";

        foreach (array_keys($objects) as $id) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }

        $pdf .= sprintf(
            "trailer << /Size %d /Root %d 0 R >>\nstartxref\n%d\n%%%%EOF",
            count($objects) + 1,
            $catalogId,
            $xrefOffset,
        );

        return $pdf;
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function pageContent(array $lines): string
    {
        $content = "BT\n/F1 12 Tf\n50 760 Td\n14 TL\n";

        foreach ($lines as $index => $line) {
            $escaped = $this->escape($line);
            $content .= $index === 0
                ? sprintf("(%s) Tj\n", $escaped)
                : sprintf("T*\n(%s) Tj\n", $escaped);
        }

        $content .= 'ET';

        return $content;
    }

    private function escape(string $value): string
    {
        return str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\(', '\)'],
            iconv('UTF-8', 'Windows-1252//TRANSLIT', $value) ?: $value,
        );
    }

    private function money(int $amountCents): string
    {
        $amount = $amountCents / 100;

        return 'RD$'.number_format($amount, 2);
    }
}
