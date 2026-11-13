<?php

namespace App\Livewire\Concerns;

use App\Actions\Waste\CorrectWasteEntry;
use App\Actions\Waste\RecordWaste;
use App\Exceptions\WasteNotRecordable;
use App\Models\WasteEntry;
use App\Support\CurrentRestaurant;
use Livewire\Attributes\Computed;

/**
 * Correct a waste entry on the waste screen (waste/index): the original stays as
 * it is and CorrectWasteEntry records the difference as a new entry.
 *
 * The using component provides `public ?string $statusMessage`, `authorizeRole()`,
 * `scopedEntry(int)` (restaurant-fenced lookup, 404 otherwise) and `refreshEntries()`.
 */
trait CorrectsWasteEntries
{
    public string $correctingId    = '';
    public string $correctQuantity = '';

    #[Computed]
    public function correcting(): ?WasteEntry
    {
        if ($this->correctingId === '') {
            return null;
        }

        return WasteEntry::where('restaurant_id', CurrentRestaurant::id())
            ->withSum('adjustments', 'quantity')
            ->find((int) $this->correctingId);
    }

    public function startCorrecting(int $entryId): void
    {
        $this->authorizeRole();
        $entry = $this->scopedEntry($entryId);

        $this->resetErrorBag('correctQuantity');

        if ($entry->isAdjustment()) {
            $this->addError('correctQuantity', 'Corrija o lançamento original, não a correção.');

            return;
        }

        $this->correctingId    = (string) $entry->id;
        // Plain number for the input: "2", "1.5".
        $this->correctQuantity = rtrim(rtrim(number_format($entry->effectiveQuantity(), 3, '.', ''), '0'), '.');
        unset($this->correcting);
    }

    public function saveCorrection(CorrectWasteEntry $action): void
    {
        $this->authorizeRole();
        $this->statusMessage = null;

        // Before validating: a foreign id is a 404, never a correction.
        $entry = $this->scopedEntry((int) $this->correctingId);

        $this->validate(
            ['correctQuantity' => ['required', 'numeric', 'min:0', 'max:'.RecordWaste::MAX_QUANTITY]],
            [],
            ['correctQuantity' => 'quantidade correta'],
        );

        try {
            $action->execute($entry, $this->correctQuantity, auth()->user());
        } catch (WasteNotRecordable $e) {
            $this->addError('correctQuantity', $e->getMessage());

            return;
        }

        $this->statusMessage = "Lançamento corrigido: {$entry->ingredient_name} agora soma {$entry->formattedQuantity($entry->effectiveQuantity())}.";
        $this->cancelCorrecting();
        $this->refreshEntries();
    }

    public function cancelCorrecting(): void
    {
        $this->reset('correctingId', 'correctQuantity');
        $this->resetErrorBag('correctQuantity');
        unset($this->correcting);
    }
}
