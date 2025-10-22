<?php

declare(strict_types=1);

namespace SurReset\classes\ui\Component\Input\Field;

use Closure;
use Exception;
use Generator;
use ilDatabaseException;
use ILIAS\Data\Factory;
use ILIAS\Refinery\Constraint;
use ILIAS\Refinery\Transformation;
use ILIAS\UI\Component\Input\Field\Text;
use ILIAS\UI\Component\Signal;
use ILIAS\UI\Implementation\Component\ComponentHelper;
use ILIAS\UI\Implementation\Component\Input\Field\FormInputInternal;
use ILIAS\UI\Implementation\Component\Input\InputData;
use ILIAS\UI\Implementation\Component\Input\NameSource;
use ILIAS\UI\Implementation\Component\JavaScriptBindable;
use ILIAS\UI\Implementation\Component\Triggerer;
use ilObject;
use ilObjectFactory;
use ilObjectNotFoundException;
use LogicException;
use SurReset\classes\ui\Component\HasOneItem;

/**
 * Class ObjectSelector
 */
class ObjectSelector implements Text, FormInputInternal
{
    use ComponentHelper;
    use JavaScriptBindable;
    use Triggerer;

    protected string $label;
    protected ?string $byline;
    protected bool $is_required = false;
    protected bool $is_disabled = false;
    protected ?Constraint $requirement_constraint = null;
    private \ILIAS\Refinery\Factory $refinery;
    private array $allowed_types;
    private array $processed_objects = [];
    private array $tree = [];
    protected $value = null;

    protected $error = null;

    protected $content = null;

    private array $operations;
    protected Factory $data_factory;

    private $name = null;


    public function __construct(string $label, ?string $byline = null, array $allowed_types = [])
    {
        global $DIC;

        $this->label = $label;
        $this->byline = $byline;
        $this->allowed_types = $allowed_types;

        $this->refinery = $DIC->refinery();
        $this->data_factory = new Factory();

        try {
            $this->buildTree();
        } catch (ilObjectNotFoundException | ilDatabaseException $ex) {
            $this->tree = [];
        }

        $this->operations = [];
    }

    public function isClientSideValueOk($value): bool
    {
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

    public function withLabel($label): ObjectSelector
    {
        $clone = clone $this;
        $clone->label = $label;
        return $clone;
    }

    public function getByline(): ?string
    {
        return $this->byline;
    }

    public function withByline($byline): ObjectSelector
    {
        $clone = clone $this;
        $clone->byline = $byline;
        return $clone;
    }

    public function isRequired(): bool
    {
        return $this->is_required;
    }

    public function withRequired($is_required, ?Constraint $requirement_constraint = null): ObjectSelector
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

    public function withDisabled($is_disabled): ObjectSelector
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

    public function getTree(): array
    {
        return $this->tree;
    }

    /**
     * @throws ilObjectNotFoundException
     * @throws ilDatabaseException
     */
    private function buildTree(): void
    {
        global $DIC;

        $this->processed_objects = [];

        // Build recursive tree
        $root_tree = $this->buildTreeRecursive((int) ROOT_FOLDER_ID);

        // Add objects of allowed types
        foreach ($this->allowed_types as $type) {
            $objects_by_type = ilObject::_getObjectsByType($type);

            foreach ($objects_by_type as $obj_id => $obj_data) {
                if (!isset($this->processed_objects[$obj_id])) {
                    $ref_ids = ilObject::_getAllReferences($obj_id);

                    foreach ($ref_ids as $ref_id) {
                        if (ilObject::_exists($ref_id, true)) {
                            $obj = ilObjectFactory::getInstanceByRefId($ref_id);
                            $root_tree['children'][] = [
                                'id' => $obj_id,
                                'ref_id' => $ref_id,
                                'title' => $obj->getTitle(),
                                'type' => $type,
                                'icon' => $DIC->ui()->factory()->symbol()->icon()->standard($type, $type),
                                'depth' => 1,
                                'selectable' => true,
                                'children' => []
                            ];

                            $this->processed_objects[$obj_id] = true;
                            break;
                        }
                    }
                }
            }
        }

        $this->tree = [$root_tree];
    }

    private function buildTreeRecursive(int $ref_id, int $depth = 0): array
    {
        global $DIC;

        $node_data = $DIC->repositoryTree()->getNodeData($ref_id);
        $children_data = [];

        $children = $DIC->repositoryTree()->getChilds($ref_id);

        foreach ($children as $child) {
            if (in_array($child['type'], $this->allowed_types) || $child['type'] === 'fold' || $child['type'] === 'cat' || $child['type'] === 'crs') {

                if (in_array($child['type'], $this->allowed_types)) {
                    if (isset($this->processed_objects[$child['obj_id']])) {
                        continue;
                    }
                    $this->processed_objects[$child['obj_id']] = true;
                }

                $child_tree = $this->buildTreeRecursive((int) $child['ref_id'], $depth + 1);

                if (in_array($child['type'], $this->allowed_types) || !empty($child_tree['children'])) {
                    $children_data[] = $child_tree;
                }
            }
        }

        return [
            'id' => $node_data['obj_id'],
            'ref_id' => $node_data['ref_id'],
            'title' => $node_data['title'],
            'type' => $node_data['type'],
            'icon' => $DIC->ui()->factory()->symbol()->icon()->standard($node_data['type'], $node_data['type']),
            'depth' => $depth,
            'selectable' => in_array($node_data['type'], $this->allowed_types),
            'children' => $children_data
        ];
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