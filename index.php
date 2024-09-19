<?php

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use Wwwision\DAM\Command\AddAsset;
use Wwwision\DAM\Command\AddFolder;
use Wwwision\DAM\Model\AssetId;
use Wwwision\DAM\Model\Filter\AssetFilter;
use Wwwision\DAM\Model\FolderId;
use Wwwision\DAM\Model\ResourcePointer;
use Wwwision\DamExample\Factory;

require __DIR__ . '/vendor/autoload.php';

$dam = Factory::build();

$app = AppFactory::create();
$twig = Twig::create(__DIR__ . '/templates', ['cache' => false]);
$app->add(TwigMiddleware::create($app, $twig));

$app->get('/', function (ServerRequestInterface $request, ResponseInterface $response) use ($dam) {
    $queryParams = $request->getQueryParams();
    $assetFilter = AssetFilter::create(
        assetType: $queryParams['type'] ?? null,
        tagId: $queryParams['tag'] ?? null,
        folderId: $queryParams['folder'] ?? null,
    );
    return Twig::fromRequest($request)->render($response, 'index.html.twig', [
        'assets' => $dam->findAssets($assetFilter),
        'folderTree' => $dam->getFolderTree(),
        'tags' => $dam->findTags(),
        'selectedFolderId' => $queryParams['folder'] ?? null,
    ]);
});

$app->get('/assets/{assetId}', function (ServerRequestInterface $request, ResponseInterface $response, array $args) use ($dam) {
    $asset = $dam->findAssetById(AssetId::fromString($args['assetId']));
    return Twig::fromRequest($request)->render($response, 'asset.html.twig', [
        'asset' => $asset,
    ]);
});

$app->post('/add-folder', function (ServerRequestInterface $request, ResponseInterface $response) use ($dam) {
    $newFolderId = FolderId::create();
    $dam->handle(AddFolder::create(
        $newFolderId,
        label: $request->getParsedBody()['label'],
        parentFolderId: $request->getParsedBody()['parentFolderId'] ?? null,
    ));
    return $response
        ->withHeader('Location', (string)$request->getUri()->withPath('/')->withQuery('folder=' . $newFolderId->value))
        ->withStatus(303);
});

$app->post('/add-asset', function (ServerRequestInterface $request, ResponseInterface $response) use ($dam){
    $file = $request->getUploadedFiles()['file'] ?? null;
    assert($file instanceof UploadedFileInterface);
    $label = $request->getParsedBody()['label'] ?? null;
    assert(is_string($label));
    $caption = $request->getParsedBody()['caption'] ?? null;
    assert(is_string($caption));

    $resourcePointer = ResourcePointer::fromString(sha1($file->getStream()->getContents()));

    $targetPath = __DIR__ . '/assets/' . $resourcePointer->value;
    if (!file_exists($targetPath)) {
        $file->moveTo($targetPath);
    }

    $folderId = $request->getParsedBody()['folderId'] ?? null;
    $dam->handle(AddAsset::create(
        id: AssetId::create(),
        mediaType: $file->getClientMediaType(),
        resourcePointer: $resourcePointer,
        filename: $file->getClientFilename(),
        label: $label,
        caption: $caption,
        folderId: $folderId,
    ));
    $query = $folderId !== null ? 'folder=' . $folderId : '';
    return $response
        ->withHeader('Location', (string)$request->getUri()->withPath('/')->withQuery($query))
        ->withStatus(303);
});

$app->run();