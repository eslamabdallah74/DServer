<?php

namespace Tests\Unit;

use App\Services\GameEngine\NightResolver;
use App\Services\GameEngine\RoleData;
use App\Services\GameEngine\RoomManager;
use App\Services\GameEngine\VictoryEngine;
use App\Services\PusherService;
use Tests\TestCase;

class GameLogicFixesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        PusherService::fake();
    }

    public function test_role_definitions_and_targeting_constraints(): void
    {
        // 1. Roles and factions
        $this->assertEquals('kingdom', RoleData::getFaction('judge'));
        $this->assertEquals('kingdom', RoleData::getFaction('hermit'));
        $this->assertEquals('neutral', RoleData::getFaction('survivor'));
        $this->assertEquals('neutral', RoleData::getFaction('secret_collector'));
        $this->assertEquals('neutral', RoleData::getFaction('harbinger'));

        // 2. Passive roles not in NIGHT_ACTIVE_ROLES
        $this->assertFalse(RoleData::hasNightAction('royal_guard'));
        $this->assertFalse(RoleData::hasNightAction('minister'));
        $this->assertFalse(RoleData::hasNightAction('king'));
        $this->assertFalse(RoleData::hasNightAction('citizen'));
        $this->assertFalse(RoleData::hasNightAction('judge'));
        $this->assertFalse(RoleData::hasNightAction('hermit'));

        // 3. canTargetSelf
        $this->assertTrue(RoleData::canTargetSelf('doctor'));
        $this->assertTrue(RoleData::canTargetSelf('guard'));
        $this->assertTrue(RoleData::canTargetSelf('priest'));
        $this->assertTrue(RoleData::canTargetSelf('survivor'));
        $this->assertFalse(RoleData::canTargetSelf('assassin'));
        $this->assertFalse(RoleData::canTargetSelf('knight'));

        // 4. canTargetAllies
        $this->assertTrue(RoleData::canTargetAllies('impersonator'));
        $this->assertFalse(RoleData::canTargetAllies('assassin'));
        $this->assertFalse(RoleData::canTargetAllies('poisoner'));
        $this->assertFalse(RoleData::canTargetAllies('saboteur'));

        // 5. noRepeatTarget
        $this->assertTrue(RoleData::noRepeatTarget('wizard'));
        $this->assertTrue(RoleData::noRepeatTarget('saboteur'));
        $this->assertTrue(RoleData::noRepeatTarget('forger'));
        $this->assertFalse(RoleData::noRepeatTarget('assassin'));

        // 6. Immunity
        $this->assertTrue(RoleData::isImmuneOn('hermit', 1));
        $this->assertTrue(RoleData::isImmuneOn('hermit', 2));
        $this->assertFalse(RoleData::isImmuneOn('hermit', 3));
    }

    public function test_guard_shield_is_consumed_on_first_attack_allowing_second_attack_to_kill(): void
    {
        $room = [
            'players' => [
                ['playerId' => 'p_guard', 'nickname' => 'الحارس', 'role' => 'guard', 'faction' => 'kingdom', 'isAlive' => true, 'statuses' => []],
                ['playerId' => 'p_victim', 'nickname' => 'الضحية', 'role' => 'citizen', 'faction' => 'kingdom', 'isAlive' => true, 'statuses' => []],
                ['playerId' => 'p_assassin', 'nickname' => 'القاتل', 'role' => 'assassin', 'faction' => 'shadow', 'isAlive' => true, 'statuses' => []],
                ['playerId' => 'p_slasher', 'nickname' => 'السفاح', 'role' => 'slasher', 'faction' => 'shadow', 'isAlive' => true, 'statuses' => []],
            ],
            'nightActions' => [
                'p_guard' => [
                    'actorPlayerId' => 'p_guard',
                    'roleId' => 'guard',
                    'targetPlayerId' => 'p_victim',
                ],
                'p_assassin' => [
                    'actorPlayerId' => 'p_assassin',
                    'roleId' => 'assassin',
                    'targetPlayerId' => 'p_victim',
                ],
                'p_slasher' => [
                    'actorPlayerId' => 'p_slasher',
                    'roleId' => 'slasher',
                    'targetPlayerId' => 'p_victim',
                ],
            ]
        ];

        $outcome = NightResolver::resolveNight($room);

        // Victim was attacked twice: guard shield stopped first attack, but second attack killed victim!
        $this->assertFalse($room['players'][1]['isAlive'], 'Victim must die from the second lethal attack after guard shield was consumed.');
        $killedIds = array_column($outcome['killed'], 'playerId');
        $this->assertContains('p_victim', $killedIds);
    }

    public function test_poisoner_does_not_kill_immediately_on_first_night(): void
    {
        $room = [
            'round' => 1,
            'poisonedPlayers' => [],
            'players' => [
                ['playerId' => 'p_poisoner', 'nickname' => 'السمام', 'role' => 'poisoner', 'faction' => 'shadow', 'isAlive' => true, 'statuses' => []],
                ['playerId' => 'p_victim', 'nickname' => 'الضحية', 'role' => 'citizen', 'faction' => 'kingdom', 'isAlive' => true, 'statuses' => []],
            ],
            'nightActions' => [
                'p_poisoner' => [
                    'actorPlayerId' => 'p_poisoner',
                    'roleId' => 'poisoner',
                    'targetPlayerId' => 'p_victim',
                ],
            ]
        ];

        $outcome = NightResolver::resolveNight($room);

        // Target must remain ALIVE on Night 1!
        $this->assertTrue($room['players'][1]['isAlive'], 'Target must not die on the night of poisoning.');
        $this->assertContains('POISONED', $room['players'][1]['statuses']);
        $this->assertEquals(0, $room['poisonedPlayers']['p_victim']); // Decremented from 1 to 0 for next night check

        // Night 2: Without doctor, victim dies!
        $room['round'] = 2;
        $room['nightActions'] = [];
        $outcome2 = NightResolver::resolveNight($room);

        $this->assertFalse($room['players'][1]['isAlive'], 'Victim must die on Night 2 if unhealed.');
        $killedIds2 = array_column($outcome2['killed'], 'playerId');
        $this->assertContains('p_victim', $killedIds2);
    }

    public function test_doctor_heals_poison_before_it_becomes_lethal(): void
    {
        $room = [
            'round' => 2,
            'poisonedPlayers' => ['p_victim' => 0], // Was poisoned on Night 1
            'players' => [
                ['playerId' => 'p_doctor', 'nickname' => 'الطبيب', 'role' => 'doctor', 'faction' => 'kingdom', 'isAlive' => true, 'statuses' => []],
                ['playerId' => 'p_victim', 'nickname' => 'الضحية', 'role' => 'citizen', 'faction' => 'kingdom', 'isAlive' => true, 'statuses' => ['POISONED']],
            ],
            'nightActions' => [
                'p_doctor' => [
                    'actorPlayerId' => 'p_doctor',
                    'roleId' => 'doctor',
                    'targetPlayerId' => 'p_victim',
                ],
            ]
        ];

        $outcome = NightResolver::resolveNight($room);

        $this->assertTrue($room['players'][1]['isAlive'], 'Doctor must successfully save poisoned victim.');
        $this->assertNotContains('POISONED', $room['players'][1]['statuses']);
        $this->assertArrayNotHasKey('p_victim', $room['poisonedPlayers']);
    }

    public function test_hermit_is_immune_to_hostile_actions_on_rounds_1_and_2(): void
    {
        $room = [
            'round' => 1,
            'players' => [
                ['playerId' => 'p_assassin', 'nickname' => 'القاتل', 'role' => 'assassin', 'faction' => 'shadow', 'isAlive' => true, 'statuses' => []],
                ['playerId' => 'p_hermit', 'nickname' => 'الناسك', 'role' => 'hermit', 'faction' => 'kingdom', 'isAlive' => true, 'statuses' => []],
            ],
            'nightActions' => [
                'p_assassin' => [
                    'actorPlayerId' => 'p_assassin',
                    'roleId' => 'assassin',
                    'targetPlayerId' => 'p_hermit',
                ],
            ]
        ];

        $outcome = NightResolver::resolveNight($room);

        // Hermit is immune!
        $this->assertTrue($room['players'][1]['isAlive'], 'Hermit must be immune on round 1.');
        $killedIds = array_column($outcome['killed'], 'playerId');
        $this->assertNotContains('p_hermit', $killedIds);
    }

    public function test_saboteur_cannot_block_player_without_active_night_action(): void
    {
        $room = [
            'round' => 1,
            'players' => [
                ['playerId' => 'p_saboteur', 'nickname' => 'المخرب', 'role' => 'saboteur', 'faction' => 'shadow', 'isAlive' => true, 'statuses' => []],
                ['playerId' => 'p_king', 'nickname' => 'الملك', 'role' => 'king', 'faction' => 'kingdom', 'isAlive' => true, 'statuses' => []],
            ],
            'nightActions' => [
                'p_saboteur' => [
                    'actorPlayerId' => 'p_saboteur',
                    'roleId' => 'saboteur',
                    'targetPlayerId' => 'p_king',
                ],
            ]
        ];

        $outcome = NightResolver::resolveNight($room);

        $this->assertFalse($outcome['results']['p_saboteur']['success']);
        $this->assertNotContains('BLOCKED', $room['players'][1]['statuses']);
    }

    public function test_minister_receives_automatic_intelligence_report(): void
    {
        $room = [
            'round' => 1,
            'players' => [
                ['playerId' => 'p_minister', 'nickname' => 'الوزير', 'role' => 'minister', 'faction' => 'kingdom', 'isAlive' => true, 'statuses' => []],
                ['playerId' => 'p_shadow', 'nickname' => 'الظلامي', 'role' => 'assassin', 'faction' => 'shadow', 'isAlive' => true, 'statuses' => []],
            ],
            'nightActions' => [] // Minister does not submit an active target!
        ];

        $outcome = NightResolver::resolveNight($room);

        $this->assertTrue($outcome['results']['p_minister']['success']);
        $intelTexts = array_column($outcome['intel'], 'text');
        $this->assertNotEmpty($intelTexts);
        $this->assertStringContainsString('الظلامي', $intelTexts[0]);
    }

    public function test_judge_veto_cancels_voting_elimination_and_enforces_cooldown(): void
    {
        $roomCode = 'VETOROOM';
        $room = [
            'roomCode' => $roomCode,
            'phase' => 'ELIMINATION',
            'round' => 1,
            'settings' => ['dayTimerSeconds' => 60, 'votingTimerSeconds' => 60],
            'players' => [
                ['playerId' => 'p_judge', 'nickname' => 'القاضي', 'role' => 'judge', 'faction' => 'kingdom', 'isAlive' => true],
                ['playerId' => 'p_voted', 'nickname' => 'المستبعد', 'role' => 'citizen', 'faction' => 'kingdom', 'isAlive' => false],
            ],
            'lastEliminatedPlayer' => ['playerId' => 'p_voted', 'nickname' => 'المستبعد', 'role' => 'citizen'],
            'lastEliminatedCause' => 'VOTE',
        ];
        RoomManager::saveRoom($room);

        // Apply Veto
        $result = RoomManager::applyVeto($roomCode, 'p_judge');

        $this->assertTrue($result['success']);
        $updatedRoom = RoomManager::getRoom($roomCode);

        // Eliminated player revived
        $this->assertTrue($updatedRoom['players'][1]['isAlive']);
        $this->assertNull($updatedRoom['lastEliminatedPlayer']);
        $this->assertEquals('VETO', $updatedRoom['lastEliminatedCause']);
        $this->assertEquals(6, $updatedRoom['judgeVetoState']['usesRemaining']);

        // Second veto on same round fails due to 3-day cooldown
        $result2 = RoomManager::applyVeto($roomCode, 'p_judge');
        $this->assertEquals('COOLDOWN_ACTIVE', $result2['error']);
    }

    public function test_crown_prince_succeeds_king_upon_daytime_vote_elimination(): void
    {
        $roomCode = 'SUCCESSIONROOM';
        $room = [
            'roomCode' => $roomCode,
            'phase' => 'VOTING',
            'round' => 1,
            'settings' => ['kingMustSurvive' => true],
            'players' => [
                ['playerId' => 'p_king', 'nickname' => 'الملك', 'role' => 'king', 'faction' => 'kingdom', 'isAlive' => true],
                ['playerId' => 'p_prince', 'nickname' => 'ولي العهد', 'role' => 'crown_prince', 'faction' => 'kingdom', 'isAlive' => true],
                ['playerId' => 'p_voter1', 'nickname' => 'مصوت1', 'role' => 'citizen', 'faction' => 'kingdom', 'isAlive' => true],
                ['playerId' => 'p_voter2', 'nickname' => 'مصوت2', 'role' => 'assassin', 'faction' => 'shadow', 'isAlive' => true],
            ],
            'votes' => [
                'p_voter1' => 'p_king',
                'p_voter2' => 'p_king',
            ],
        ];

        RoomManager::tallyVotesAndEliminate($room);

        // King is dead
        $this->assertFalse($room['players'][0]['isAlive']);
        // Crown prince is now King!
        $this->assertEquals('king', $room['players'][1]['role']);
        $this->assertFalse($room['vacantThrone']);
        $this->assertArrayHasKey('p_prince', $room['dayIntel'] ?? []);
    }

    public function test_submit_night_action_validations(): void
    {
        $roomCode = 'VALIDATIONROOM';
        $room = [
            'roomCode' => $roomCode,
            'phase' => 'NIGHT',
            'round' => 2,
            'settings' => [],
            'players' => [
                ['playerId' => 'p_assassin', 'nickname' => 'القاتل', 'role' => 'assassin', 'faction' => 'shadow', 'isAlive' => true],
                ['playerId' => 'p_shadow_ally', 'nickname' => 'رفيق الظل', 'role' => 'poisoner', 'faction' => 'shadow', 'isAlive' => true],
                ['playerId' => 'p_citizen', 'nickname' => 'المواطن', 'role' => 'citizen', 'faction' => 'kingdom', 'isAlive' => true],
                ['playerId' => 'p_doctor', 'nickname' => 'الطبيب', 'role' => 'doctor', 'faction' => 'kingdom', 'isAlive' => true],
                ['playerId' => 'p_impersonator', 'nickname' => 'المنتحل', 'role' => 'impersonator', 'faction' => 'shadow', 'isAlive' => true],
                ['playerId' => 'p_wizard', 'nickname' => 'الساحر', 'role' => 'wizard', 'faction' => 'kingdom', 'isAlive' => true],
                ['playerId' => 'p_saboteur', 'nickname' => 'المخرب', 'role' => 'saboteur', 'faction' => 'shadow', 'isAlive' => true],
                ['playerId' => 'p_dead', 'nickname' => 'ميت', 'role' => 'guard', 'faction' => 'kingdom', 'isAlive' => false],
            ],
            'lastNightTargets' => [
                'p_wizard' => ['p_citizen'], // Wizard silenced citizen last night
            ],
            'nightActions' => [],
        ];
        RoomManager::saveRoom($room);

        // 1. Inactive role cannot act
        $res1 = RoomManager::submitNightAction($roomCode, 'p_citizen', 'p_assassin');
        $this->assertEquals('ROLE_HAS_NO_ACTION', $res1['error']);

        // 2. Targeting dead player fails
        $res2 = RoomManager::submitNightAction($roomCode, 'p_assassin', 'p_dead');
        $this->assertEquals('TARGET_NOT_ALIVE', $res2['error']);

        // 3. Assassin cannot target self
        $res3 = RoomManager::submitNightAction($roomCode, 'p_assassin', 'p_assassin');
        $this->assertEquals('CANNOT_TARGET_SELF', $res3['error']);

        // 4. Assassin cannot target shadow ally
        $res4 = RoomManager::submitNightAction($roomCode, 'p_assassin', 'p_shadow_ally');
        $this->assertEquals('CANNOT_TARGET_ALLY', $res4['error']);

        // 5. Doctor CAN target self
        $res5 = RoomManager::submitNightAction($roomCode, 'p_doctor', 'p_doctor');
        $this->assertTrue($res5['success'] ?? false);

        // 6. Impersonator CAN target shadow ally
        $res6 = RoomManager::submitNightAction($roomCode, 'p_impersonator', '', ['p_shadow_ally', 'p_citizen']);
        $this->assertTrue($res6['success'] ?? false);

        // 7. Wizard CANNOT repeat target from previous night
        $res7 = RoomManager::submitNightAction($roomCode, 'p_wizard', 'p_citizen');
        $this->assertEquals('CANNOT_REPEAT_TARGET', $res7['error']);

        // 8. Saboteur CANNOT target citizen (no active ability)
        $res8 = RoomManager::submitNightAction($roomCode, 'p_saboteur', 'p_citizen');
        $this->assertEquals('TARGET_HAS_NO_ACTION', $res8['error']);

        // 9. Action rejected if phase is not NIGHT
        $room['phase'] = 'DAY';
        RoomManager::saveRoom($room);
        $res9 = RoomManager::submitNightAction($roomCode, 'p_assassin', 'p_citizen');
        $this->assertEquals('INVALID_PHASE', $res9['error']);
    }
}
