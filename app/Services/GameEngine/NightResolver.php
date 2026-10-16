<?php

namespace App\Services\GameEngine;

class NightResolver
{
    /**
     * Resolves all submitted night actions using the 100-Stage Priority Queue.
     */
    public static function resolveNight(array &$room): array
    {
        $round = (int)($room['round'] ?? 1);

        // Build direct reference map for players by playerId
        $playerMap = [];
        foreach ($room['players'] as $idx => &$p) {
            $playerMap[$p['playerId']] = &$room['players'][$idx];
        }
        unset($p);

        $actions = array_values($room['nightActions'] ?? []);

        $results = [];
        $intel = []; // Private feedback messages per playerId
        $killed = [];
        $saved = [];
        $silenced = [];
        $blockedActors = [];
        $recommendation = null;

        // 1. Group actions by role stage priority
        $stageMap = [];
        foreach ($actions as $action) {
            $actorId = $action['actorPlayerId'] ?? $action['actorId'] ?? '';
            if (!isset($playerMap[$actorId]) || !($playerMap[$actorId]['isAlive'] ?? true)) {
                continue;
            }

            $roleId = $action['roleId'] ?? $action['role'] ?? ($playerMap[$actorId]['role'] ?? '');
            $stage = self::getRoleStagePriority($roleId);

            $stageMap[$stage][] = [
                'action'  => $action,
                'actorId' => $actorId,
                'roleId'  => $roleId,
            ];
        }

        $activeProtections = []; // targetPlayerId => [ ['protectorId' => ..., 'type' => ...] ]
        $pendingKills = [];      // [ ['attackerId' => ..., 'victimId' => ...] ]
        $activeHeals = [];       // victimId => true

        // 2. Stage-by-Stage Processing (10 to 100)
        for ($stage = 10; $stage <= 100; $stage += 10) {
            $bucket = $stageMap[$stage] ?? [];

            // Stage 10: Block (Saboteur) & Mark (Shadow Leader)
            if ($stage === 10) {
                foreach ($bucket as $entry) {
                    $action = $entry['action'];
                    $actorId = $entry['actorId'];
                    $roleId = $entry['roleId'];
                    $actor = &$playerMap[$actorId];

                    $targetId = $action['targetPlayerId'] ?? '';
                    if (!isset($playerMap[$targetId])) continue;
                    $target = &$playerMap[$targetId];

                    // Hermit immunity check on rounds 1-2
                    if (($target['role'] ?? '') === 'hermit' && $round <= 2) {
                        $results[$actorId] = ['success' => false, 'message' => 'لم يكن لقدرتك أي تأثير على الهدف.'];
                        continue;
                    }

                    if ($roleId === 'saboteur') {
                        $targetRole = $target['role'] ?? '';
                        if (!RoleData::hasNightAction($targetRole)) {
                            $results[$actorId] = ['success' => false, 'message' => "فشل التعطيل: {$target['nickname']} لا يملك قدرة ليلية نشطة."];
                            continue;
                        }

                        if (!in_array('BLOCKED', $target['statuses'] ?? [], true)) {
                            $target['statuses'][] = 'BLOCKED';
                        }
                        $blockedActors[] = $target;
                        $results[$actorId] = ['success' => true, 'message' => "تم تعطيل قدرة {$target['nickname']} بنجاح."];
                    } elseif ($roleId === 'shadow_leader') {
                        $recommendation = $target['playerId'];
                        $room['shadowRecommendation'] = $target['playerId'];
                        $results[$actorId] = ['success' => true, 'message' => "تم اقتراح استهداف {$target['nickname']}."];
                    }
                }
            }

            // Stage 20: Copy (Impersonator)
            if ($stage === 20) {
                foreach ($bucket as $entry) {
                    $action = $entry['action'];
                    $actorId = $entry['actorId'];
                    $actor = &$playerMap[$actorId];
                    if (in_array('BLOCKED', $actor['statuses'] ?? [], true)) continue;

                    $targetIds = !empty($action['targetPlayerIds']) ? $action['targetPlayerIds'] : (!empty($action['targetPlayerId']) ? [$action['targetPlayerId']] : []);
                    $sourceId = $targetIds[0] ?? '';
                    if (!isset($playerMap[$sourceId])) continue;
                    $source = &$playerMap[$sourceId];

                    if (!empty($source['role']) && ($source['isAlive'] ?? true)) {
                        $actor['copiedRole'] = $source['role'];
                        $results[$actorId] = ['success' => true, 'message' => "تم نسخ قدرة {$source['nickname']}!"];

                        $victimId = $targetIds[1] ?? null;
                        if ($victimId && isset($playerMap[$victimId]) && ($playerMap[$victimId]['isAlive'] ?? true)) {
                            $copiedStage = self::getRoleStagePriority($source['role']);
                            if ($copiedStage > 20) {
                                $stageMap[$copiedStage][] = [
                                    'action' => [
                                        'actorPlayerId' => $actorId,
                                        'targetPlayerId' => $victimId,
                                        'targetPlayerIds' => [$victimId],
                                    ],
                                    'actorId' => $actorId,
                                    'roleId' => $source['role'],
                                ];
                            }
                        }
                    }
                }
            }

            // Stage 30: Protect (Guard / Royal Guard passive / Knight)
            if ($stage === 30) {
                // Royal Guard passive auto-shield for King
                $kingId = null;
                $royalGuardId = null;
                foreach ($playerMap as $pid => $p) {
                    if (($p['isAlive'] ?? true)) {
                        if (($p['role'] ?? '') === 'king') $kingId = $pid;
                        if (($p['role'] ?? '') === 'royal_guard') $royalGuardId = $pid;
                    }
                }

                if ($kingId && $royalGuardId) {
                    $activeProtections[$kingId][] = [
                        'protectorId' => $royalGuardId,
                        'type' => 'ROYAL_GUARD'
                    ];
                }

                foreach ($bucket as $entry) {
                    $action = $entry['action'];
                    $actorId = $entry['actorId'];
                    $roleId = $entry['roleId'];
                    $actor = &$playerMap[$actorId];
                    if (in_array('BLOCKED', $actor['statuses'] ?? [], true)) continue;

                    $targetId = $action['targetPlayerId'] ?? '';
                    if (!isset($playerMap[$targetId])) continue;
                    $target = &$playerMap[$targetId];

                    if ($roleId === 'guard') {
                        $activeProtections[$target['playerId']][] = ['protectorId' => $actorId, 'type' => 'GUARD'];
                        $results[$actorId] = ['success' => true, 'message' => "قمت بحماية {$target['nickname']} الليلة."];
                    } elseif ($roleId === 'knight') {
                        $activeProtections[$target['playerId']][] = ['protectorId' => $actorId, 'type' => 'KNIGHT'];
                        $results[$actorId] = ['success' => true, 'message' => "قمت بإعلان الفدية لحماية {$target['nickname']}."];
                    }
                }
            }

            // Stage 40: Silence (Wizard)
            if ($stage === 40) {
                foreach ($bucket as $entry) {
                    $action = $entry['action'];
                    $actorId = $entry['actorId'];
                    $roleId = $entry['roleId'];
                    $actor = &$playerMap[$actorId];
                    if (in_array('BLOCKED', $actor['statuses'] ?? [], true)) continue;

                    $targetId = $action['targetPlayerId'] ?? '';
                    if (!isset($playerMap[$targetId])) continue;
                    $target = &$playerMap[$targetId];

                    // Hermit immunity check on rounds 1-2
                    if (($target['role'] ?? '') === 'hermit' && $round <= 2) {
                        $results[$actorId] = ['success' => false, 'message' => 'لم يكن لقدرتك أي تأثير على الهدف.'];
                        continue;
                    }

                    if ($roleId === 'wizard') {
                        if (!in_array('SILENCED', $target['statuses'] ?? [], true)) {
                            $target['statuses'][] = 'SILENCED';
                        }
                        $silenced[] = $target;
                        $results[$actorId] = ['success' => true, 'message' => "تم إسكات {$target['nickname']} للغد."];
                    }
                }
            }

            // Stage 50: Poison (Poisoner - Sets Delayed Poison)
            if ($stage === 50) {
                foreach ($bucket as $entry) {
                    $action = $entry['action'];
                    $actorId = $entry['actorId'];
                    $roleId = $entry['roleId'];
                    $actor = &$playerMap[$actorId];
                    if (in_array('BLOCKED', $actor['statuses'] ?? [], true)) continue;

                    $targetId = $action['targetPlayerId'] ?? '';
                    if (!isset($playerMap[$targetId])) continue;
                    $target = &$playerMap[$targetId];

                    // Hermit immunity check on rounds 1-2
                    if (($target['role'] ?? '') === 'hermit' && $round <= 2) {
                        $results[$actorId] = ['success' => false, 'message' => 'لم يكن لقدرتك أي تأثير على الهدف.'];
                        continue;
                    }

                    if ($roleId === 'poisoner') {
                        // Mark as poisoned with 1 round delay (target survives tonight and tomorrow's day)
                        if (!in_array('POISONED', $target['statuses'] ?? [], true)) {
                            $target['statuses'][] = 'POISONED';
                        }
                        $room['poisonedPlayers'][$target['playerId']] = 1;
                        $results[$actorId] = ['success' => true, 'message' => "تم تسميم {$target['nickname']} بنجاح."];
                    }
                }
            }

            // Stage 60: Inspect & Eavesdrop (Investigator / Minister / Spy / Messenger / Forger)
            if ($stage === 60) {
                // 1st pass: Forger frames target
                foreach ($bucket as $entry) {
                    $action = $entry['action'];
                    $actorId = $entry['actorId'];
                    $roleId = $entry['roleId'];
                    $actor = &$playerMap[$actorId];
                    if (in_array('BLOCKED', $actor['statuses'] ?? [], true)) continue;

                    $targetId = $action['targetPlayerId'] ?? '';
                    if (!isset($playerMap[$targetId])) continue;
                    $target = &$playerMap[$targetId];

                    if ($roleId === 'forger') {
                        if (!in_array('FRAMED', $target['statuses'] ?? [], true)) {
                            $target['statuses'][] = 'FRAMED';
                        }
                        $results[$actorId] = ['success' => true, 'message' => "تم تزوير هوية {$target['nickname']}."];
                    }
                }

                // 2nd pass: Inspections & Comparisons
                foreach ($bucket as $entry) {
                    $action = $entry['action'];
                    $actorId = $entry['actorId'];
                    $roleId = $entry['roleId'];
                    $actor = &$playerMap[$actorId];
                    if (in_array('BLOCKED', $actor['statuses'] ?? [], true)) continue;

                    $targetIds = !empty($action['targetPlayerIds']) ? $action['targetPlayerIds'] : (!empty($action['targetPlayerId']) ? [$action['targetPlayerId']] : []);
                    $targetId = $targetIds[0] ?? '';
                    $target = $playerMap[$targetId] ?? null;

                    if ($roleId === 'investigator' && $target) {
                        // Hermit immunity check on rounds 1-2
                        if (($target['role'] ?? '') === 'hermit' && $round <= 2) {
                            $results[$actorId] = ['success' => false, 'message' => 'لم يتم العثور على أي معلومات حول هذا الهدف.'];
                            $intel[] = ['playerId' => $actorId, 'text' => "فحص {$target['nickname']}: محصن ضد التحقيق الليلة."];
                            continue;
                        }

                        $isFramed = in_array('FRAMED', $target['statuses'] ?? [], true);
                        $isShadow = $isFramed || ($target['faction'] ?? '') === 'shadow';
                        $report = $isShadow ? 'انتماء ظلامي (Shadow)' : 'انتماء مملكة (Kingdom)';
                        $intel[] = ['playerId' => $actorId, 'text' => "نتائج فحص {$target['nickname']}: {$report}"];
                        $results[$actorId] = ['success' => true, 'message' => "تقرير الفحص: {$report}"];
                    } elseif ($roleId === 'messenger') {
                        $chosenTargets = [];
                        foreach ($targetIds as $tid) {
                            if (isset($playerMap[$tid])) $chosenTargets[] = $playerMap[$tid];
                        }
                        if (count($chosenTargets) >= 2) {
                            $p1 = $chosenTargets[0];
                            $p2 = $chosenTargets[1];
                            $isSameTeam = ($p1['faction'] === $p2['faction']) && $p1['faction'] !== 'neutral';
                            $msgText = "نتائج الرسول: {$p1['nickname']} و {$p2['nickname']} " . ($isSameTeam ? 'ينتميان لنفس الفريق ✅' : 'ينتميان لفريقين مختلفين ❌');
                            $intel[] = ['playerId' => $actorId, 'text' => $msgText];
                            $results[$actorId] = ['success' => true, 'message' => $msgText];
                        } else {
                            $results[$actorId] = ['success' => false, 'message' => 'فشلت قدرة الرسول: يلزم تحديد هدفين صالحين للمقارنة.'];
                        }
                    } elseif ($roleId === 'spy' && $target) {
                        $visitors = [];
                        foreach ($actions as $a) {
                            $aTargets = !empty($a['targetPlayerIds']) ? $a['targetPlayerIds'] : (!empty($a['targetPlayerId']) ? [$a['targetPlayerId']] : []);
                            if (in_array($target['playerId'], $aTargets, true)) {
                                $vPlayer = $playerMap[$a['actorPlayerId'] ?? ''] ?? null;
                                $visitors[] = $vPlayer ? $vPlayer['nickname'] : 'مجهول';
                            }
                        }
                        $reportText = !empty($visitors) ? "الزوار لـ {$target['nickname']}: " . implode(', ', $visitors) : "لم يزر {$target['nickname']} أحد.";
                        $intel[] = ['playerId' => $actorId, 'text' => $reportText];
                        $results[$actorId] = ['success' => true, 'message' => $reportText];
                    }
                }

                // 3rd pass: Passive Minister Intelligence Generation (System chooses target automatically)
                foreach ($playerMap as $pId => $p) {
                    if (($p['role'] ?? '') === 'minister' && ($p['isAlive'] ?? true)) {
                        if (in_array('BLOCKED', $p['statuses'] ?? [], true)) {
                            $intel[] = ['playerId' => $pId, 'text' => 'تم اعتراض تقرير المستشارين لهذه الليلة بواسطة المخرب.'];
                            $results[$pId] = ['success' => false, 'message' => 'تم تعطيل وصول التقرير لك الليلة.'];
                            continue;
                        }

                        $candidates = [];
                        foreach ($playerMap as $candId => $cand) {
                            if ($candId !== $pId && ($cand['isAlive'] ?? true)) {
                                $candidates[] = $cand;
                            }
                        }

                        if (!empty($candidates)) {
                            $chosen = $candidates[array_rand($candidates)];
                            $f = $chosen['faction'] ?? RoleData::getFaction($chosen['role'] ?? '');
                            $fName = $f === 'shadow' ? 'الظلال' : ($f === 'neutral' ? 'المحايدون' : 'المملكة');
                            $fullReport = "تقرير المستشارين السري: اللاعب {$chosen['nickname']} ينتمي إلى فريق ({$fName}).";
                            $intel[] = ['playerId' => $pId, 'text' => $fullReport];
                            $results[$pId] = ['success' => true, 'message' => $fullReport];
                        }
                    }
                }
            }

            // Stage 70: Lethal Attacks (Assassin / Slasher)
            if ($stage === 70) {
                foreach ($bucket as $entry) {
                    $action = $entry['action'];
                    $actorId = $entry['actorId'];
                    $roleId = $entry['roleId'];
                    $actor = &$playerMap[$actorId];
                    if (in_array('BLOCKED', $actor['statuses'] ?? [], true)) continue;

                    $targetIds = !empty($action['targetPlayerIds']) ? $action['targetPlayerIds'] : (!empty($action['targetPlayerId']) ? [$action['targetPlayerId']] : []);

                    if ($roleId === 'slasher') {
                        foreach ($targetIds as $tid) {
                            if (isset($playerMap[$tid]) && ($playerMap[$tid]['isAlive'] ?? true)) {
                                if (($playerMap[$tid]['role'] ?? '') === 'hermit' && $round <= 2) {
                                    continue;
                                }
                                $pendingKills[] = ['attackerId' => $actorId, 'victimId' => $tid];
                            }
                        }
                        $results[$actorId] = ['success' => true, 'message' => "تم تنفيذ الهجوم على الأهداف المحددة."];
                    } elseif ($roleId === 'assassin' && !empty($targetIds)) {
                        $tid = $targetIds[0];
                        if (isset($playerMap[$tid]) && ($playerMap[$tid]['isAlive'] ?? true)) {
                            if (($playerMap[$tid]['role'] ?? '') === 'hermit' && $round <= 2) {
                                $results[$actorId] = ['success' => false, 'message' => "فشل الهجوم: الهدف محصن ضد الهجمات الليلة."];
                                continue;
                            }
                            $pendingKills[] = ['attackerId' => $actorId, 'victimId' => $tid];
                            $results[$actorId] = ['success' => true, 'message' => "تم تنفيذ هجومك على {$playerMap[$tid]['nickname']}."];
                        }
                    }
                }
            }

            // Stage 80: Heal & Cleanse (Doctor / Priest)
            if ($stage === 80) {
                foreach ($bucket as $entry) {
                    $action = $entry['action'];
                    $actorId = $entry['actorId'];
                    $roleId = $entry['roleId'];
                    $actor = &$playerMap[$actorId];
                    if (in_array('BLOCKED', $actor['statuses'] ?? [], true)) continue;

                    $targetId = $action['targetPlayerId'] ?? '';
                    if (!isset($playerMap[$targetId])) continue;
                    $target = &$playerMap[$targetId];

                    if ($roleId === 'doctor') {
                        $activeHeals[$target['playerId']] = true;
                        unset($room['poisonedPlayers'][$target['playerId']]);
                        $target['statuses'] = array_values(array_diff($target['statuses'] ?? [], ['POISONED', 'SILENCED', 'FRAMED']));
                        $results[$actorId] = ['success' => true, 'message' => "تم علاج {$target['nickname']}."];
                    } elseif ($roleId === 'priest') {
                        $target['statuses'] = array_values(array_diff($target['statuses'] ?? [], ['SILENCED', 'FRAMED']));
                        $results[$actorId] = ['success' => true, 'message' => "تم تطهير {$target['nickname']}."];
                    }
                }
            }

            // Stage 90: Apply Deaths (Lethal Attacks & Multi-Round Poison)
            if ($stage === 90) {
                foreach ($pendingKills as $kill) {
                    $victimId = $kill['victimId'];
                    if (!isset($playerMap[$victimId]) || !($playerMap[$victimId]['isAlive'] ?? true)) continue;
                    $victim = &$playerMap[$victimId];

                    $protections = $activeProtections[$victim['playerId']] ?? [];
                    $royalGuardProt = self::findProtectionType($protections, 'ROYAL_GUARD');
                    $guardProt = self::findProtectionType($protections, 'GUARD');
                    $knightProt = self::findProtectionType($protections, 'KNIGHT');
                    $hasDoctorHeal = isset($activeHeals[$victim['playerId']]);

                    if ($royalGuardProt) {
                        $saved[] = $victim;
                    } elseif ($guardProt) {
                        $saved[] = $victim;
                        // Consume Guard shield so second attack penetrates!
                        foreach ($activeProtections[$victim['playerId']] as $pIdx => $prot) {
                            if (($prot['type'] ?? '') === 'GUARD') {
                                unset($activeProtections[$victim['playerId']][$pIdx]);
                                break;
                            }
                        }
                    } elseif ($knightProt) {
                        $knightId = $knightProt['protectorId'] ?? '';
                        if (isset($playerMap[$knightId]) && ($playerMap[$knightId]['isAlive'] ?? true)) {
                            $knight = &$playerMap[$knightId];
                            $knight['isAlive'] = false;
                            $killed[] = $knight;
                            $saved[] = $victim;
                        }
                    } elseif ($hasDoctorHeal) {
                        $saved[] = $victim;
                    } else {
                        $victim['isAlive'] = false;
                        $killed[] = $victim;
                    }
                }

                // Process unhealed active poison across rounds (1-round delay)
                if (!empty($room['poisonedPlayers'])) {
                    foreach ($room['poisonedPlayers'] as $victimId => $remaining) {
                        if ($remaining > 0) {
                            // Target was poisoned tonight; survives tonight and next day!
                            $room['poisonedPlayers'][$victimId] = $remaining - 1;
                            continue;
                        }

                        // Delay expired: lethality applies unless healed by Doctor tonight
                        if (!isset($playerMap[$victimId]) || !($playerMap[$victimId]['isAlive'] ?? true)) {
                            unset($room['poisonedPlayers'][$victimId]);
                            continue;
                        }
                        $victim = &$playerMap[$victimId];
                        $hasDoctorHeal = isset($activeHeals[$victim['playerId']]);

                        if ($hasDoctorHeal) {
                            $saved[] = $victim;
                            unset($room['poisonedPlayers'][$victimId]);
                            $victim['statuses'] = array_values(array_diff($victim['statuses'] ?? [], ['POISONED']));
                        } else {
                            $victim['isAlive'] = false;
                            $victim['statuses'] = array_values(array_diff($victim['statuses'] ?? [], ['POISONED']));
                            $killed[] = $victim;
                            unset($room['poisonedPlayers'][$victimId]);
                        }
                    }
                }
            }

            // Stage 100: King Succession Check
            if ($stage === 100) {
                $kingAlive = false;
                $crownPrinceId = null;
                foreach ($playerMap as $pid => $p) {
                    if (($p['role'] ?? '') === 'king' && ($p['isAlive'] ?? true)) {
                        $kingAlive = true;
                    }
                    if (($p['role'] ?? '') === 'crown_prince' && ($p['isAlive'] ?? true)) {
                        $crownPrinceId = $pid;
                    }
                }

                if (!$kingAlive) {
                    if ($crownPrinceId && isset($playerMap[$crownPrinceId])) {
                        $crownPrince = &$playerMap[$crownPrinceId];
                        $crownPrince['role'] = 'king';
                        $intel[] = [
                            'playerId' => $crownPrinceId,
                            'text' => 'استشهد الملك! لقد توليت منصب العرش لحماية المملكة.'
                        ];
                    } else {
                        $room['vacantThrone'] = true;
                    }
                }
            }
        }

        // Clear transient status locks for next round
        foreach ($room['players'] as &$p) {
            $p['statuses'] = array_values(array_diff($p['statuses'] ?? [], ['BLOCKED']));
        }
        unset($p);

        return [
            'results' => $results,
            'intel' => $intel,
            'killed' => $killed,
            'saved' => $saved,
            'silenced' => $silenced,
            'blockedActors' => $blockedActors,
            'recommendation' => $recommendation,
        ];
    }

    public static function getRoleStagePriority(string $roleId): int
    {
        switch ($roleId) {
            case 'saboteur':
            case 'shadow_leader': return 10;
            case 'impersonator':   return 20;
            case 'guard':
            case 'royal_guard':
            case 'knight':         return 30;
            case 'wizard':        return 40;
            case 'poisoner':      return 50;
            case 'investigator':
            case 'minister':
            case 'spy':
            case 'messenger':
            case 'forger':        return 60;
            case 'assassin':
            case 'slasher':       return 70;
            case 'doctor':
            case 'priest':        return 80;
            default:              return 90;
        }
    }

    private static function findProtectionType(array $protections, string $type): ?array
    {
        foreach ($protections as $p) {
            if (($p['type'] ?? '') === $type) {
                return $p;
            }
        }
        return null;
    }
}
