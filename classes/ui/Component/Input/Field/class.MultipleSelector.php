<?php

declare(strict_types=1);

namespace Customizing\global\plugins\Services\UIComponent\UserInterfaceHook\SurReset\classes\ui\Component\Input\Field;

use Closure;
use Generator;
use ILIAS\Data\Factory;
use ILIAS\Refinery\Constraint;
use ILIAS\UI\Component\Input\Field\Hidden;
use ILIAS\UI\Component\Signal;
use ILIAS\UI\Implementation\Component\Input\Input;
use ILIAS\UI\Implementation\Component\JavaScriptBindable;
use ILIAS\UI\Implementation\Component\Triggerer;
use SurReset\classes\ui\Component\HasOneItem;

/**
 * Class MultipleSelector
 */
class MultipleSelector extends Input implements Hidden
{
    use JavaScriptBindable;
    use Triggerer;

    protected string $label;
    protected ?string $byline;
    protected bool $is_required = false;
    protected bool $is_disabled = false;
    protected ?Constraint $requirement_constraint = null;
    private array $options;

    public function __construct(string $label, array $options, ?string $byline = null)
    {
        global $DIC;

        $this->label = $label;
        $this->byline = $byline;
        $this->options = $options;

        parent::__construct(new Factory(), $DIC->refinery());
    }

    protected function isClientSideValueOk($value): bool
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if (is_array($item)) {
                    if (!isset($item['id']) || !is_numeric($item['id'])) {
                        return false;
                    }
                } else if (!is_numeric($item)) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function withLabel(string $label): MultipleSelector
    {
        $clone = clone $this;
        $clone->label = $label;
        return $clone;
    }

    public function getByline(): ?string
    {
        return $this->byline;
    }

    public function withByline(string $byline): MultipleSelector
    {
        $clone = clone $this;
        $clone->byline = $byline;
        return $clone;
    }

    public function isRequired(): bool
    {
        return $this->is_required;
    }

    public function withRequired(bool $is_required, ?Constraint $requirement_constraint = null): MultipleSelector
    {
        $clone = clone $this;
        $clone->is_required = $is_required;
        $clone->requirement_constraint = $requirement_constraint;
        return $clone;
    }

    public function isDisabled(): bool
    {
        return $this->is_disabled;
    }

    public function withDisabled(bool $is_disabled): MultipleSelector
    {
        $clone = clone $this;
        $clone->is_disabled = $is_disabled;
        return $clone;
    }

    public function getUpdateOnLoadCode(): Closure
    {
        return fn($id) => "$('#$id').on('input', function(event) {
				il.UI.input.onFieldUpdate(event, '$id', $('#$id').val());
			});
			il.UI.input.onFieldUpdate(event, '$id', $('#$id').val());";
    }

    public function withOnUpdate(Signal $signal): self
    {
        return $this->withTriggeredSignal($signal, 'update');
    }

    public function appendOnUpdate(Signal $signal): self
    {
        return $this->appendTriggeredSignal($signal, 'update');
    }

    public function withValue($value): self
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        $value = $value ?? [];

        $this->checkArg("value", $this->isClientSideValueOk($value), "Display value does not match input type.");

        $clone = clone $this;
        $clone->value = $value;
        return $clone;
    }

    public function getOption(): array
    {
        return $this->options;
    }

    protected function getOperations(): Generator
    {
        if ($this->isRequired()) {
            $op = $this->getConstraintForRequirement();

            yield $op;
        }

        foreach ($this->operations as $op) {
            yield $op;
        }
    }

    protected function getConstraintForRequirement(): HasOneItem
    {
        return new HasOneItem(
            $this->data_factory
        );
    }
}