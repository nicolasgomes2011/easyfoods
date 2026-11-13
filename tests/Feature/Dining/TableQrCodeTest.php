<?php

namespace Tests\Feature\Dining;

use App\Enums\DiningTableStatus;
use App\Enums\UserRole;
use App\Models\DiningTable;
use App\Models\Restaurant;
use App\Models\User;
use App\Support\TableQrCode;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin › Salão › Mesas QR codes: each table's code encodes that table's
 * public link (/mesa/{uuid}), downloads as PNG or SVG, and a print page lists
 * every table of the restaurant. Tables of another restaurant are a 404.
 */
class TableQrCodeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The panel scopes to Restaurant::query()->value('id'), which is
     * planner-order-dependent, NOT first-created. Resolve which restaurant
     * the panel will use and return [panel, foreign].
     */
    private function panelAndForeign(): array
    {
        $a = Restaurant::factory()->create();
        $b = Restaurant::factory()->create();

        $panelId = Restaurant::query()->value('id');

        return $panelId === $a->id ? [$a, $b] : [$b, $a];
    }

    private function actAsStaff(UserRole $role = UserRole::Admin): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]));
    }

    private function table(Restaurant $restaurant, string $number): DiningTable
    {
        return DiningTable::create([
            'restaurant_id' => $restaurant->id,
            'number'        => $number,
            'status'        => DiningTableStatus::Free,
        ]);
    }

    private function tableUrl(DiningTable $table): string
    {
        return 'http://localhost/mesa/'.$table->uuid;
    }

    public function test_qr_page_shows_the_code_the_table_link_and_downloads(): void
    {
        [$panel] = $this->panelAndForeign();
        $table = $this->table($panel, '7');
        $this->actAsStaff();

        $this->get(route('admin.dining.tables.qr', $table->id))
            ->assertOk()
            ->assertSee('Mesa 7')
            ->assertSee($this->tableUrl($table))
            ->assertSee('<svg', false)
            ->assertSee(route('admin.dining.tables.qr.download', [$table->id, 'png']))
            ->assertSee(route('admin.dining.tables.qr.download', [$table->id, 'svg']))
            ->assertDontSee('Undefined');
    }

    public function test_svg_download_encodes_the_table_link(): void
    {
        [$panel] = $this->panelAndForeign();
        $table = $this->table($panel, '7');
        $other = $this->table($panel, '8');
        $this->actAsStaff();

        $response = $this->get(route('admin.dining.tables.qr.download', [$table->id, 'svg']));

        $response->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->assertHeader('Content-Disposition', 'attachment; filename="mesa-7-qr.svg"');

        $qr = app(TableQrCode::class);
        $this->assertSame($qr->svg($this->tableUrl($table)), $response->getContent());
        $this->assertNotSame($qr->svg($this->tableUrl($other)), $response->getContent());
    }

    public function test_png_download_draws_the_qr_matrix_of_the_table_link(): void
    {
        [$panel] = $this->panelAndForeign();
        $table = $this->table($panel, 'A1');
        $this->actAsStaff();

        $response = $this->get(route('admin.dining.tables.qr.download', [$table->id, 'png']));

        $response->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('Content-Disposition', 'attachment; filename="mesa-a1-qr.png"');

        $png = $response->getContent();
        $this->assertStringStartsWith("\x89PNG", $png);

        // Every module of the expected QR matrix must be drawn at its place.
        $matrix = Encoder::encode($this->tableUrl($table), ErrorCorrectionLevel::M())->getMatrix();
        $image  = imagecreatefromstring($png);
        $scale  = intdiv(imagesx($image), $matrix->getWidth() + 2 * TableQrCode::MARGIN);
        $this->assertGreaterThan(0, $scale);

        for ($y = 0; $y < $matrix->getHeight(); $y++) {
            for ($x = 0; $x < $matrix->getWidth(); $x++) {
                $px  = ($x + TableQrCode::MARGIN) * $scale + intdiv($scale, 2);
                $py  = ($y + TableQrCode::MARGIN) * $scale + intdiv($scale, 2);
                $rgb = imagecolorsforindex($image, imagecolorat($image, $px, $py));

                $this->assertSame(
                    $matrix->get($x, $y) === 1 ? 0 : 255,
                    $rgb['red'],
                    "Module ({$x}, {$y}) drawn with the wrong color."
                );
            }
        }
    }

    public function test_unknown_download_format_is_404(): void
    {
        [$panel] = $this->panelAndForeign();
        $table = $this->table($panel, '7');
        $this->actAsStaff();

        $this->get('/admin/dining/tables/'.$table->id.'/qr.gif')->assertNotFound();
    }

    public function test_table_of_another_restaurant_is_404(): void
    {
        [, $foreign] = $this->panelAndForeign();
        $table = $this->table($foreign, '9');
        $this->actAsStaff();

        $this->get(route('admin.dining.tables.qr', $table->id))->assertNotFound();
        $this->get(route('admin.dining.tables.qr.download', [$table->id, 'png']))->assertNotFound();
        $this->get(route('admin.dining.tables.qr.download', [$table->id, 'svg']))->assertNotFound();
    }

    public function test_print_page_lists_every_table_of_the_restaurant_only(): void
    {
        [$panel, $foreign] = $this->panelAndForeign();
        $one     = $this->table($panel, '1');
        $two     = $this->table($panel, '2');
        $outside = $this->table($foreign, '99');
        $this->actAsStaff();

        $response = $this->get(route('admin.dining.tables.qr.print'));

        $response->assertOk()
            ->assertSeeInOrder(['Mesa 1', 'Mesa 2'])
            ->assertSee($this->tableUrl($one))
            ->assertSee($this->tableUrl($two))
            ->assertDontSee($this->tableUrl($outside))
            ->assertDontSee('Mesa 99');

        $this->assertSame(2, substr_count($response->getContent(), '<svg'));
    }

    public function test_tables_screen_links_to_each_qr_page_and_the_print_page(): void
    {
        [$panel] = $this->panelAndForeign();
        $table = $this->table($panel, '7');
        $this->actAsStaff(UserRole::Attendant);

        $this->get(route('admin.dining.tables'))
            ->assertOk()
            ->assertSee(route('admin.dining.tables.qr', $table->id))
            ->assertSee(route('admin.dining.tables.qr.print'));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        [$panel] = $this->panelAndForeign();
        $table = $this->table($panel, '7');

        $this->get(route('admin.dining.tables.qr.download', [$table->id, 'png']))->assertRedirect(route('login'));
    }
}
