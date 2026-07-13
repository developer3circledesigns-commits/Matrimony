<?php
namespace Matrimony\Services;

use Matrimony\Database\Connection;

final class MatchEngine
{
    private array $weights = [
        'age'          => 20,
        'height'       => 8,
        'religion'     => 15,
        'caste'        => 7,
        'mother_tongue'=> 5,
        'education'    => 10,
        'income'       => 5,
        'location'     => 10,
        'diet'         => 4,
        'marital_status'=> 6,
        'verified'     => 5,
        'active'       => 3,
        'mutual'       => 2,
    ];

    private function decodePref($value): array
    {
        if (empty($value)) return [];
        if (is_array($value)) return $value;
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [$value];
    }

    public function compute(int $viewerId, array $viewerProfile, array $targetProfile, array $preferences): int
    {
        $score = 0;

        if (!empty($preferences['min_age']) && !empty($preferences['max_age'])) {
            $targetAge = $this->calcAge($targetProfile['date_of_birth']);
            $score += $this->scoreRange($targetAge, (int) $preferences['min_age'], (int) $preferences['max_age']) * 0.2;
        } else {
            $score += 10;
        }

        $prefRel = $this->decodePref($preferences['pref_religion'] ?? null);
        if (!empty($prefRel)) {
            if (in_array($targetProfile['religion'] ?? '', $prefRel, true)) $score += 15;
        } else {
            $score += 7;
        }

        $prefCaste = $this->decodePref($preferences['pref_caste'] ?? null);
        if (!empty($prefCaste)) {
            if (in_array($targetProfile['caste'] ?? '', $prefCaste, true)) $score += 7;
        } else {
            $score += 3;
        }

        $prefTongue = $this->decodePref($preferences['pref_mother_tongue'] ?? null);
        if (!empty($prefTongue)) {
            if (in_array($targetProfile['mother_tongue'] ?? '', $prefTongue, true)) $score += 5;
        } else {
            $score += 2;
        }

        $prefEdu = $this->decodePref($preferences['pref_education'] ?? null);
        if (!empty($prefEdu)) {
            if (in_array($targetProfile['education'] ?? '', $prefEdu, true)) $score += 10;
        } else {
            $score += 5;
        }

        if (!empty($preferences['pref_income_min'])) {
            $targetIncome = (int) preg_replace('/[^0-9]/', '', $targetProfile['annual_income'] ?? '0');
            $minIncome = (int) preg_replace('/[^0-9]/', '', $preferences['pref_income_min']);
            if ($targetIncome >= $minIncome) $score += 5;
        } else {
            $score += 2;
        }

        $prefLoc = $this->decodePref($preferences['pref_location'] ?? null);
        if (!empty($prefLoc)) {
            $targetCity = $targetProfile['city'] ?? '';
            $targetState = $targetProfile['state'] ?? '';
            if (in_array($targetCity, $prefLoc, true) || in_array($targetState, $prefLoc, true)) {
                $score += 10;
            }
        } else {
            if (($viewerProfile['state'] ?? '') === ($targetProfile['state'] ?? '')) $score += 5;
            if (($viewerProfile['city'] ?? '') === ($targetProfile['city'] ?? '')) $score += 5;
        }

        $prefMs = $this->decodePref($preferences['pref_marital_status'] ?? null);
        if (!empty($prefMs)) {
            if (in_array($targetProfile['marital_status'] ?? '', $prefMs, true)) $score += 6;
        } else {
            $score += 3;
        }

        if (!empty($targetProfile['diet']) && !empty($viewerProfile['diet']) && $targetProfile['diet'] === $viewerProfile['diet']) {
            $score += 4;
        }

        if (!empty($preferences['min_height_cm']) && !empty($preferences['max_height_cm'])) {
            $targetH = (int) ($targetProfile['height_cm'] ?? 0);
            if ($targetH >= (int) $preferences['min_height_cm'] && $targetH <= (int) $preferences['max_height_cm']) {
                $score += 8;
            }
        } else {
            $score += 4;
        }

        if (!empty($targetProfile['is_verified'])) $score += 5;

        if (!empty($targetProfile['last_login_at'])) {
            $lastActive = strtotime($targetProfile['last_login_at']);
            if ($lastActive && (time() - $lastActive) < 7 * 86400) $score += 3;
        }

        return min(100, $score);
    }

    public function getWeights(): array
    {
        return $this->weights;
    }

    public function calcAge(?string $dob): int
    {
        if ($dob === null || $dob === '' || $dob === '0000-00-00') return 0;
        $birth = new \DateTime($dob);
        $now = new \DateTime();
        return (int) $now->diff($birth)->y;
    }

    private function scoreRange(int $value, int $min, int $max): int
    {
        if ($value < $min || $value > $max) return 0;
        $mid = ($min + $max) / 2;
        $dist = abs($value - $mid);
        $range = ($max - $min) / 2;
        if ($range <= 0) return 100;
        return max(20, (int) (100 - ($dist / $range) * 80));
    }
}
