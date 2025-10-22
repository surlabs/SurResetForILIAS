<?php

declare(strict_types=1);

namespace SurReset\classes\ui\Component\Input\Field;

use ILIAS\UI\Component\Component;
use ILIAS\UI\Component\Input\Field\FormInput;
use ILIAS\UI\Component\Tree\Node\Factory;
use ILIAS\UI\Component\Tree\Node\Node;
use ILIAS\UI\Component\Tree\TreeRecursion;
use ILIAS\UI\Implementation\Component\Input\Field\Renderer as RendererILIAS;
use ILIAS\UI\Implementation\Render\Template;
use ilSurResetPlugin;
use ilTemplate;
use ilTemplateException;

/**
 * Class Renderer
 */
class Renderer extends RendererILIAS
{
    private static int $lastObjSelectorId = 0;
    private static int $lastMultipleSelectorId = 0;
    protected ?\ILIAS\UI\Renderer $default_renderer = null;

    /**
     * @throws ilTemplateException
     */
    public function render(Component $component, ?\ILIAS\UI\Renderer $default_renderer = null): string
    {
        global $DIC;

        $DIC->ui()->mainTemplate()->addJavaScript('Customizing/global/plugins/Services/Cron/CronHook/SurReset/templates/Component/Input/Field/customField.js');
        $DIC->ui()->mainTemplate()->addCss('Customizing/global/plugins/Services/Cron/CronHook/SurReset/templates/Component/Input/Field/customField.css');

        if (isset($default_renderer)) {
            $this->default_renderer = $default_renderer;
        } else if (!isset($this->default_renderer)) {
            $this->default_renderer = $DIC->ui()->renderer();
        }

        switch (true) {
            case $component instanceof ObjectSelector:
                return $this->renderObjectSelector($component);
            case $component instanceof MultipleSelector:
                return $this->renderMultipleSelector($component);
            default:
                return $this->default_renderer->render($component);
        }
    }

    /**
     * @throws ilTemplateException
     */
    protected function wrapInFormContext(
        FormInput $component,
        string $input_html,
        string $id_pointing_to_input = '',
        string $dependant_group_html = '',
        bool $bind_label_with_for = true
    ): string {
        $tpl = new ilTemplate("src/UI/templates/default/Input/tpl.context_form.html", true, true);

        $tpl->setVariable("INPUT", $input_html);

        if ($id_pointing_to_input && $bind_label_with_for) {
            $tpl->setCurrentBlock('for');
            $tpl->setVariable("ID", $id_pointing_to_input);
            $tpl->parseCurrentBlock();
        }

        $label = $component->getLabel();
        $tpl->setVariable("LABEL", $label);

        $byline = $component->getByline();
        if ($byline) {
            $tpl->setVariable("BYLINE", $byline);
        }

        $required = $component->isRequired();
        if ($required) {
            $tpl->touchBlock("required");
        }

        $error = $component->getError();
        if ($error) {
            $tpl->setVariable("ERROR", $error);
            $tpl->setVariable("ERROR_FOR_ID", $id_pointing_to_input);
        }

        $tpl->setVariable("DEPENDANT_GROUP", $dependant_group_html);
        return $tpl->get();
    }

    protected function maybeDisable(FormInput $component, $tpl): void
    {
        if ($component->isDisabled()) {
            $tpl->setVariable("DISABLED", 'disabled="disabled"');
        }
    }

    protected function applyName(FormInput $component, $tpl): ?string
    {
        $name = $component->getName();
        $tpl->setVariable("NAME", $name);
        return $name;
    }

    protected function bindJSandApplyId(FormInput $component, $tpl): string
    {
        $id = $this->bindJavaScript($component) ?? $this->createId();
        $tpl->setVariable("ID", $id);
        return $id;
    }

    protected function applyValue(FormInput $component, $tpl, callable $escape = null): void
    {
        $value = $component->getValue();
        if (!is_null($escape)) {
            $value = $escape($value);
        }
        if (isset($value) && $value != '') {
            $tpl->setVariable("VALUE", $value);
        }
    }

    private function getTemplateCustom(string $name): ilTemplate
    {
        return new ilTemplate("Customizing/global/plugins/Services/Cron/CronHook/SurReset/templates/Component/Input/Field/$name", true, true);
    }

