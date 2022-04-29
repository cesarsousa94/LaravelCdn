<?php

namespace SampleNinja\LaravelCdn\Tests\Juhasev\laravelcdn;

use Illuminate\Support\Collection;
use Mockery as M;
use SampleNinja\LaravelCdn\Tests\TestCase;

/**
 * Class CdnTest.
 *
 * @category Test
 *
 * @author  Mahmoud Zalt <mahmoud@vinelab.com>
 */
class CdnTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->m_spl_file_info = M::mock('Symfony\Component\Finder\SplFileInfo');
    }

    public function tearDown(): void
    {
        M::close();
        parent::tearDown();
    }

    public function testPushCommandReturnTrue()
    {
        $this->m_asset = M::mock('SampleNinja\LaravelCdn\Contracts\AssetInterface');
        $this->m_asset->shouldReceive('init')
            ->once()
            ->andReturn($this->m_asset);
        $this->m_asset->shouldReceive('setAssets')
            ->once();

        $this->m_asset->shouldReceive('getAssets')
            ->once()
            ->andReturn(new Collection());

        $this->m_finder = M::mock('SampleNinja\LaravelCdn\Contracts\FinderInterface');
        $this->m_finder->shouldReceive('read')
            ->with($this->m_asset)
            ->once()
            ->andReturn(new Collection());

        $this->m_provider = M::mock('SampleNinja\LaravelCdn\Providers\Provider');
        $this->m_provider->shouldReceive('upload')
            ->once()
            ->andReturn(true);

        $this->m_provider_factory = M::mock('SampleNinja\LaravelCdn\Contracts\ProviderFactoryInterface');
        $this->m_provider_factory->shouldReceive('create')
            ->once()
            ->andReturn($this->m_provider);

        $this->m_helper = M::mock('SampleNinja\LaravelCdn\Contracts\CdnHelperInterface');
        $this->m_helper->shouldReceive('getConfigurations')
            ->once()
            ->andReturn([]);

        $this->cdn = new \SampleNinja\LaravelCdn\Cdn(
            $this->m_finder,
            $this->m_asset,
            $this->m_provider_factory,
            $this->m_helper);

        $result = $this->cdn->push();

        $this->assertEquals(true, $result);
    }

    /**
     * Integration Test.
     */
    public function testPushCommand()
    {
        $configuration_file = [
            'bypass'    => false,
            'default'   => 'AwsS3',
            'url'       => 'https://s3.amazonaws.com',
            'threshold' => 10,
            'providers' => [
                'aws' => [
                    's3' => [
                        'region'      => 'us-standard',
                        'version'     => 'latest',
                        'buckets'     => [
                            'my-bucket-name' => '*',
                        ],
                        'acl'         => 'public-read',
                        'cloudfront'  => [
                            'use'     => false,
                            'cdn_url' => '',
                        ],
                        'metadata' => [],

                        'expires' => gmdate('D, d M Y H:i:s T', strtotime('+5 years')),

                        'cache-control' => 'max-age=2628000',
                    ],
                ],
            ],
            'include'   => [
                'directories' => [__DIR__],
                'extensions'  => [],
                'patterns'    => [],
            ],
            'exclude'   => [
                'directories' => [],
                'files'       => [],
                'extensions'  => [],
                'patterns'    => [],
                'hidden'      => true,
            ],
        ];

        $m_consol = M::mock('Symfony\Component\Console\Output\ConsoleOutput');
        $m_consol->shouldReceive('writeln')
            ->atLeast(1);

        $finder = new \SampleNinja\LaravelCdn\Finder($m_consol);

        $asset = new \SampleNinja\LaravelCdn\Asset();

        $provider_factory = new \SampleNinja\LaravelCdn\ProviderFactory();

        $m_config = M::mock('Illuminate\Config\Repository');
        $m_config->shouldReceive('get')
            ->with('cdn')
            ->once()
            ->andReturn($configuration_file);

        $helper = new \SampleNinja\LaravelCdn\CdnHelper($m_config);

        $m_console = M::mock('Symfony\Component\Console\Output\ConsoleOutput');
        $m_console->shouldReceive('writeln')
            ->atLeast(2);

        $m_validator = M::mock('SampleNinja\LaravelCdn\Validators\Contracts\ProviderValidatorInterface');
        $m_validator->shouldReceive('validate');

        $m_helper = M::mock('SampleNinja\LaravelCdn\CdnHelper');

        $m_spl_file = M::mock('Symfony\Component\Finder\SplFileInfo');
        $m_spl_file->shouldReceive('getPathname')
            ->andReturn('SampleNinja\LaravelCdn/tests/Juhasev/laravelcdn/AwsS3ProviderTest.php');
        $m_spl_file->shouldReceive('getRealPath')
            ->andReturn(__DIR__.'/AwsS3ProviderTest.php');

        // partial mock
        $p_aws_s3_provider = M::mock('\SampleNinja\LaravelCdn\Providers\AwsS3Provider[connect]',
        [
            $m_console,
            $m_validator,
            $m_helper,
        ]);

        $m_s3 = M::mock('Aws\S3\S3Client');
        $m_s3->shouldReceive('factory')->andReturn('Aws\S3\S3Client');
        $m_command = M::mock('Aws\Command');
        $m_s3->shouldReceive('getCommand')->andReturn($m_command);
        $m_command1 = M::mock('Aws\Result')->shouldIgnoreMissing();
        $m_s3->shouldReceive('listObjects')->andReturn($m_command1);
        $m_s3->shouldReceive('execute');
        $p_aws_s3_provider->setS3Client($m_s3);

        $p_aws_s3_provider->shouldReceive('connect')->andReturn(true);

        \Illuminate\Support\Facades\App::shouldReceive('make')
            ->once()
            ->andReturn($p_aws_s3_provider);

        $cdn = new \SampleNInja\LaravelCdn\Cdn($finder,
            $asset,
            $provider_factory,
            $helper
        );

        $result = $cdn->push();

        $this->assertEquals(true, $result);
    }
}
