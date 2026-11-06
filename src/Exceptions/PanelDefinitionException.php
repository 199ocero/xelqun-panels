<?php

namespace Xelqun\Panels\Exceptions;

use LogicException;

/**
 * A panel was declared in a way that can't work. This is a mistake in the app's
 * code, not in anything a user did.
 */
class PanelDefinitionException extends LogicException {}
