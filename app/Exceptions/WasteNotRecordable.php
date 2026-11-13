<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A business reason a waste entry (or its correction) cannot be recorded
 * (archived ingredient, quantity out of range, nothing to correct…). Its
 * message is written for the staff member and is shown as is on the screen.
 */
final class WasteNotRecordable extends RuntimeException
{
}
