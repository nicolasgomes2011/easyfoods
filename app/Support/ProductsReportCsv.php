<?php

namespace App\Support;

use App\Actions\Reports\BuildProductsReport;

/**
 * CSV of the products and peak hours report (BuildProductsReport): the same
 * lines as the screen, one block per table — top products by quantity, top
 * products by revenue, orders by hour, orders by day of the week — separated
 * by an empty line. Semicolon-separated with decimal comma and a UTF-8 BOM, so
 * it opens as columns in a pt-BR spreadsheet (same format as SalesReportCsv).
 */
final class ProductsReportCsv
{
    public static function filename(array $report): string
    {
        return 'produtos-e-horarios-'.$report['from']->toDateString().'-a-'.$report['to']->toDateString().'.csv';
    }

    public static function make(array $report): string
    {
        $handle = fopen('php://temp', 'r+');
        $line   = fn (array $fields) => fputcsv($handle, $fields, ';', '"', '');

        foreach (['by_quantity' => 'Mais vendidos (quantidade)', 'by_revenue' => 'Maior receita'] as $key => $title) {
            $line([$title, 'Quantidade', 'Receita']);

            foreach ($report[$key] as $product) {
                $line([$product['name'], $product['quantity'], number_format($product['revenue'], 2, ',', '')]);
            }

            fwrite($handle, "\n");
        }

        $line(['Hora', 'Pedidos']);
        foreach ($report['by_hour'] as $hour => $orders) {
            $line([self::hour($hour), $orders]);
        }

        fwrite($handle, "\n");

        $line(['Dia da semana', 'Pedidos']);
        foreach ($report['by_weekday'] as $day => $orders) {
            $line([BuildProductsReport::WEEKDAYS[$day], $orders]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return "\xEF\xBB\xBF".$csv;
    }

    /** 0 → "00h", 19 → "19h" (also used on the screen). */
    public static function hour(int $hour): string
    {
        return str_pad((string) $hour, 2, '0', STR_PAD_LEFT).'h';
    }
}
