<?php

namespace Tests\Unit;

use App\Helpers\GeoIpHelper;
use App\Models\SettingModel;
use App\Services\NginxConfigGenerator;
use App\Services\NginxManager;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class GeoIpSecurityTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testCountryListHelperReturnsValidIsoCodes(): void
    {
        $countries = GeoIpHelper::getCountryList();
        $this->assertNotEmpty($countries);
        $this->assertArrayHasKey('ES', $countries);
        $this->assertSame('España', $countries['ES']['name']);
        $this->assertArrayHasKey('US', $countries);
        $this->assertArrayHasKey('CN', $countries);
        $this->assertArrayHasKey('RU', $countries);
    }

    public function testNginxConfigGeneratorIncludesGeoIpCheck(): void
    {
        $generator = new NginxConfigGenerator();
        $proxy = [
            'id'           => 99,
            'domain'       => 'geoip-test.local',
            'forward_host' => '127.0.0.1',
            'forward_port' => 8080,
            'scheme'       => 'http',
            'ssl_enabled'  => 0,
        ];

        $vhost = $generator->generate($proxy);
        $this->assertStringContainsString('if ($geoip_blocked = 1)', $vhost);
        $this->assertStringContainsString('return 403;', $vhost);
    }

    public function testNginxManagerGeneratesDisabledGeoIpConfig(): void
    {
        $manager = new NginxManager();
        $result = $manager->syncGeoIp(false);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('map $remote_addr $geoip_blocked', $result['content']);
        $this->assertStringContainsString('default 0;', $result['content']);
    }

    public function testNginxManagerGeneratesBlocklistGeoIpConfig(): void
    {
        $manager = new NginxManager();
        $result = $manager->syncGeoIp(true, 'blocklist', ['RU', 'CN', 'KP']);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('geoip2 /usr/share/GeoIP/GeoLite2-Country.mmdb', $result['content']);
        $this->assertStringContainsString('map $geoip2_data_country_code $geoip_blocked', $result['content']);
        $this->assertStringContainsString('default 0;', $result['content']);
        $this->assertStringContainsString('RU 1;', $result['content']);
        $this->assertStringContainsString('CN 1;', $result['content']);
        $this->assertStringContainsString('KP 1;', $result['content']);
    }

    public function testNginxManagerGeneratesAllowlistGeoIpConfig(): void
    {
        $manager = new NginxManager();
        $result = $manager->syncGeoIp(true, 'allowlist', ['ES', 'PT', 'FR']);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('map $geoip2_data_country_code $geoip_blocked', $result['content']);
        $this->assertStringContainsString('default 1;', $result['content']);
        $this->assertStringContainsString('ES 0;', $result['content']);
        $this->assertStringContainsString('PT 0;', $result['content']);
        $this->assertStringContainsString('FR 0;', $result['content']);
    }
}

