<?php

declare(strict_types=1);

namespace Masilia\EditorialToolbox\Translation;

use Ibexa\AutomatedTranslation\ClientProvider;
use Ibexa\AutomatedTranslation\Translator;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Core\MVC\Symfony\Locale\LocaleConverterInterface;
use Masilia\Contracts\EditorialToolbox\Translation\TranslationProvider;
use Masilia\Contracts\EditorialToolbox\Translation\TranslationProviderInterface;

/**
 * Adapter for ibexa/automated-translation. Its services are optional (the package is not installed everywhere): without them
 * no provider is offered. Only providers that are configured and support both languages are listed, as in Ibexa's own form.
 */
class AutomatedTranslationProvider implements TranslationProviderInterface
{
    public function __construct(
        private readonly ?ClientProvider $clients = null,
        private readonly ?Translator $translator = null,
        private readonly ?LocaleConverterInterface $localeConverter = null,
    ) {
    }

    public function providers(string $fromLanguage, string $toLanguage): array
    {
        if ($this->clients === null || $this->localeConverter === null) {
            return [];
        }

        $from = $this->localeConverter->convertToPOSIX($fromLanguage);
        $to = $this->localeConverter->convertToPOSIX($toLanguage);
        if ($from === null || $to === null) {
            return [];
        }

        $providers = [];
        foreach ($this->clients->getClients() as $key => $client) {
            if ($client->supportsLanguage($from) && $client->supportsLanguage($to)) {
                $providers[] = new TranslationProvider((string) $key, $client->getServiceFullName());
            }
        }

        return $providers;
    }

    public function translateFields(Content $source, string $fromLanguage, string $toLanguage, string $providerId): array
    {
        if ($this->translator === null) {
            throw new \LogicException('Ibexa automated translation is not installed.');
        }

        return $this->translator->getTranslatedFields($fromLanguage, $toLanguage, $providerId, $source);
    }
}
