<?php

namespace App\Exceptions;

use RuntimeException;

/** An identical request claimed the same key and has not finished creating its resource yet. */
class IdempotentRequestInFlight extends RuntimeException {}
