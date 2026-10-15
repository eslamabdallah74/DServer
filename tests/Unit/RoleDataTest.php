<?php

namespace Tests\Unit;

use App\Services\GameEngine\RoleData;
use PHPUnit\Framework\TestCase;

class RoleDataTest extends TestCase
{
    public function test_all_expected_roles_exist(): void
    {
        $roles = RoleData::getAllRoles();
        $this->assertNotEmpty($roles);
        $this->assertGreaterThanOrEqual(20, count($roles));

        // Critical roles must be present
        $this->assertArrayHasKey('king', $roles);
        $this->assertArrayHasKey('assassin', $roles);
        $this->assertArrayHasKey('doctor', $roles);
        $this->assertArrayHasKey('investigator', $roles);
        $this->assertArrayHasKey('trickster', $roles);
        $this->assertArrayHasKey('saboteur', $roles);
        $this->assertArrayHasKey('impersonator', $roles);
    }

    public function test_role_factions(): void
    {
        $this->assertEquals('kingdom', RoleData::getFaction('king'));
        $this->assertEquals('kingdom', RoleData::getFaction('doctor'));
        $this->assertEquals('shadow', RoleData::getFaction('assassin'));
        $this->assertEquals('shadow', RoleData::getFaction('saboteur'));
        $this->assertEquals('neutral', RoleData::getFaction('trickster'));
    }

    public function test_night_active_roles(): void
    {
        $nightRoles = RoleData::NIGHT_ACTIVE_ROLES;
        $this->assertContains('assassin', $nightRoles);
        $this->assertContains('doctor', $nightRoles);
        $this->assertContains('saboteur', $nightRoles);
        $this->assertContains('impersonator', $nightRoles);
        $this->assertContains('poisoner', $nightRoles);
    }

    public function test_role_pool_generation(): void
    {
        $pool4 = RoleData::getRolePool(4);
        $this->assertCount(4, $pool4);
        $this->assertContains('king', $pool4);

        $pool8 = RoleData::getRolePool(8);
        $this->assertCount(8, $pool8);

        $pool16 = RoleData::getRolePool(16);
        $this->assertCount(16, $pool16);
    }
}
