<?php

namespace Icinga\Module\Eventtracker\Modifier;

use gipfl\Json\JsonString;
use JsonSerializable;
use stdClass;

class ModifierChain implements JsonSerializable
{
    protected array $modifiers = [];

    public function __construct(array $modifiers)
    {
        foreach ($modifiers as $pair) {
            $this->addModifier($pair[1], $pair[0]);
        }
    }

    public static function fromSerialization(array $serializedModifiers): ModifierChain
    {
        $modifiers = [];
        foreach ($serializedModifiers as $modifier) {
            $modifiers[] = static::makeModifier($modifier);
        }
        return new static($modifiers);
    }

    protected static function makeModifier(array $modifier): array
    {
        /** @var Modifier|string $class Just a hint, it's a string */
        $class = __NAMESPACE__ . '\\' . $modifier[1];
        return [
            $modifier[0],
            new $class(
                Settings::fromSerialization($modifier[2] ?? (object) [])
            )
        ];
    }

    /**
     * @return array of arrays, 0 => property, 1 => modifier
     */
    public function getModifiers(): array
    {
        return $this->modifiers;
    }

    public function process(stdClass $object)
    {
        foreach ($this->getModifiers() as list($propertyName, $modifier)) {
            static::applyModifier($modifier, $object, $propertyName);
        }
    }

    public static function applyModifier(Modifier $modifier, stdClass $object, $propertyName)
    {
        $value = $modifier->transform($object, $propertyName);
        if ($value instanceof ModifierUnset) {
            ObjectUtils::unsetSpecificValue($object, $propertyName);
        } else {
            ObjectUtils::setSpecificValue($object, $propertyName, $value);
        }
    }

    public function addModifier(Modifier $modifier, $propertyName)
    {
        $this->modifiers[] = [$propertyName, $modifier];
    }

    public function removeModifier(int $row): ModifierChain
    {
        $modifiers = $this->modifiers;
        unset($modifiers[$row]);
        $this->modifiers = array_values($modifiers);

        return $this;
    }

    public function moveUp(int $index): ModifierChain
    {
        $modifiers = $this->modifiers;
        if ($index === 0) {
            return $this;
        }
        $tempNext = $modifiers[$index - 1];
        $temp = $modifiers[$index];
        $modifiers[$index - 1] = $temp;
        $modifiers[$index] = $tempNext;
        $this->modifiers = $modifiers;

        return $this;
    }

    public function moveDown(int $index): ModifierChain
    {
        $modifiers = $this->modifiers;
        if (! isset($modifiers[$index + 1])) {
            return $this;
        }
        $tempNext = $modifiers[$index + 1];
        $temp = $modifiers[$index];
        $modifiers[$index + 1] = $temp;
        $modifiers[$index] = $tempNext;
        $this->modifiers = $modifiers;

        return $this;
    }

    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        $result = [];
        foreach ($this->modifiers as $modifier) {
            $instance = $modifier[1];
            assert($instance instanceof Modifier);
            $settings = $instance->getSettings()->jsonSerialize();
            if ((array) $settings === []) {
                $result[] = [$modifier[0], $instance::getName()];
            } else {
                $result[] = [$modifier[0], $instance::getName(), $settings];
            }
        }

        return $result;
    }
}
