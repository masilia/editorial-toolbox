<?php

declare(strict_types=1);

namespace Masilia\Contracts\EditorialToolbox\Meta;

use Ibexa\Contracts\Core\Repository\Values\Content\Content;

/**
 * Builds the "admin meta" payload that templates print as a data-admin attribute and the toolbar reads.
 */
interface AdminMetaBuilderInterface
{
    /**
     * @return array<string, mixed>
     */
    public function build(Content $content): array;
}
