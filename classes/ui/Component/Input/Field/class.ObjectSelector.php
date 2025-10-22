<?php

declare(strict_types=1);

namespace Customizing\global\plugins\Services\UIComponent\UserInterfaceHook\SurReset\classes\ui\Component\Input\Field;

use Closure;
use Generator;
use ilDatabaseException;
use ILIAS\Data\Factory;
use ILIAS\Refinery\Constraint;
use ILIAS\UI\Component\Input\Field\Hidden;
use ILIAS\UI\Component\Signal;
use ILIAS\UI\Implementation\Component\Input\Input;
use ILIAS\UI\Implementation\Component\JavaScriptBindable;
use ILIAS\UI\Implementation\Component\Triggerer;
use ilObject;
use ilObjectFactory;
use ilObjectNotFoundException;
use SurReset\classes\ui\Component\HasOneItem;

/**
 * Class ObjectSelector
 */
class ObjectSelector extends Input implements Hidden
{
    use JavaScriptBindable;
    use Triggerer;

    protected string $label;
    protected ?string $byline;
    protected bool $is_required = false;
    protected bool $is_disabled = false;
    protected ?Constraint $requirement_constraint = null;
    private array $allowed_types;
    private array $processed_objects = [];
    private array $tree = [];

    public function __construct(string $label, ?string $byline = null, array $allowed_types = [])
    {
        global $DIC;

        $this->label = $label;
        $this->byline = $byline;
        $this->allowed_types = $allowed_types;

        try {
            $this->buildTree();
        } catch (ilObjectNotFoundException | ilDatabaseException) {
            $this->tree = [];
        }

        parent::__construct(new Factory(), $DIC->refinery());
    }

    protected function isClientSideValueOk($value): bool
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

    public function withLabel(string $label): ObjectSelector
    {
        $clone = clone $this;
        $clone->label = $label;
        return $clone;
    }

    public function getByline(): ?string
    {
        return $this->byline;
    }

    public function withByline(string $byline): ObjectSelector
    {
        $clone = clone $this;
        $clone->byline = $byline;
        return $clone;
    }

    public function isRequired(): bool
    {
        return $this->is_required;
    }

    public function withRequired(bool $is_required, ?Constraint $requirement_constraint = null): ObjectSelector
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

    public function withDisabled(bool $is_disabled): ObjectSelector
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
        $root_tree = $this->buildTreeRecursive(ROOT_FOLDER_ID);

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

                $child_tree = $this->buildTreeRecursive($child['ref_id'], $depth + 1);

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