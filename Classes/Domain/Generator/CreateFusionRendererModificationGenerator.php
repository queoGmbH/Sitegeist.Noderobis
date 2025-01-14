<?php

declare(strict_types=1);

/*
 * This file is part of the Sitegeist.Noderobis package.
 */

namespace Sitegeist\Noderobis\Domain\Generator;

use Neos\Flow\Annotations as Flow;
use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\Flow\Package\FlowPackageInterface;
use Sitegeist\Noderobis\Domain\Modification\WriteFileModification;
use Sitegeist\Noderobis\Domain\Modification\DoNothingModification;
use Sitegeist\Noderobis\Domain\Modification\ModificationInterface;
use Sitegeist\Noderobis\Domain\Specification\NodeTypeNameSpecification;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;

class CreateFusionRendererModificationGenerator implements ModificationGeneratorInterface
{
    #[Flow\Inject]
    protected NodeTypeManager $nodeTypeManager;

    /** @var array<string, array{afx:string, prop:string}> */
    #[Flow\InjectConfiguration("properties")]
    protected array $propertyRendererConfiguration;

    public function generateModification(FlowPackageInterface $package, NodeType $nodeType): ModificationInterface
    {
        if ($nodeType->isOfType('Neos.Neos:Shortcut')) {
            $fusionCode = null;
        } elseif ($nodeType->isOfType('Neos.Neos:Document')) {
            $fusionCode = $this->createDocumentFusionPrototype($package, $nodeType);
        } elseif ($nodeType->isOfType('Neos.Neos:Content')) {
            $fusionCode = $this->createContentFusionPrototype($package, $nodeType);
        } else {
            $fusionCode = null;
        }

        if ($fusionCode) {
            $nodeTypeNameSpecification = NodeTypeNameSpecification::fromString($nodeType->getName());
            $filePath = $package->getPackagePath() . 'NodeTypes/' . implode('/', $nodeTypeNameSpecification->getLocalNameParts()) . '.fusion';
            return new WriteFileModification($filePath, $fusionCode);
        } else {
            return new DoNothingModification();
        }
    }

    protected function createDocumentFusionPrototype(FlowPackageInterface $package, NodeType $nodeType): string
    {
        $name = $nodeType->getName();
        $packagePath = $package->getPackagePath();

        $propertyAcessors = $this->generatePropertyAccessors($nodeType);
        $propertyRenderers = $this->generatePropertyRenderers($nodeType);
        $childNodeRenderer = $this->generateChildrenAfxRenderer($nodeType);

        $fusionCode = <<<EOT
            #
            # Renderer for NodeType {$name}
            #
            # @see https://docs.neos.io/cms/manual/rendering
            #
            prototype({$name}) < prototype(Neos.Neos:Page) {

                body = Neos.Fusion:Component {

                    {$this->indent($propertyAcessors, 8)}

                    renderer = afx`
                        <div>
                            <h1>Autogenerated renderer for NodeType: "{$name}" "{props.title}" </h1>

                            {$this->indent($propertyRenderers, 16)}

                            {$this->indent($childNodeRenderer, 16)}
                        </div>
                    `
                }
            }
            EOT;
        return $fusionCode;
    }

    protected function createContentFusionPrototype(FlowPackageInterface $package, NodeType $nodeType): string
    {
        $name = $nodeType->getName();

        $propertyAcessors = $this->generatePropertyAccessors($nodeType);
        $propertyRenderers = $this->generatePropertyRenderers($nodeType);
        $childNodeRenderer = $this->generateChildrenAfxRenderer($nodeType);

        $fusionCode = <<<EOT
            #
            # Renderer for NodeType {$name}
            #
            # @see https://docs.neos.io/cms/manual/rendering
            #
            prototype({$name}) < prototype(Neos.Neos:ContentComponent) {

                {$this->indent($propertyAcessors, 4)}

                renderer = afx`
                    <div>
                        <p>
                            Autogenerated renderer for NodeType: {$name}
                        </p>

                        {$this->indent($propertyRenderers, 12)}

                        {$this->indent($childNodeRenderer, 12)}
                    </div>
                `
            }
            EOT;
        return $fusionCode;
    }

