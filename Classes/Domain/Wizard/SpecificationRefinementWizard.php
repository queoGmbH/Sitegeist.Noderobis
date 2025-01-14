<?php

/*
 * This file is part of the Sitegeist.Noderobis package.
 */

declare(strict_types=1);

namespace Sitegeist\Noderobis\Domain\Wizard;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\ConsoleOutput;
use Neos\Flow\Cli\Exception\StopCommandException;
use Sitegeist\Noderobis\Domain\Specification\IconNameSpecification;
use Sitegeist\Noderobis\Domain\Specification\NodeTypeLabelSpecification;
use Sitegeist\Noderobis\Domain\Specification\NodeTypeNameSpecification;
use Sitegeist\Noderobis\Domain\Specification\NodeTypeNameSpecificationFactory;
use Sitegeist\Noderobis\Domain\Specification\NodeTypeSpecification;
use Sitegeist\Noderobis\Domain\Specification\PropertySpecificationFactory;
use Sitegeist\Noderobis\Domain\Specification\TetheredNodeSpecificationFactory;

class SpecificationRefinementWizard
{
    #[Flow\Inject]
    protected PropertySpecificationFactory $propertySpecificationFactory;

    #[Flow\Inject]
    protected TetheredNodeSpecificationFactory $tetheredNodeSpecificationFactory;

    #[Flow\Inject]
    protected NodeTypeNameSpecificationFactory $nodeTypeNameSpecificationFactory;

    public function __construct(
        private readonly ConsoleOutput $output
    ) {
    }

    public function refineSpecification(NodeTypeSpecification $nodeTypeSpecification): NodeTypeSpecification
    {
        $this->output->outputLine();
        $this->output->outputLine((string)$nodeTypeSpecification);
        $this->output->outputLine();

        $choices = [
            "add Label",
            "add Icon",
            "add Property",
            "add ChildNode",
            "add SuperType",
            "add Mixin"
        ];

        if ($nodeTypeSpecification->abstract) {
            $choices[] = 'make Non-Abstract';
        } else {
            $choices[] = 'make Abstract';
        }

        /**
         * @var string
         */
        $choice = $this->output->select(
            "What is next?",
            [
                "FINISH and generate files",
                ...$choices,
                "exit"
            ],
            "generate NodeType"
        );

        switch ($choice) {
            case "FINISH and generate files":
                return $nodeTypeSpecification;
            case "add Label":
                $nodeTypeSpecification = $this->addLabelToNodeTypeSpecification($nodeTypeSpecification);
                return $this->refineSpecification($nodeTypeSpecification);
            case "add Icon":
                $nodeTypeSpecification = $this->addIconToNodeTypeSpecification($nodeTypeSpecification);
                return $this->refineSpecification($nodeTypeSpecification);
            case "add Property":
                $nodeTypeSpecification = $this->addPropertyToNodeTypeSpecification($nodeTypeSpecification);
                return $this->refineSpecification($nodeTypeSpecification);
            case "add ChildNode":
                $nodeTypeSpecification = $this->addTetheredNodeToNodeTypeSpecification($nodeTypeSpecification);
                return $this->refineSpecification($nodeTypeSpecification);
            case "add SuperType":
                $nodeTypeSpecification = $this->addSuperTypeToNodeTypeSpecification($nodeTypeSpecification);
                return $this->refineSpecification($nodeTypeSpecification);
            case "add Mixin":
                $nodeTypeSpecification = $this->addMixinToNodeTypeSpecification($nodeTypeSpecification);
                return $this->refineSpecification($nodeTypeSpecification);
            case "make Abstract":
                $nodeTypeSpecification = $nodeTypeSpecification->withAbstract(true);
                return $this->refineSpecification($nodeTypeSpecification);
            case "make Non-Abstract":
                $nodeTypeSpecification = $nodeTypeSpecification->withAbstract(false);
                return $this->refineSpecification($nodeTypeSpecification);
            case "exit":
                throw new StopCommandException();
            default:
                throw new \InvalidArgumentException(sprintf("Unkonwn option %s", $choice));
        }
    }

    protected function addLabelToNodeTypeSpecification(NodeTypeSpecification $nodeTypeSpecification): NodeTypeSpecification
    {
        $text = $this->output->ask("Label: ");
        if (is_string($text)) {
            $label = new NodeTypeLabelSpecification((string)$text);
            return $nodeTypeSpecification->withLabel($label);
        } else {
            return $nodeTypeSpecification;
        }
    }

    protected function addIconToNodeTypeSpecification(NodeTypeSpecification $nodeTypeSpecification): NodeTypeSpecification
    {
        $name = $this->output->ask("Icob: ");
        if (is_string($name)) {
            $icon = new IconNameSpecification($name);
            return $nodeTypeSpecification->withIcon($icon);
        } else {
            return $nodeTypeSpecification;
        }
    }

    protected function addSuperTypeToNodeTypeSpecification(NodeTypeSpecification $nodeTypeSpecification): NodeTypeSpecification
    {
        $name = $this->output->select("SuperType: ", $this->nodeTypeNameSpecificationFactory->getExistingNodeTypeNames());
        if (is_string($name)) {
            $nodeTypeName = NodeTypeNameSpecification::fromString($name);
            return $nodeTypeSpecification->withSuperType($nodeTypeName);
        } else {
            return $nodeTypeSpecification;
        }
    }

    protected function addMixinToNodeTypeSpecification(NodeTypeSpecification $nodeTypeSpecification): NodeTypeSpecification
    {
        $name = $this->output->select("Mixin: ", $this->nodeTypeNameSpecificationFactory->getExistingMixinNodeTypeNames());
        if (is_string($name)) {
            $nodeTypeName = NodeTypeNameSpecification::fromString($name);
            return $nodeTypeSpecification->withSuperType($nodeTypeName);
        } else {
            return $nodeTypeSpecification;
        }
    }

    protected function addTetheredNodeToNodeTypeSpecification(NodeTypeSpecification $nodeTypeSpecification): NodeTypeSpecification
    {
        $name = $this->output->ask("ChildNode name: ");
        $type = $this->output->select("ChildNode type: ", $this->tetheredNodeSpecificationFactory->getTypeConfiguration());

        if (!is_string($name) || !is_string($type)) {
            return $nodeTypeSpecification;
        }

        $tetheredNode = $this->tetheredNodeSpecificationFactory->generateTetheredNodeSpecificationFromCliInput(
            trim($name),
            $type
        );

        return $nodeTypeSpecification->withTeheredNode($tetheredNode);
    }

    protected function addPropertyToNodeTypeSpecification(NodeTypeSpecification $nodeTypeSpecification): NodeTypeSpecification
    {
        $name = $this->output->ask("Property name: ");
        $type = $this->output->select("Property type: ", $this->propertySpecificationFactory->getTypeConfiguration());

        if (!is_string($name) || !is_string($type)) {
            return $nodeTypeSpecification;
        }

        $propertySpecification = $this->propertySpecificationFactory->generatePropertySpecificationFromCliInput(
            trim($name),
            $type
        );

        return $nodeTypeSpecification->withProperty($propertySpecification);
    }
}
