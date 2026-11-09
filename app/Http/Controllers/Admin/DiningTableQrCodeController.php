<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DiningTable;
use App\Models\Restaurant;
use App\Support\TableQrCode;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Admin › Salão › Mesas: show, download (PNG/SVG) and batch-print the QR code
 * of each table. Every lookup is fenced to the panel's restaurant.
 */
class DiningTableQrCodeController extends Controller
{
    public function __construct(private readonly TableQrCode $qr) {}

    public function show(int $table): View
    {
        $table = $this->findTable($table);
        $url   = $this->qr->url($table);

        return view('admin.dining.table-qr', [
            'table' => $table,
            'url'   => $url,
            'svg'   => $this->qr->inlineSvg($url),
        ]);
    }

    public function download(int $table, string $format): Response
    {
        $table    = $this->findTable($table);
        $url      = $this->qr->url($table);
        $filename = Str::slug('mesa-'.$table->number).'-qr.'.$format;

        [$body, $type] = $format === 'png'
            ? [$this->qr->png($url), 'image/png']
            : [$this->qr->svg($url), 'image/svg+xml'];

        return response($body, 200, [
            'Content-Type'        => $type,
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function print(): View
    {
        $codes = DiningTable::where('restaurant_id', $this->restaurantId())
            ->orderBy('number')
            ->get()
            ->map(function (DiningTable $table) {
                $url = $this->qr->url($table);

                return ['table' => $table, 'url' => $url, 'svg' => $this->qr->inlineSvg($url, 240)];
            });

        return view('admin.dining.tables-qr-print', ['codes' => $codes]);
    }

    private function restaurantId(): ?int
    {
        return Restaurant::query()->value('id');
    }

    /** Table lookup fenced to this restaurant — a bare findOrFail would cross tenants. */
    private function findTable(int $tableId): DiningTable
    {
        return DiningTable::where('restaurant_id', $this->restaurantId())->findOrFail($tableId);
    }
}
