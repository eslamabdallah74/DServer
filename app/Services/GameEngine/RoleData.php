<?php

namespace App\Services\GameEngine;

class RoleData
{
    public const ROLE_FACTION = [
        // Kingdom
        'king'             => 'kingdom',
        'crown_prince'     => 'kingdom',
        'doctor'           => 'kingdom',
        'guard'            => 'kingdom',
        'royal_guard'      => 'kingdom',
        'knight'           => 'kingdom',
        'minister'         => 'kingdom',
        'wizard'           => 'kingdom',
        'investigator'     => 'kingdom',
        'messenger'        => 'kingdom',
        'priest'           => 'kingdom',
        'citizen'          => 'kingdom',
        'judge'            => 'kingdom',
        'hermit'           => 'kingdom',
        // Shadow
        'assassin'         => 'shadow',
        'saboteur'         => 'shadow',
        'poisoner'         => 'shadow',
        'spy'              => 'shadow',
        'slasher'          => 'shadow',
        'impersonator'     => 'shadow',
        'forger'           => 'shadow',
        'shadow_leader'    => 'shadow',
        // Neutral
        'trickster'        => 'neutral',
        'survivor'         => 'neutral',
        'secret_collector' => 'neutral',
        'harbinger'        => 'neutral',
    ];

    public const NIGHT_ACTIVE_ROLES = [
        'assassin', 'saboteur', 'poisoner', 'wizard', 'slasher',
        'impersonator', 'forger', 'shadow_leader',
        'doctor', 'guard', 'knight',
        'investigator', 'spy', 'messenger', 'priest',
        'survivor', 'secret_collector', 'harbinger',
    ];

    public const CAN_TARGET_SELF_ROLES = [
        'doctor', 'guard', 'priest', 'survivor',
    ];

    public const NO_REPEAT_TARGET_ROLES = [
        'wizard', 'saboteur', 'forger',
    ];

    /**
     * Role composition pool by player count (2 to 16+)
     */
    public static function getCompositionPool(int $count): array
    {
        switch ($count) {
            case 2:  return ['king', 'assassin'];
            case 3:  return ['king', 'crown_prince', 'assassin'];
            case 4:  return ['king', 'crown_prince', 'assassin', 'citizen'];
            case 5:  return ['king', 'crown_prince', 'doctor', 'guard', 'assassin'];
            case 6:  return ['king', 'crown_prince', 'guard', 'assassin', 'saboteur', 'trickster'];
            case 7:  return ['king', 'crown_prince', 'doctor', 'guard', 'assassin', 'saboteur', 'trickster'];
            case 8:  return ['king', 'crown_prince', 'doctor', 'guard', 'investigator', 'assassin', 'saboteur', 'trickster'];
            case 9:  return ['king', 'crown_prince', 'doctor', 'guard', 'investigator', 'assassin', 'saboteur', 'poisoner', 'trickster'];
            case 10: return ['king', 'crown_prince', 'doctor', 'guard', 'investigator', 'royal_guard', 'assassin', 'saboteur', 'poisoner', 'trickster'];
            case 11: return ['king', 'crown_prince', 'doctor', 'guard', 'investigator', 'royal_guard', 'knight', 'assassin', 'saboteur', 'poisoner', 'trickster'];
            case 12: return ['king', 'crown_prince', 'doctor', 'guard', 'investigator', 'royal_guard', 'knight', 'minister', 'assassin', 'saboteur', 'poisoner', 'trickster'];
            case 13: return ['king', 'crown_prince', 'doctor', 'guard', 'investigator', 'royal_guard', 'knight', 'minister', 'assassin', 'saboteur', 'poisoner', 'wizard', 'trickster'];
            case 14: return ['king', 'crown_prince', 'doctor', 'guard', 'investigator', 'royal_guard', 'knight', 'minister', 'spy', 'assassin', 'saboteur', 'poisoner', 'wizard', 'trickster'];
            case 15: return ['king', 'crown_prince', 'doctor', 'guard', 'investigator', 'royal_guard', 'knight', 'minister', 'spy', 'priest', 'assassin', 'saboteur', 'poisoner', 'wizard', 'trickster'];
            default: {
                $base = ['king', 'crown_prince', 'doctor', 'guard', 'investigator', 'royal_guard', 'knight', 'minister', 'spy', 'priest', 'judge', 'assassin', 'saboteur', 'poisoner', 'wizard', 'slasher', 'trickster', 'hermit'];
                while (count($base) < $count) {
                    $base[] = 'citizen';
                }
                return array_slice($base, 0, $count);
            }
        }
    }

    public static function getAllRoles(): array
    {
        return self::ROLE_FACTION;
    }

    public static function getRolePool(int $count): array
    {
        return self::getCompositionPool($count);
    }

    public static function getFaction(string $role): string
    {
        return self::ROLE_FACTION[$role] ?? 'kingdom';
    }

    public static function hasNightAction(string $role): bool
    {
        return in_array($role, self::NIGHT_ACTIVE_ROLES, true);
    }

    public static function canTargetSelf(string $role): bool
    {
        return in_array($role, self::CAN_TARGET_SELF_ROLES, true);
    }

    public static function canTargetAllies(string $role): bool
    {
        // Impersonator can target allies or enemies to copy their abilities.
        // Other shadow roles cannot target fellow shadow allies.
        if ($role === 'impersonator') return true;
        if (self::getFaction($role) === 'shadow') return false;
        return true;
    }

    public static function noRepeatTarget(string $role): bool
    {
        return in_array($role, self::NO_REPEAT_TARGET_ROLES, true);
    }

    public static function getRequiredTargets(string $role): int
    {
        if (!self::hasNightAction($role)) return 0;
        if ($role === 'messenger' || $role === 'impersonator') return 2;
        return 1;
    }

    public static function isImmuneOn(string $role, int $round): bool
    {
        if ($role === 'hermit' && $round <= 2) {
            return true;
        }
        return false;
    }
}
