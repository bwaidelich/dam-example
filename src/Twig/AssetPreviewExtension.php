<?php
declare(strict_types=1);

namespace Wwwision\DamExample\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Wwwision\DAM\Model\Asset;
use Wwwision\DAM\Model\AssetType;
use function get_debug_type;
use function htmlentities;

final class AssetPreviewExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('asset_preview', [$this, 'assetPreview'], ['is_safe' => ['html']]),
        ];
    }

    public function assetPreview($asset): string
    {
        if (!$asset instanceof Asset) {
            return '';
        }
        if ($asset->type === AssetType::Image) {
            return '<img src="/thumbnails/' . urlencode($asset->resourcePointer->value) . '" alt="' . htmlentities($asset->caption->value) . '" />';
        }
        return '<img src="https://placehold.co/285x150?text=' . urlencode($asset->type->name) . '" alt="' . htmlentities($asset->caption->value) . '" />';
    }
}
