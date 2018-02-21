<?php
namespace TYPO3\CMS\Core\Tests\Unit\Imaging;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use Prophecy\Argument;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Imaging\IconProvider\FontawesomeIconProvider;
use TYPO3\CMS\Core\Imaging\IconProviderInterface;
use TYPO3\CMS\Core\Imaging\IconRegistry;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case
 */
class IconRegistryTest extends UnitTestCase
{
    /**
     * @var string
     */
    protected $notRegisteredIconIdentifier = 'my-super-unregistered-identifier';

    public function setUp()
    {
        $cacheManagerProphecy = $this->prophesize(CacheManager::class);
        GeneralUtility::setSingletonInstance(CacheManager::class, $cacheManagerProphecy->reveal());
        $cacheFrontendProphecy = $this->prophesize(FrontendInterface::class);
        $cacheManagerProphecy->getCache('assets')->willReturn($cacheFrontendProphecy->reveal());
        $cacheFrontendProphecy->get(Argument::cetera())->willReturn(false);
        $cacheFrontendProphecy->set(Argument::cetera())->willReturn(null);
    }

    public function tearDown()
    {
        // Drop cache manager singleton again
        GeneralUtility::purgeInstances();
        parent::tearDown();
    }

    /**
     * @test
     */
    public function getDefaultIconIdentifierReturnsTheCorrectDefaultIconIdentifierString()
    {
        $result = (new IconRegistry)->getDefaultIconIdentifier();
        $this->assertEquals($result, 'default-not-found');
    }

    /**
     * @test
     */
    public function isRegisteredReturnsTrueForRegisteredIcon()
    {
        $subject = new IconRegistry();
        $result = $subject->isRegistered($subject->getDefaultIconIdentifier());
        $this->assertEquals($result, true);
    }

    /**
     * @test
     */
    public function isRegisteredReturnsFalseForNotRegisteredIcon()
    {
        $result = (new IconRegistry)->isRegistered($this->notRegisteredIconIdentifier);
        $this->assertEquals($result, false);
    }

    /**
     * @test
     */
    public function registerIconAddNewIconToRegistry()
    {
        $unregisterdIcon = 'foo-bar-unregistered';
        $subject = new IconRegistry();
        $this->assertFalse($subject->isRegistered($unregisterdIcon));
        $subject->registerIcon($unregisterdIcon, FontawesomeIconProvider::class, [
            'name' => 'pencil',
            'additionalClasses' => 'fa-fw'
        ]);
        $this->assertTrue($subject->isRegistered($unregisterdIcon));
    }

    /**
     * @test
     */
    public function registerIconThrowsInvalidArgumentExceptionWithInvalidIconProvider()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1437425803);

        (new IconRegistry)->registerIcon($this->notRegisteredIconIdentifier, GeneralUtility::class);
    }

    /**
     * @test
     */
    public function getIconConfigurationByIdentifierThrowsExceptionWithUnregisteredIconIdentifier()
    {
        $this->expectException(\TYPO3\CMS\Core\Exception::class);
        $this->expectExceptionCode(1437425804);

        (new IconRegistry)->getIconConfigurationByIdentifier($this->notRegisteredIconIdentifier);
    }

    /**
     * @test
     */
    public function getIconConfigurationByIdentifierReturnsCorrectConfiguration()
    {
        $result = (new IconRegistry)->getIconConfigurationByIdentifier('default-not-found');
        // result must contain at least provider and options array
        $this->assertArrayHasKey('provider', $result);
        $this->assertArrayHasKey('options', $result);
        // the provider must implement the IconProviderInterface
        $this->assertTrue(in_array(IconProviderInterface::class, class_implements($result['provider'])));
    }

    /**
     * @test
     */
    public function getAllRegisteredIconIdentifiersReturnsAnArrayWithIconIdentiefiers()
    {
        $this->assertInternalType('array', (new IconRegistry)->getAllRegisteredIconIdentifiers());
    }

    /**
     * @test
     */
    public function getAllRegisteredIconIdentifiersReturnsArrayWithAllRegisteredIconIdentifiers()
    {
        $result = (new IconRegistry)->getAllRegisteredIconIdentifiers();
        $this->assertInternalType('array', $result);
        $this->assertContains('default-not-found', $result);
    }

    /**
     * @test
     */
    public function getIconIdentifierForFileExtensionReturnsDefaultIconIdentifierForEmptyFileExtension()
    {
        $result = (new IconRegistry)->getIconIdentifierForFileExtension('');
        $this->assertEquals('mimetypes-other-other', $result);
    }

    /**
     * @test
     */
    public function getIconIdentifierForFileExtensionReturnsDefaultIconIdentifierForUnknownFileExtension()
    {
        $result = (new IconRegistry)->getIconIdentifierForFileExtension('xyz');
        $this->assertEquals('mimetypes-other-other', $result);
    }

    /**
     * @test
     */
    public function getIconIdentifierForFileExtensionReturnsImageIconIdentifierForImageFileExtension()
    {
        $result = (new IconRegistry)->getIconIdentifierForFileExtension('jpg');
        $this->assertEquals('mimetypes-media-image', $result);
    }

    /**
     * @test
     */
    public function registerFileExtensionRegisterAnIcon()
    {
        $subject = new IconRegistry();
        $subject->registerFileExtension('abc', 'xyz');
        $result = $subject->getIconIdentifierForFileExtension('abc');
        $this->assertEquals('xyz', $result);
    }

    /**
     * @test
     */
    public function registerFileExtensionOverwriteAnExistingIcon()
    {
        $subject = new IconRegistry();
        $subject->registerFileExtension('jpg', 'xyz');
        $result = $subject->getIconIdentifierForFileExtension('jpg');
        $this->assertEquals('xyz', $result);
    }

    /**
     * @test
     */
    public function registerMimeTypeIconRegisterAnIcon()
    {
        $subject = new IconRegistry();
        $subject->registerMimeTypeIcon('foo/bar', 'mimetype-foo-bar');
        $result = $subject->getIconIdentifierForMimeType('foo/bar');
        $this->assertEquals('mimetype-foo-bar', $result);
    }

    /**
     * @test
     */
    public function registerMimeTypeIconOverwriteAnExistingIcon()
    {
        $subject = new IconRegistry();
        $subject->registerMimeTypeIcon('video/*', 'mimetype-foo-bar');
        $result = $subject->getIconIdentifierForMimeType('video/*');
        $this->assertEquals('mimetype-foo-bar', $result);
    }

    /**
     * @test
     */
    public function getIconIdentifierForMimeTypeWithUnknowMimeTypeReturnNull()
    {
        $result = (new IconRegistry)->getIconIdentifierForMimeType('bar/foo');
        $this->assertEquals(null, $result);
    }
}
