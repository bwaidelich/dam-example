<?php

declare(strict_types=1);

namespace Wwwision\DamExample\ContentRepository;

use Closure;
use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\Projection\CatchUpOptions;
use Neos\ContentRepository\Core\Projection\ProjectionCatchUpTriggerInterface;
use Neos\ContentRepository\Core\Projection\Projections;
use function get_class;

final readonly class SynchronousProjectionCatchUpTrigger implements ProjectionCatchUpTriggerInterface
{


    /**
     * @param Closure(): ContentRepository $contentRepositoryResolver
     */
    public function __construct(
        private Closure $contentRepositoryResolver,
    ) {
    }

    public function triggerCatchUp(Projections $projections): void
    {
        $contentRepository = ($this->contentRepositoryResolver)();
        assert($contentRepository instanceof ContentRepository);
        foreach ($projections as $projection) {
            $projectionClassName = get_class($projection);
            $contentRepository->catchUpProjection($projectionClassName, CatchUpOptions::create());
        }
    }
}