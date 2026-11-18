<?php

namespace App\Actions\Dining;

use App\Exceptions\TabNotEditable;
use App\Models\TableSessionGuest;

/** Staff renames a person of the tab while the table session is open. */
class RenameTableSessionGuest
{
    /**
     * @throws TabNotEditable
     */
    public function execute(TableSessionGuest $guest, ?string $name): TableSessionGuest
    {
        if (! $guest->tableSession->isOpen()) {
            throw new TabNotEditable('A conta desta mesa já foi fechada.');
        }

        $name = trim((string) $name);

        if ($name === '') {
            throw new TabNotEditable('Informe o nome da pessoa.');
        }

        if (mb_strlen($name) > TableSessionGuest::NAME_MAX) {
            throw new TabNotEditable('Use um nome de até '.TableSessionGuest::NAME_MAX.' caracteres.');
        }

        $guest->update(['name' => $name]);

        return $guest;
    }
}
