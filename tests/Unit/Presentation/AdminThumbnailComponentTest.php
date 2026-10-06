<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Presentation;

use Lemonade\Admin\Presentation\AdminThumbnailComponent;
use Lemonade\Framework\Image\Value\ImageDimensions;
use Lemonade\Framework\Image\Value\ImageFormat;
use Lemonade\Framework\Image\Value\ImageQuality;
use Lemonade\Framework\Image\Value\ImageVariantDefinition;
use Lemonade\Framework\Routing\ControllerAction;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Routing\UrlGenerator;
use Lemonade\Image\ImageUrlGenerator;
use Lemonade\Image\ImageVariantRegistry;
use PHPUnit\Framework\TestCase;

final class AdminThumbnailComponentTest extends TestCase
{
    public function testItBuildsAThumbnailFromRegisteredImageIdentity(): void
    {
        $thumbnail = $this->component()->image(
            module: 'system.users',
            imageId: '42',
            alt: 'Ada Lovelace',
            fallback: 'AL',
            size: 'compact',
            shape: 'circle',
            presentation: 'datagrid',
        );

        self::assertSame('/api/image/system.users/datagrid/42', $thumbnail->url());
        self::assertSame('Ada Lovelace', $thumbnail->alt());
        self::assertSame('AL', $thumbnail->fallback());
        self::assertSame('compact', $thumbnail->size());
        self::assertSame('circle', $thumbnail->shape());
    }

    public function testItBuildsFallbackWithoutAnImageRequest(): void
    {
        $thumbnail = $this->component()->fallback(
            fallback: 'AL',
            size: 'detail',
            shape: 'rounded',
        );

        self::assertNull($thumbnail->url());
        self::assertSame('AL', $thumbnail->fallback());
        self::assertSame('detail', $thumbnail->size());
        self::assertSame('rounded', $thumbnail->shape());
    }

    /**
     * Overuje, ze detailovy nahled pouziva druhou sdilenou variantu
     */
    public function testItBuildsSharedThumbnailUrl(): void
    {
        $thumbnail = $this->component()->image(
            module: 'system.users',
            imageId: '42',
        );

        self::assertSame('/api/image/system.users/thumbnail/42', $thumbnail->url());
    }

    private function component(): AdminThumbnailComponent
    {
        $router = new Router();
        $router->getNamed(
            'api.image.show',
            '/api/image/{module}/{variant}/{image}',
            ControllerAction::for('TestController', 'show'),
        );
        $variants = new ImageVariantRegistry();
        $variants->register(
            variant: 'datagrid',
            definition: new ImageVariantDefinition(
                dimensions: new ImageDimensions(32, 32),
                format: ImageFormat::Webp,
                quality: ImageQuality::fromInt(82),
            ),
        );
        $variants->register(
            variant: 'thumbnail',
            definition: new ImageVariantDefinition(
                dimensions: new ImageDimensions(160, 160),
                format: ImageFormat::Webp,
                quality: ImageQuality::fromInt(82),
            ),
        );

        return new AdminThumbnailComponent(new ImageUrlGenerator(new UrlGenerator($router), $variants));
    }
}
