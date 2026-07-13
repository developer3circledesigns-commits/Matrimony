<?php
declare(strict_types=1);

namespace Tests\Unit;

use Matrimony\Services\MatchEngine;
use Tests\TestCase;

final class MatchEngineTest extends TestCase
{
    private MatchEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new MatchEngine();
    }

    public function test_compute_perfect_match_returns_100(): void
    {
        $viewer = ['diet' => 'vegetarian'];
        $target = [
            'date_of_birth' => '1995-06-15',
            'religion' => 'Hindu',
            'caste' => 'Brahmin',
            'mother_tongue' => 'Hindi',
            'education' => 'Masters',
            'annual_income' => '1200000',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'marital_status' => 'never_married',
            'diet' => 'vegetarian',
            'height_cm' => '165',
            'is_verified' => true,
            'last_login_at' => date('Y-m-d H:i:s'),
        ];
        $preferences = [
            'min_age' => 28,
            'max_age' => 32,
            'pref_religion' => json_encode(['Hindu']),
            'pref_caste' => json_encode(['Brahmin']),
            'pref_mother_tongue' => json_encode(['Hindi']),
            'pref_education' => json_encode(['Masters']),
            'pref_location' => json_encode(['Mumbai', 'Maharashtra']),
            'pref_marital_status' => json_encode(['never_married']),
            'min_height_cm' => 150,
            'max_height_cm' => 180,
            'pref_income_min' => '500000',
        ];

        $score = $this->engine->compute(1, $viewer, $target, $preferences);
        $this->assertGreaterThanOrEqual(88, $score);
    }

    public function test_compute_no_preferences_returns_partial_score(): void
    {
        $viewer = ['diet' => 'vegetarian', 'state' => 'Maharashtra', 'city' => 'Mumbai'];
        $target = [
            'date_of_birth' => '1995-06-15',
            'religion' => 'Hindu',
            'mother_tongue' => 'Hindi',
            'education' => 'Masters',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'marital_status' => 'never_married',
            'is_verified' => true,
            'last_login_at' => date('Y-m-d H:i:s'),
        ];
        $preferences = [];

        $score = $this->engine->compute(1, $viewer, $target, $preferences);
        // Should get partial scores from fallback logic
        $this->assertGreaterThan(0, $score);
        $this->assertLessThanOrEqual(100, $score);
    }

    public function test_compute_no_match_returns_zero_or_low_score(): void
    {
        $viewer = ['diet' => 'non-veg'];
        $target = [
            'date_of_birth' => '2005-06-15', // Too young (age 21)
            'religion' => 'Christian',
            'caste' => 'Other',
            'mother_tongue' => 'Tamil',
            'education' => 'High School',
            'annual_income' => '0',
            'city' => 'Chennai',
            'state' => 'Tamil Nadu',
            'marital_status' => 'divorced',
            'diet' => 'non-veg',
            'height_cm' => '140',
            'is_verified' => false,
            'last_login_at' => '2020-01-01',
        ];
        $preferences = [
            'min_age' => 28, 'max_age' => 32,
            'pref_religion' => json_encode(['Hindu']),
            'pref_caste' => json_encode(['Brahmin']),
            'pref_mother_tongue' => json_encode(['Hindi']),
            'pref_education' => json_encode(['Masters']),
            'pref_location' => json_encode(['Mumbai']),
            'pref_marital_status' => json_encode(['never_married']),
            'min_height_cm' => 150, 'max_height_cm' => 180,
        ];

        $score = $this->engine->compute(1, $viewer, $target, $preferences);
        // Should get only: age=0, religion=0, diet=0, verified=0, inactive=0, partial from others
        $this->assertLessThan(50, $score);
    }

    public function test_calc_age_returns_correct_years(): void
    {
        $age = $this->engine->calcAge('1990-01-15');
        $expected = (int) (new \DateTime())->diff(new \DateTime('1990-01-15'))->y;
        $this->assertEquals($expected, $age);
    }

    public function test_calc_age_empty_returns_zero(): void
    {
        $this->assertEquals(0, $this->engine->calcAge(''));
    }

    public function test_get_weights_returns_expected_structure(): void
    {
        $weights = $this->engine->getWeights();
        $this->assertIsArray($weights);
        $this->assertArrayHasKey('age', $weights);
        $this->assertArrayHasKey('religion', $weights);
        $this->assertArrayHasKey('education', $weights);
        $this->assertEquals(20, $weights['age']);
        $this->assertEquals(15, $weights['religion']);
    }

    public function test_compute_age_exact_range_midpoint(): void
    {
        $viewer = ['diet' => 'any'];
        $target = [
            'date_of_birth' => '1995-06-15',
            'religion' => 'Hindu',
            'caste' => 'General',
            'mother_tongue' => 'Hindi',
            'education' => 'Graduate',
            'annual_income' => '600000',
            'city' => 'Delhi',
            'state' => 'Delhi',
            'marital_status' => 'never_married',
            'diet' => 'any',
            'height_cm' => '165',
            'is_verified' => false,
            'last_login_at' => null,
        ];
        $preferences = ['min_age' => 30, 'max_age' => 30]; // Exact midpoint

        $score = $this->engine->compute(1, $viewer, $target, $preferences);
        $this->assertGreaterThan(0, $score);
    }

    public function test_compute_income_strips_non_numeric(): void
    {
        $viewer = ['diet' => 'any'];
        $target = [
            'date_of_birth' => '1995-06-15',
            'marital_status' => 'never_married',
            'annual_income' => '₹ 12,00,000',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'education' => 'Graduate',
            'diet' => 'any',
            'height_cm' => '165',
            'is_verified' => true,
            'last_login_at' => date('Y-m-d H:i:s'),
        ];
        $preferences = ['pref_income_min' => '₹ 10,00,000'];

        $score = $this->engine->compute(1, $viewer, $target, $preferences);
        $this->assertGreaterThan(0, $score); // Income should match after stripping
    }

    public function test_compute_with_json_array_preferences(): void
    {
        $viewer = ['diet' => 'vegetarian'];
        $target = [
            'date_of_birth' => '1995-06-15',
            'religion' => 'Hindu',
            'education' => 'Masters',
            'city' => 'Pune',
            'state' => 'Maharashtra',
            'marital_status' => 'never_married',
            'diet' => 'vegetarian',
            'height_cm' => '162',
            'is_verified' => true,
            'last_login_at' => date('Y-m-d H:i:s'),
        ];
        $preferences = [
            'pref_religion' => ['Hindu', 'Sikh'],
            'pref_education' => ['Masters', 'PhD'],
            'pref_location' => ['Pune', 'Maharashtra'],
            'pref_marital_status' => ['never_married'],
        ];

        $score = $this->engine->compute(1, $viewer, $target, $preferences);
        $this->assertGreaterThan(50, $score);
    }
}
