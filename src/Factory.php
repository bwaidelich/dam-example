<?php

declare(strict_types=1);

namespace Wwwision\DamExample;

use Doctrine\DBAL\DriverManager;
use Neos\ContentRepository\Core\NodeType\NodeTypeManager;
use Wwwision\DAM\ContentRepository\AssetNodeTypes;
use Wwwision\DAM\DAM;
use Wwwision\DamExample\ContentRepository\ContentRepositoryBuilder;
use function getenv;

final readonly class Factory
{
    public static function build(): DAM
    {
        $dbDSN = getenv('DB_DSN');
        if (!$dbDSN) {
            throw new \RuntimeException('Missing DB_DSN environment variable');
        }
        $connection = DriverManager::getConnection(['url' => $dbDSN]);

        $contentRepository = ContentRepositoryBuilder::forConnection($connection)
            ->with(
                contentRepositoryId: 'dam',
                nodeTypeManager: new NodeTypeManager(AssetNodeTypes::getConfiguration(...)),
            )
            ->build();
        return new DAM($contentRepository);
    }
}