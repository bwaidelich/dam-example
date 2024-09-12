<?php
declare(strict_types=1);

namespace Wwwision\DamExample\ContentRepository;

use Doctrine\DBAL\Connection;
use Neos\ContentGraph\DoctrineDbalAdapter\DoctrineDbalContentGraphProjectionFactory;
use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\Dimension\ConfigurationBasedContentDimensionSource;
use Neos\ContentRepository\Core\Factory\ContentRepositoryFactory;
use Neos\ContentRepository\Core\Factory\ProjectionsAndCatchUpHooksFactory;
use Neos\ContentRepository\Core\Infrastructure\Property\Normalizer\ArrayNormalizer;
use Neos\ContentRepository\Core\Infrastructure\Property\Normalizer\CollectionTypeDenormalizer;
use Neos\ContentRepository\Core\Infrastructure\Property\Normalizer\ScalarNormalizer;
use Neos\ContentRepository\Core\Infrastructure\Property\Normalizer\UriNormalizer;
use Neos\ContentRepository\Core\Infrastructure\Property\Normalizer\ValueObjectArrayDenormalizer;
use Neos\ContentRepository\Core\Infrastructure\Property\Normalizer\ValueObjectBoolDenormalizer;
use Neos\ContentRepository\Core\Infrastructure\Property\Normalizer\ValueObjectFloatDenormalizer;
use Neos\ContentRepository\Core\Infrastructure\Property\Normalizer\ValueObjectIntDenormalizer;
use Neos\ContentRepository\Core\Infrastructure\Property\Normalizer\ValueObjectStringDenormalizer;
use Neos\ContentRepository\Core\NodeType\NodeTypeManager;
use Neos\ContentRepository\Core\Projection\ContentStream\ContentStreamProjectionFactory;
use Neos\ContentRepository\Core\Projection\Workspace\WorkspaceProjectionFactory;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\User\StaticUserIdProvider;
use Neos\ContentRepository\Core\SharedModel\User\UserId;
use Neos\EventStore\DoctrineAdapter\DoctrineEventStore;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Serializer\Serializer;
use function is_string;

final class ContentRepositoryBuilder
{
    /**
     * @var array<string, ContentRepository>
     */
    private static array $instances = [];

    private const DEFAULT_NORMALIZERS = [
        JsonSerializableNormalizer::class,
        DateTimeNormalizer::class,
        ScalarNormalizer::class,
        BackedEnumNormalizer::class,
        ArrayNormalizer::class,
        UriNormalizer::class,
        ValueObjectArrayDenormalizer::class,
        ValueObjectBoolDenormalizer::class,
        ValueObjectFloatDenormalizer::class,
        ValueObjectIntDenormalizer::class,
        ValueObjectStringDenormalizer::class,
        CollectionTypeDenormalizer::class,
    ];

    private function __construct(
        private readonly Connection $connection,
        private readonly ContentRepositoryId $contentRepositoryId,
        private readonly NodeTypeManager $nodeTypeManager,
    ) {
    }

    public static function forConnection(Connection $connection): self
    {
        return new self(
            connection: $connection,
            contentRepositoryId: ContentRepositoryId::fromString('default'),
            nodeTypeManager: new NodeTypeManager(fn() => []),
        );
    }

    /**
     * Modify one or more defaults.
     *
     * NOTE: The signature of this method is very likely to be extended, always use named arguments!
     */
    public function with(
        ContentRepositoryId|string $contentRepositoryId = null,
        NodeTypeManager $nodeTypeManager = null,
    ): self
    {
        if (is_string($contentRepositoryId)) {
            $contentRepositoryId = ContentRepositoryId::fromString($contentRepositoryId);
        }
        return new self(
            connection: $this->connection,
            contentRepositoryId: $contentRepositoryId ?? $this->contentRepositoryId,
            nodeTypeManager: $nodeTypeManager ?? $this->nodeTypeManager,
        );
    }

    public function build(): ContentRepository
    {
        $clock = new SystemClock();
        $eventStore = new DoctrineEventStore($this->connection, 'cr_' . $this->contentRepositoryId->value . '_events', $clock);

        $contentDimensionSource = new ConfigurationBasedContentDimensionSource([]);

        // TODO make normalizers configurable
        $normalizers = array_map(static fn (string $className) => new $className(), self::DEFAULT_NORMALIZERS);
        $propertySerializer = new Serializer($normalizers);

        $projectionsAndCatchUpHooksFactory = new ProjectionsAndCatchUpHooksFactory();
        $projectionsAndCatchUpHooksFactory->registerFactory(new DoctrineDbalContentGraphProjectionFactory($this->connection), []);
        $projectionsAndCatchUpHooksFactory->registerFactory(new ContentStreamProjectionFactory($this->connection), []);
        $projectionsAndCatchUpHooksFactory->registerFactory(new WorkspaceProjectionFactory($this->connection), []);

        // HACK: hen-egg-hack... ContentRepositoryFactory requires ProjectionCatchUpTrigger that requires ContentRepositoryFactory (will be fixed with https://github.com/neos/neos-development-collection/issues/4746)
        $projectionCatchUpTrigger = new SynchronousProjectionCatchUpTrigger(fn () => self::$instances[$this->contentRepositoryId->value]);

        $userIdProvider = new StaticUserIdProvider(UserId::fromString(get_current_user()));

        $contentRepository = (new ContentRepositoryFactory(
            $this->contentRepositoryId,
            $eventStore,
            $this->nodeTypeManager,
            $contentDimensionSource,
            $propertySerializer,
            $projectionsAndCatchUpHooksFactory,
            $projectionCatchUpTrigger,
            $userIdProvider,
            $clock
        ))->getOrBuild();
        self::$instances[$this->contentRepositoryId->value] = $contentRepository;
        return $contentRepository;
    }
}