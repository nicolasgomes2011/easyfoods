<?php

namespace App\Support;

/**
 * CSV of the sales report (App\Actions\Reports\BuildSalesReport): the same
 * lines as the screen table — one per day / week / month, then the total.
 * Semicolon-separated with decimal comma and a UTF-8 BOM, so it opens as
 * columns in a pt-BR spreadsheet.
 */
final class SalesReportCsv
{
    public const HEADER = ['Período', 'Pedidos', 'Faturamento', 'Ticket médio'];

    public static function filename(array $report): string
    {
        return 'vendas-'.$report['from']->toDateString().'-a-'.$report['to']->toDateString().'.csv';
    }

    public static function make(array $report): string
    {
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, self::HEADER, ';', '"', '');

        foreach ($report['rows'] as $row) {
            fputcsv($handle, [$row['label'], $row['orders'], self::number($row['revenue']), self::number($row['average_ticket'])], ';', '"', '');
        }

        fputcsv($handle, ['Total', $report['orders'], self::number($report['revenue']), self::number($report['average_ticket'])], ';', '"', '');

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return "\xEF\xBB\xBF".$csv;
    }

    private static function number(float $value): string
    {
        return number_format($value, 2, ',', '');
    }
}
