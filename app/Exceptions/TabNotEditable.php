<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A business reason the people of a table tab, or which person an item
 * belongs to, cannot be changed (tab already closed, canceled order, person of
 * another table…). Its message is written for the staff member and is shown
 * as is on the screen.
 */
final class TabNotEditable extends RuntimeException
{
}
