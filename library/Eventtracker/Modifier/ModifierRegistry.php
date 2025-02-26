<?php

namespace Icinga\Module\Eventtracker\Modifier;

class ModifierRegistry
{
    protected array $modifiers = [];
    protected array $groupedModifiers = [];

    public function register($className)
    {
        /** @var Modifier $className Fake hint, it's a class name, not an instance */
        $className::getName();
    }

    public function getInstance($name, Settings $settings)
    {
    }
}
