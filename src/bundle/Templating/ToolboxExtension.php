<?php

declare(strict_types=1);

namespace Masilia\Bundle\EditorialToolbox\Templating;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * `{{ masilia_editorial_toolbox() }}` renders the mount point of the toolbar, carrying everything the
 * JavaScript needs (API URL templates, labels) so the front-end has no hardcoded URLs.
 */
class ToolboxExtension extends AbstractExtension
{
    // Numeric sentinels (routes require \d+) swapped for {placeholders} in the generated URLs.
    private const LOCATION = 987654301;
    private const CONTENT = 987654302;
    private const BLOCK = 987654303;

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('masilia_editorial_toolbox', [$this, 'render'], ['needs_environment' => true, 'is_safe' => ['html']]),
            new TwigFunction('masilia_editorial_attrs', [$this, 'attributes'], ['needs_context' => true, 'is_safe' => ['html']]),
        ];
    }

    /**
     * `<section{{ masilia_editorial_attrs() }} class="...">` marks an element as editable by the toolbar.
     *
     * Prints ` data-admin="{...}"` from the `admin_meta` template variable (set for users who may edit, see
     * AdminMetaViewListener) or from the array given, and nothing for everybody else. The JSON is HTML-escaped,
     * so it is safe in a double-quoted attribute; the toolbar reads it back with `JSON.parse(el.dataset.admin)`.
     *
     * @param array<string, mixed>      $context
     * @param array<string, mixed>|null $meta
     */
    public function attributes(array $context, ?array $meta = null): string
    {
        $meta ??= $context['admin_meta'] ?? null;

        if (!\is_array($meta) || $meta === []) {
            return '';
        }

        try {
            $json = json_encode($meta, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (\JsonException) {
            return '';
        }

        return ' data-admin="' . htmlspecialchars($json, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
    }

    public function render(Environment $twig): string
    {
        return $twig->render('@MasiliaEditorialToolbox/toolbox.html.twig', [
            'config' => ['endpoints' => $this->endpoints()],
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function endpoints(): array
    {
        $generate = fn (string $route, array $params = []): string => str_replace(
            [(string) self::CONTENT, (string) self::LOCATION, (string) self::BLOCK],
            ['{contentId}', '{locationId}', '{blockContentId}'],
            urldecode($this->urlGenerator->generate($route, $params))
        );

        $content = ['contentId' => self::CONTENT, 'languageCode' => '{languageCode}'];
        $location = ['locationId' => self::LOCATION];
        $block = $location + ['blockContentId' => self::BLOCK];

        return [
            'csrf' => $generate('masilia_editorial_toolbox.csrf'),
            'draftEdit' => $generate('masilia_editorial_toolbox.draft.edit', $content + $location),
            'draftDiscard' => $generate('masilia_editorial_toolbox.draft.discard', $content),
            'translationOptions' => $generate('masilia_editorial_toolbox.translation.options', $content),
            'blocks' => $generate('masilia_editorial_toolbox.blocks.list', $location),
            'blocksRelation' => $generate('masilia_editorial_toolbox.blocks.relation', $location),
            'blocksOrder' => $generate('masilia_editorial_toolbox.blocks.reorder', $location),
            'block' => $generate('masilia_editorial_toolbox.blocks.remove', $block),
        ];
    }
}
