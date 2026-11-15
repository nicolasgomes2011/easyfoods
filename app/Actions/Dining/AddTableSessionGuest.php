<?php

namespace App\Actions\Dining;

use App\Exceptions\TabNotEditable;
use App\Models\TableSession;
use App\Models\TableSessionGuest;

/**
 * Staff adds a person to the tab of an open table session. Without a name the
 * person is numbered ("Pessoa 1", "Pessoa 2"…) and can be renamed later.
 */
class AddTableSessionGuest
{
    /**
     * @throws TabNotEditable
     */
    public function execute(TableSession $session, ?string $name): TableSessionGuest
    {
        if (! $session->isOpen()) {
            throw new TabNotEditable('A conta desta mesa já foi fechada.');
        }

        $name = trim((string) $name);

        if ($name === '') {
            $name = 'Pessoa '.($session->guests()->count() + 1);
        }

        if (mb_strlen($name) > TableSessionGuest::NAME_MAX) {
            throw new TabNotEditable('Use um nome de até '.TableSessionGuest::NAME_MAX.' caracteres.');
        }

        return TableSessionGuest::create([
            'restaurant_id'    => $session->restaurant_id,
            'table_session_id' => $session->id,
            'name'             => $name,
        ]);
    }
}
