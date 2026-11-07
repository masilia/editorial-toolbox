<?php

declare(strict_types=1);

namespace Masilia\Tests\Bundle\EditorialToolbox;

use Masilia\Bundle\EditorialToolbox\Templating\ToolboxExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class ToolboxExtensionTest extends TestCase
{
    public function testRendersTheMountPointWithUrlTemplatesInsteadOfConcreteIds(): void
    {
        // Same shape as the real routes (prefix /admin/api); the extension must turn its numeric sentinels into placeholders.
        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(static fn (string $route, array $params = []): string => match ($route) {
            'masilia_editorial_toolbox.csrf' => '/admin/api/csrf-token',
            'masilia_editorial_toolbox.draft.edit' => sprintf('/admin/api/in-context/edit/%d/%s/%d', $params['contentId'], rawurlencode($params['languageCode']), $params['locationId']),
            'masilia_editorial_toolbox.draft.discard' => sprintf('/admin/api/in-context/discard/%d/%s', $params['contentId'], rawurlencode($params['languageCode'])),
            'masilia_editorial_toolbox.blocks.list' => sprintf('/admin/api/page/%d/blocks', $params['locationId']),
            'masilia_editorial_toolbox.blocks.relation' => sprintf('/admin/api/page/%d/blocks/relation', $params['locationId']),
            'masilia_editorial_toolbox.blocks.reorder' => sprintf('/admin/api/page/%d/blocks/order', $params['locationId']),
            'masilia_editorial_toolbox.blocks.remove' => sprintf('/admin/api/page/%d/blocks/%d', $params['locationId'], $params['blockContentId']),
        });

        $twig = new Environment(new ArrayLoader([
            '@MasiliaEditorialToolbox/toolbox.html.twig' => '{{ config|json_encode|raw }}',
        ]));

        $json = (new ToolboxExtension($urls, $this->createMock(TranslatorInterface::class)))->render($twig);
        $config = json_decode($json, true);

        self::assertSame([
            'csrf' => '/admin/api/csrf-token',
            'draftEdit' => '/admin/api/in-context/edit/{contentId}/{languageCode}/{locationId}',
            'draftDiscard' => '/admin/api/in-context/discard/{contentId}/{languageCode}',
            'blocks' => '/admin/api/page/{locationId}/blocks',
            'blocksRelation' => '/admin/api/page/{locationId}/blocks/relation',
            'blocksOrder' => '/admin/api/page/{locationId}/blocks/order',
            'block' => '/admin/api/page/{locationId}/blocks/{blockContentId}',
        ], $config['endpoints']);
    }

    public function testAttributesPrintNothingWithoutMeta(): void
    {
        $extension = $this->extension();

        self::assertSame('', $extension->attributes([]));
        self::assertSame('', $extension->attributes(['admin_meta' => null]));
        self::assertSame('', $extension->attributes(['admin_meta' => []]));
    }

    public function testAttributesEscapeTheJsonForADoubleQuotedAttribute(): void
    {
        $meta = ['content_id' => 64, 'content_type' => 'Bloc "Héro" <b> & co', 'dashboard_url' => 'http://admin.example/dashboard'];

        $attribute = $this->extension()->attributes(['admin_meta' => $meta]);

        self::assertStringStartsWith(' data-admin="', $attribute);
        self::assertStringEndsWith('"', $attribute);
        // Nothing that could close the attribute or open a tag survives.
        $value = substr($attribute, \strlen(' data-admin="'), -1);
        self::assertStringNotContainsString('"', $value);
        self::assertStringNotContainsString('<', $value);
        // ...and a browser decoding the attribute gets the original JSON back.
        self::assertSame($meta, json_decode(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), true));
    }

    public function testAnExplicitMetaWinsOverTheContext(): void
    {
        $attribute = $this->extension()->attributes(['admin_meta' => ['content_id' => 1]], ['content_id' => 2]);

        self::assertStringContainsString('content_id&quot;:2', $attribute);
    }

    private function extension(): ToolboxExtension
    {
        return new ToolboxExtension($this->createMock(UrlGeneratorInterface::class), $this->createMock(TranslatorInterface::class));
    }
}