    protected function generatePropertyRenderers(NodeType $nodeType): string
    {
        $propertyRenderers = [];
        foreach ($nodeType->getProperties() as $name => $propertyConfiguration) {
            $name = (string) $name;
            if (str_starts_with($name, '_')) {
                continue;
            }

            if ($propertyConfiguration['ui']['inlineEditable'] ?? false) {
                $afx = $this->propertyRendererConfiguration['inlineEditable']['afx'] ?? $this->propertyRendererConfiguration['default']['afx'];
            } else {
                $afx = $this->propertyRendererConfiguration[$propertyConfiguration['type']]['afx'] ?? $this->propertyRendererConfiguration['default']['afx'];
            }
            $propertyRenderers[] = '<dt>' . $name . '</dt><dd>' . str_replace('###NAME###', $name, $afx) . '</dd>';
        }

        if ($propertyRenderers) {
            return 'Properties:' . PHP_EOL . '<dl>' . $this->indent(PHP_EOL . implode(PHP_EOL, $propertyRenderers)) . PHP_EOL . '</dl>';
        } else {
            return '';
        }
    }

    protected function generatePropertyAccessors(NodeType $nodeType): string
    {
        $propertyAcessorList = [];

        foreach ($nodeType->getProperties() as $name => $propertyConfiguration) {
            $name = (string) $name;
            if (str_starts_with($name, '_')) {
                continue;
            }

            if ($propertyConfiguration['ui']['inlineEditable'] ?? false) {
                $prop = $this->propertyRendererConfiguration['inlineEditable']['prop'] ?? $this->propertyRendererConfiguration['default']['prop'];
            } else {
                $prop = $this->propertyRendererConfiguration[$propertyConfiguration['type']]['prop'] ?? $this->propertyRendererConfiguration['default']['prop'];
            }
            $propertyAcessorList[] =  str_replace('###NAME###', $name, $prop);
        }

        if ($propertyAcessorList) {
            return implode(PHP_EOL, $propertyAcessorList);
        } else {
            return '';
        }
    }

    protected function generateChildrenAfxRenderer(NodeType $nodeType): string
    {
        $childNodeRenderers = [];
        foreach ($nodeType->getAutoCreatedChildNodes() as $name => $childNodeType) {
            if ($childNodeType->isOfType('Neos.Neos:Document')) {
                $renderer = '<Neos.Neos:NodeLink node={q(node).children(' . $name . ')} >' . $name . '</Neos.Neos:NodeLink>';
            } elseif ($childNodeType->isOfType('Neos.Neos:ContentCollection')) {
                $renderer = '<Neos.Neos:ContentCollection nodePath="' . $name . '" />';
            } elseif ($childNodeType->isOfType('Neos.Neos:Content')) {
                $renderer = '<Neos.Neos:ContentCase @context.node={q(node).children(' . $name . ')} />';
            } else {
                $renderer = '<!-- no clue how to render node of type ' . $childNodeType->getName() . ' -->';
            }

            $childNodeRenderers[] = '<dt>' . $name . '</dt><dd>' . $renderer . '</dd>';
        }
        if (count($childNodeRenderers) > 0) {
            return 'ChildNodes:' . PHP_EOL . '<dl>' . $this->indent(PHP_EOL . implode(PHP_EOL, $childNodeRenderers)) . PHP_EOL . '</dl>';
        }
        return '';
    }

    protected function indent(string $text, int $numSpaces = 4): string
    {
        $padding = '';
        for ($i = 0; $i < $numSpaces; $i++) {
            $padding .= ' ';
        }
        return implode(PHP_EOL . $padding, explode(PHP_EOL, $text));
    }
}
