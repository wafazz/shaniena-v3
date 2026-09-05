<?php

namespace App\Exceptions;

use RuntimeException;

/** A courier refused a booking, with a message safe to show an operator. */
class ShipmentFailed extends RuntimeException {}
