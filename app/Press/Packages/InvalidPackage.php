<?php

namespace App\Press\Packages;

use RuntimeException;

/** A plugin or theme package (zip, folder or manifest) that failed validation. The message is safe to show to admins. */
class InvalidPackage extends RuntimeException {}