    /**
     * @throws ilTemplateException
     */
    private function renderObjectSelector(ObjectSelector $component): string
    {
        $obj_sel_tpl = $this->getTemplateCustom("tpl.objectSelector.html");

        $id_obj_selector = "obj_selector_" . self::$lastObjSelectorId++;

        $obj_sel_tpl->setVariable("ID_OBJ_SELECTOR", $id_obj_selector);
        $obj_sel_tpl->setVariable("TXT_SELECT", $this->txt("select"));
        $obj_sel_tpl->setVariable("TXT_RESET", $this->txt("reset"));

        $id = $this->bindJSandApplyId($component, $obj_sel_tpl);
        $this->applyName($component, $obj_sel_tpl);
        $obj_sel_tpl->setVariable("VALUE", json_encode($component->getValue() ?? []));
        $this->maybeDisable($component, $obj_sel_tpl);

        $plugin = ilSurResetPlugin::getInstance();

        $modal = $this->getUIFactory()->modal()->lightbox($this->getUIFactory()->modal()->lightboxTextPage($this->buildObjects($component->getTree()), $plugin->txt("select_objects")));
        $modal_rendered = $this->render($modal);

        $obj_sel_tpl->setVariable("MODAL", $modal_rendered);
        $obj_sel_tpl->setVariable("MODAL_SIGNAL", $modal->getShowSignal());

        return $this->wrapInFormContext($component, $obj_sel_tpl->get(), $id);
    }

    private function buildObjects(array $tree): string
    {
        global $DIC;

        $factory = $DIC->ui()->factory();

        $renderer = $DIC->ui()->renderer();

        $recursion = new class () implements TreeRecursion {
            public function getChildren($record, $environment = null): array
            {
                return $record['children'] ?? [];
            }

            public function build(Factory $factory, $record, $environment = null): Node {
                return $factory->simple($record["title"], $record["icon"])->withAdditionalOnLoadCode(function ($id) use ($record) {
                    $modify = "$('#$id').attr('data-id', '" . $record["ref_id"] . "')";

                    if ($record["selectable"]) {
                        $modify .= ".addClass('ilSurResetTreeNode')";
                    }

                    return $modify . ";";
                });
            }
        };

        return $renderer->render($factory->tree()->expandable('', $recursion)->withData($tree));
    }

    /**
     * @throws ilTemplateException
     */
    private function renderMultipleSelector(MultipleSelector $component): string
    {
        $multiple_sel_tpl = $this->getTemplateCustom("tpl.multipleSelector.html");

        $id_multiple_selector = "multiple_selector_" . self::$lastMultipleSelectorId++;

        $multiple_sel_tpl->setVariable("ID_MULTIPLE_SELECTOR", $id_multiple_selector);
        $multiple_sel_tpl->setVariable("TXT_SELECT", $this->txt("select"));
        $multiple_sel_tpl->setVariable("TXT_RESET", $this->txt("reset"));

        $id = $this->bindJSandApplyId($component, $multiple_sel_tpl);
        $this->applyName($component, $multiple_sel_tpl);
        $multiple_sel_tpl->setVariable("VALUE", json_encode($component->getValue() ?? []));
        $this->maybeDisable($component, $multiple_sel_tpl);

        $plugin = ilSurResetPlugin::getInstance();

        $modal = $this->getUIFactory()->modal()->lightbox($this->getUIFactory()->modal()->lightboxTextPage($this->buildMultipleCheckbox($id_multiple_selector, $component->getOption()), $plugin->txt("select")));
        $modal_rendered = $this->render($modal);

        $multiple_sel_tpl->setVariable("MODAL", $modal_rendered);
        $multiple_sel_tpl->setVariable("MODAL_SIGNAL", $modal->getShowSignal());

        return $this->wrapInFormContext($component, $multiple_sel_tpl->get(), $id);
    }

    private function buildMultipleCheckbox(string $multiple_id, array $options): string
    {
        $checkboxes = "";

        foreach ($options as $option) {
            $id_attr = "checkbox_{$multiple_id}_{$option['id']}";
            $checkboxes .= '
            <div class="checkbox-wrapper">
                <input type="checkbox"
                       class="multiple-node"
                       id="' . $id_attr . '"
                       multipleSelector-id="' . $multiple_id . '"
                       node-id="' . $option['id'] . '"
                       node-title="' . $option['title'] . '">
                <label for="' . $id_attr . '">' . $option['title'] . '</label>
            </div>
        ';
        }

        return $checkboxes;
    }
}