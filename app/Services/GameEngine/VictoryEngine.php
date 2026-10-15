<?php

namespace App\Services\GameEngine;

class VictoryEngine
{
    /**
     * Tiered Victory Evaluator (100% aligned with Node.js and Flutter offline game engine)
     */
    public static function evaluateVictory(array &$room): array
    {
        $players = &$room['players'];
        $round = $room['round'] ?? 0;

        // Guard: don't decide victory before round 1 starts
        if ($round < 1) {
            return ['isDecided' => false];
        }

        $getFaction = function ($p) {
            return $p['faction'] ?? RoleData::getFaction($p['role'] ?? '');
        };

        $livingKingdom = array_values(array_filter($players, fn($p) => ($p['isAlive'] ?? true) && $getFaction($p) === 'kingdom'));
        $livingShadows = array_values(array_filter($players, fn($p) => ($p['isAlive'] ?? true) && $getFaction($p) === 'shadow'));

        // ── Tier 1: Trickster Solo Win ────────────────────────────────────────────
        $lastElim = $room['lastEliminatedPlayer'] ?? null;
        $lastCause = $room['lastEliminatedCause'] ?? null;

        if (
            $lastElim &&
            ($lastElim['role'] ?? '') === 'trickster' &&
            $lastCause === 'VOTE'
        ) {
            return [
                'isDecided' => true,
                'kind' => 'SOLO',
                'winnerIds' => [$lastElim['playerId']],
                'ruleId' => 'trickster_voted_out',
                'reason' => 'فاز المخادع — سقط في تصويت النهار!',
            ];
        }

        // ── King Succession Check ──────────────────────────────────────────────
        $livingKing = null;
        $livingCrownPrince = null;
        foreach ($players as &$p) {
            if ($p['isAlive'] ?? true) {
                if (($p['role'] ?? '') === 'king') $livingKing = &$p;
                if (($p['role'] ?? '') === 'crown_prince') $livingCrownPrince = &$p;
            }
        }

        if (!$livingKing && $livingCrownPrince) {
            $livingCrownPrince['role'] = 'king';
            $room['vacantThrone'] = false;
        } elseif (!$livingKing && !$livingCrownPrince) {
            $room['vacantThrone'] = true;
        }

        // ── Tier 2: Vacant Throne — Shadow Auto-Win ───────────────────────────────
        $kingMustSurvive = $room['settings']['kingMustSurvive'] ?? true;
        if ($kingMustSurvive && ($room['vacantThrone'] ?? false) === true) {
            return [
                'isDecided' => true,
                'kind' => 'FACTION',
                'faction' => 'SHADOW',
                'ruleId' => 'king_must_survive',
                'reason' => 'سقط الملك وخلا العرش من وريث شرعي — انتصرت قوى الظلام!',
            ];
        }

        // ── Tier 3: Kingdom Wins — All Shadows Dead ───────────────────────────────
        if (count($livingShadows) === 0) {
            return [
                'isDecided' => true,
                'kind' => 'FACTION',
                'faction' => 'KINGDOM',
                'ruleId' => 'shadows_eliminated',
                'reason' => 'تم القضاء على جميع الظلال — نصرت المملكة!',
            ];
        }

        // ── Tier 3b: Kingdom Wins — 100% Tasks Completed ─────────────────────────
        $taskProgress = $room['taskProgress'] ?? 0;
        if ($taskProgress >= 1.0) {
            return [
                'isDecided' => true,
                'kind' => 'FACTION',
                'faction' => 'KINGDOM',
                'ruleId' => 'tasks_completed',
                'reason' => 'أنجزت المملكة 100٪ من مهامها — النصر للمملكة!',
            ];
        }

        // ── Tier 4: Shadow Wins — Outnumber Kingdom ───────────────────────────────
        if (count($livingShadows) > count($livingKingdom)) {
            return [
                'isDecided' => true,
                'kind' => 'FACTION',
                'faction' => 'SHADOW',
                'ruleId' => 'shadows_outnumber',
                'reason' => 'تفوق عدد الظلال على المملكة — النصر للظلام!',
            ];
        }

        // ── Tier 4b: Shadow Wins — Parity with no protection ────────────────────
        if (count($livingShadows) === count($livingKingdom) && count($livingKingdom) > 0) {
            $hasProtector = false;
            foreach ($livingKingdom as $p) {
                $r = $p['role'] ?? '';
                if ($r === 'doctor' || $r === 'guard' || $r === 'royal_guard') {
                    $hasProtector = true;
                    break;
                }
            }
            if (!$hasProtector) {
                return [
                    'isDecided' => true,
                    'kind' => 'FACTION',
                    'faction' => 'SHADOW',
                    'ruleId' => 'shadows_break_parity',
                    'reason' => 'تعادل فريق الظلال مع المملكة وانعدمت الحماية — انتصر الظلام!',
                ];
            }
        }

        return ['isDecided' => false];
    }
}
