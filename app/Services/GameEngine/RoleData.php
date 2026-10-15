<?php

namespace App\Services\GameEngine;

class RoleData
{
    public const ROLE_FACTION = [
        // Kingdom
        'king'          => 'kingdom',
        'crown_prince'  => 'kingdom',
        'doctor'        => 'kingdom',
        'guard'         => 'kingdom',
        'royal_guard'   => 'kingdom',
        'knight'        => 'kingdom',
        'minister'      => 'kingdom',
        'wizard'        => 'kingdom',
        'investigator'  => 'kingdom',
        'messenger'     => 'kingdom',
        'priest'        => 'kingdom',
        'citizen'       => 'kingdom',
        // Shadow
        'assassin'      => 'shadow',
        'saboteur'      => 'shadow',
        'poisoner'      => 'shadow',
        'spy'           => 'shadow',
        'slasher'       => 'shadow',
        'impersonator'  => 'shadow',
        'forger'        => 'shadow',
        'shadow_leader' => 'shadow',
        // Neutral
        'trickster'     => 'neutral',
        'hermit'        => 'neutral',
    ];

    public const NIGHT_ACTIVE_ROLES = [
        'assassin', 'saboteur', 'poisoner', 'wizard', 'slasher',
        'impersonator', 'forger', 'shadow_leader',
        'doctor', 'guard', 'royal_guard', 'knight',
        'investigator', 'minister', 'spy', 'messenger', 'priest',
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
                $base = ['king', 'crown_prince', 'doctor', 'guard', 'investigator', 'royal_guard', 'knight', 'minister', 'spy', 'priest', 'assassin', 'saboteur', 'poisoner', 'wizard', 'slasher', 'trickster'];
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
}
