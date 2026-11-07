<?php

declare(strict_types=1);

namespace Masilia\Tests\EditorialToolbox;

use Ibexa\AutomatedTranslation\ClientProvider;
use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\LanguageService;
use Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo;
use Ibexa\Contracts\Core\Repository\Values\Content\Language;
use Ibexa\Contracts\Core\Repository\Values\Content\VersionInfo;
use Ibexa\Core\MVC\Symfony\Locale\LocaleConverterInterface;
use Ibexa\Core\Repository\Values\Content\VersionInfo as CoreVersionInfo;
use Masilia\Contracts\EditorialToolbox\Translation\TranslationProvider;
use Masilia\Contracts\EditorialToolbox\Translation\TranslationProviderInterface;
use Masilia\Contracts\EditorialToolbox\Translation\TranslationRequest;
use Masilia\EditorialToolbox\Translation\AutomatedTranslationProvider;
use Masilia\EditorialToolbox\Translation\TranslationOptionsService;
use PHPUnit\Framework\TestCase;

final class TranslationTest extends TestCase
{
    /**
     * @dataProvider invalidRequests
     *
     * @param array<string, mixed> $data
     */
    public function testARequestMustBeComplete(array $data): void
    {
        $this->expectException(\InvalidArgumentException::class);

        TranslationRequest::fromArray($data);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidRequests(): iterable
    {
        yield 'unknown mode' => [['mode' => 'magic']];
        yield 'no mode' => [[]];
        yield 'provider mode without a provider' => [['mode' => 'provider', 'from' => 'eng-GB']];
    }

    public function testARequestIsReadFromTheJsonBody(): void
    {
        $request = TranslationRequest::fromArray(['mode' => 'provider', 'from' => 'eng-GB', 'provider' => 'deepl']);

        self::assertSame('provider', $request->mode);
        self::assertSame('eng-GB', $request->fromLanguage);
        self::assertSame('deepl', $request->provider);

        $blank = TranslationRequest::fromArray(['mode' => 'blank', 'from' => '']);
        self::assertNull($blank->fromLanguage);
    }

    public function testTheOptionsListTheLanguagesToCopyFromAndTheProvidersForThePair(): void
    {
        $contentService = $this->createMock(ContentService::class);
        $contentService->method('loadContentInfo')->willReturn(new ContentInfo(['id' => 239, 'mainLanguageCode' => 'eng-GB']));
        $contentService->method('loadVersionInfo')->willReturn(new CoreVersionInfo(['languageCodes' => ['eng-GB'], 'status' => VersionInfo::STATUS_PUBLISHED]));

        $languages = $this->createMock(LanguageService::class);
        $languages->method('loadLanguage')->willReturnCallback(static fn (string $code): Language => new Language([
            'languageCode' => $code,
            'name' => ['eng-GB' => 'English (United Kingdom)', 'fre-FR' => 'French (France)'][$code],
        ]));

        $provider = $this->createMock(TranslationProviderInterface::class);
        $provider->expects(self::once())->method('providers')->with('eng-GB', 'fre-FR')->willReturn([new TranslationProvider('deepl', 'DeepL')]);

        $options = (new TranslationOptionsService($contentService, $languages, $provider))->options(239, 'fre-FR')->toArray();

        self::assertSame([
            'target' => ['code' => 'fre-FR', 'name' => 'French (France)'],
            'available_languages' => [['code' => 'eng-GB', 'name' => 'English (United Kingdom)']],
            'default_source' => 'eng-GB',
            'providers' => [['id' => 'deepl', 'name' => 'DeepL']],
        ], $options);
    }

    public function testWithoutIbexaAutomatedTranslationNoProviderIsOffered(): void
    {
        $adapter = new AutomatedTranslationProvider();

        self::assertSame([], $adapter->providers('eng-GB', 'fre-FR'));

        $this->expectException(\LogicException::class);
        $adapter->translateFields($this->createMock(\Ibexa\Contracts\Core\Repository\Values\Content\Content::class), 'eng-GB', 'fre-FR', 'deepl');
    }

    public function testOnlyConfiguredProvidersThatSupportBothLanguagesAreOffered(): void
    {
        if (!interface_exists(\Ibexa\Contracts\AutomatedTranslation\Client\ClientInterface::class)) {
            self::markTestSkipped('ibexa/automated-translation is not installed.');
        }

        $supportsBoth = $this->client('DeepL', ['en_GB', 'fr_FR']);
        $onlyEnglish = $this->client('Google', ['en_GB']);

        $clients = $this->createMock(ClientProvider::class);
        $clients->method('getClients')->willReturn(['deepl' => $supportsBoth, 'google' => $onlyEnglish]);

        $converter = $this->createMock(LocaleConverterInterface::class);
        $converter->method('convertToPOSIX')->willReturnCallback(static fn (string $code): ?string => ['eng-GB' => 'en_GB', 'fre-FR' => 'fr_FR'][$code] ?? null);

        $adapter = new AutomatedTranslationProvider($clients, null, $converter);

        self::assertEquals([new TranslationProvider('deepl', 'DeepL')], $adapter->providers('eng-GB', 'fre-FR'));
        self::assertSame([], $adapter->providers('eng-GB', 'xx-XX'), 'a language Ibexa cannot convert offers nothing');
    }

    /**
     * @param string[] $supported
     */
    private function client(string $name, array $supported): \Ibexa\Contracts\AutomatedTranslation\Client\ClientInterface
    {
        $client = $this->createMock(\Ibexa\Contracts\AutomatedTranslation\Client\ClientInterface::class);
        $client->method('getServiceFullName')->willReturn($name);
        $client->method('supportsLanguage')->willReturnCallback(static fn (string $code): bool => \in_array($code, $supported, true));

        return $client;
    }
}
