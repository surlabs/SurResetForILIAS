<?php

declare(strict_types=1);

namespace SurReset\classes\ui\Component\Input\Field;

use Closure;
use Exception;
use Generator;
use ILIAS\Refinery\Constraint;
use ILIAS\Refinery\Factory;
use ILIAS\Refinery\Transformation;
use ILIAS\UI\Component\Input\Field\Text;
use ILIAS\UI\Component\Signal;
use ILIAS\UI\Implementation\Component\ComponentHelper;
use ILIAS\UI\Implementation\Component\Input\Field\FormInputInternal;
use ILIAS\UI\Implementation\Component\Input\InputData;
use ILIAS\UI\Implementation\Component\Input\NameSource;
use ILIAS\UI\Implementation\Component\JavaScriptBindable;
use ILIAS\UI\Implementation\Component\Triggerer;
use LogicException;
use SurReset\classes\ui\Component\HasOneItem;

/**
 * Class MultipleSelector
 */
class MultipleSelector implements Text, FormInputInternal
{
    use ComponentHelper;
    use JavaScriptBindable;
    use Triggerer;

    protected string $label;
    protected ?string $byline;
    protected bool $is_required = false;
    protected bool $is_disabled = false;
    protected ?Constraint $requirement_constraint = null;
    private Factory $refinery;
    protected $value = null;

    protected $error = null;

    protected $content = null;

    private array $operations;
    protected \ILIAS\Data\Factory $data_factory;

    private $name = null;

    private array $options;


    public function __construct(string $label, array $options, ?string $byline = null)
    {
        global $DIC;

        $this->refinery = $DIC->refinery();
        $this->data_factory = new \ILIAS\Data\Factory();

        $this->label = $label;
        $this->byline = $byline;
        $this->options = $options;

        $this->operations = [];
    }

    public function isClientSideValueOk($value): bool
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

    public function withLabel($label): MultipleSelector
    {
        $clone = clone $this;
        $clone->label = $label;
        return $clone;
    }

    public function getByline(): ?string
    {
        return $this->byline;
    }

    public function withByline($byline): MultipleSelector
    {
        $clone = clone $this;
        $clone->byline = $byline;
        return $clone;
    }

    public function isRequired(): bool
    {
        return $this->is_required;
    }

    public function withRequired($is_required, ?Constraint $requirement_constraint = null): MultipleSelector
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

    public function withDisabled($is_disabled): MultipleSelector
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

    public function isComplex(): bool
    {
        return false;
    }

    public function getError()
    {
        return $this->error;
    }

    public function withError($error)
    {
        $clone = clone $this;
        $clone->setError($error);

        return $clone;
    }

    private function setError($error)
    {
        $this->checkStringArg("error", $error);
        $this->error = $error;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function withAdditionalTransformation(Transformation $trafo)
    {
        $clone = clone $this;
        $clone->setAdditionalTransformation($trafo);

        return $clone;
    }

    protected function setAdditionalTransformation(Transformation $trafo)
    {
        $this->operations[] = $trafo;
        if ($this->content !== null) {
            if (!$this->content->isError()) {
                $this->content = $trafo->applyTo($this->content);
            }
            if ($this->content->isError()) {
                $this->setError($this->content->error());
            }
        }
    }

    public function withNameFrom(NameSource $source)
    {
        $clone = clone $this;
        $clone->name = $source->getNewName();

        return $clone;
    }

    public function getName()
    {
        return $this->name;
    }

    public function withInput(InputData $input)
    {
        if ($this->getName() === null) {
            throw new LogicException("Can only collect if input has a name.");
        }

        //TODO: Discuss, is this correct here. If there is no input contained in this post
        //We assign null. Note that unset checkboxes are not contained in POST.
        if (!$this->isDisabled()) {
            $value = $input->getOr($this->getName(), null);
            // ATTENTION: There was a special case for the Filter Input Container here,
            // which lead to #27909. The issue will most certainly appear again in. If
            // you are the one debugging it and came here: Please don't put knowledge
            // of the special case for the filter in this general class. Have a look
            // into https://mantis.ilias.de/view.php?id=27909 for the according discussion.
            $clone = $this->withValue($value);
        } else {
            $clone = $this;
        }

        $clone->content = $this->applyOperationsTo($clone->getValue());
        if ($clone->content->isError()) {
            $error = $clone->content->error();
            if ($error instanceof Exception) {
                $error = $error->getMessage();
            }
            return $clone->withError("" . $error);
        }

        return $clone;
    }

    protected function applyOperationsTo($res)
    {
        if ($res === null && !$this->isRequired()) {
            return $this->data_factory->ok($res);
        }

        $res = $this->data_factory->ok($res);
        foreach ($this->getOperations() as $op) {
            if ($res->isError()) {
                return $res;
            }

            $res = $op->applyTo($res);
        }

        return $res;
    }

    private function getOperations(): Generator
    {
        if ($this->isRequired()) {
            $op = $this->getConstraintForRequirement();
            if ($op !== null) {
                yield $op;
            }
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

    public function getContent()
    {
        if (is_null($this->content)) {
            throw new LogicException("No content of this field has been evaluated yet. Seems withRequest was not called.");
        }
        return $this->content;
    }
}