<?php
declare(strict_types=1);

namespace Tests\Unit;

use Matrimony\Services\ProfileService;
use Tests\TestCase;

final class ProfileServiceCompletionTest extends TestCase
{
    private ProfileService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ProfileService();
    }

    public function test_calculate_completion_empty_profile_returns_zero(): void
    {
        $profile = [];
        $pct = $this->invokePrivateMethod($this->service, 'calculateCompletion', [$profile]);
        $this->assertEquals(0, $pct);
    }

    public function test_calculate_completion_full_profile_returns_one_hundred(): void
    {
        $profile = [
            'first_name' => 'Raj', 'last_name' => 'Sharma',
            'gender' => 'male', 'date_of_birth' => '1995-06-15',
            'marital_status' => 'never_married', 'religion' => 'Hindu',
            'mother_tongue' => 'Hindi', 'height_cm' => '175',
            'education' => 'Masters', 'occupation' => 'Engineer',
            'city' => 'Mumbai', 'about_me' => 'Hello',
            'institution' => 'IIT', 'company' => 'Google',
            'annual_income' => '1200000',
            'primary_photo' => '/uploads/1/photo.jpg',
            'father_name' => 'Mr. Sharma', 'mother_name' => 'Mrs. Sharma',
            'family_type' => 'nuclear', 'family_values' => 'liberal',
            'preferences' => [
                'min_age' => 24, 'max_age' => 30,
                'pref_religion' => '["Hindu"]', 'pref_caste' => '["Brahmin"]',
            ],
        ];
        $pct = $this->invokePrivateMethod($this->service, 'calculateCompletion', [$profile]);
        $this->assertEquals(100, $pct);
    }

    public function test_calculate_completion_half_profile_returns_around_fifty(): void
    {
        $profile = [
            'first_name' => 'Raj', 'last_name' => 'Sharma',
            'gender' => 'male', 'date_of_birth' => '1995-06-15',
            'marital_status' => 'never_married',
            'city' => 'Mumbai',
            'education' => 'Masters', 'occupation' => 'Engineer',
        ];
        $pct = $this->invokePrivateMethod($this->service, 'calculateCompletion', [$profile]);
        $this->assertGreaterThan(20, $pct);
        $this->assertLessThan(80, $pct);
    }

    public function test_calculate_completion_photo_bonus_applied(): void
    {
        $profile = [
            'first_name' => 'Raj', 'last_name' => 'Sharma',
            'gender' => 'male', 'date_of_birth' => '1995-06-15',
            'marital_status' => 'never_married', 'religion' => 'Hindu',
            'mother_tongue' => 'Hindi', 'height_cm' => '175',
            'education' => 'Masters', 'occupation' => 'Engineer',
            'city' => 'Mumbai', 'about_me' => 'Hello',
            'primary_photo' => '/uploads/1/photo.jpg',
        ];

        $withPhoto = $this->invokePrivateMethod($this->service, 'calculateCompletion', [$profile]);

        $profileNoPhoto = $profile;
        unset($profileNoPhoto['primary_photo']);
        $withoutPhoto = $this->invokePrivateMethod($this->service, 'calculateCompletion', [$profileNoPhoto]);

        $this->assertGreaterThan($withoutPhoto, $withPhoto);
    }

    public function test_calculate_completion_family_partial(): void
    {
        $profile = [
            'first_name' => 'Raj', 'last_name' => 'Sharma',
            'gender' => 'male', 'date_of_birth' => '1995-06-15',
            'marital_status' => 'never_married', 'religion' => 'Hindu',
            'mother_tongue' => 'Hindi', 'height_cm' => '175',
            'education' => 'Masters', 'occupation' => 'Engineer',
            'city' => 'Mumbai', 'about_me' => 'Hello',
            'primary_photo' => '/uploads/1/photo.jpg',
            'institution' => 'IIT', 'company' => 'Google',
            'annual_income' => '1200000',
            'father_name' => 'Mr. Sharma',
            'preferences' => [
                'min_age' => 24, 'max_age' => 30,
                'pref_religion' => '["Hindu"]',
            ],
        ];
        $pct = $this->invokePrivateMethod($this->service, 'calculateCompletion', [$profile]);
        // Should be high but not 100% because mother_name, family_type, family_values, pref_caste missing
        $this->assertGreaterThan(70, $pct);
        $this->assertLessThan(100, $pct);
    }

    public function test_get_completion_fields_structure(): void
    {
        $profile = [
            'first_name' => 'Raj', 'last_name' => 'Sharma',
            'gender' => 'male', 'date_of_birth' => '1995-06-15',
            'marital_status' => 'never_married',
        ];
        $fields = $this->invokePrivateMethod($this->service, 'getCompletionFields', [$profile]);

        $this->assertIsArray($fields);
        $this->assertNotEmpty($fields);

        // Basic Info should be complete (all required fields provided)
        $basicInfo = array_values(array_filter($fields, fn($f) => $f['group'] === 'Basic Info'))[0] ?? null;
        $this->assertNotNull($basicInfo);
        $this->assertTrue($basicInfo['done']);

        // Photo should be incomplete
        $photoSection = array_values(array_filter($fields, fn($f) => $f['group'] === 'Photo'))[0] ?? null;
        $this->assertNotNull($photoSection);
        $this->assertFalse($photoSection['done']);
    }

    public function test_calc_age_empty_returns_zero(): void
    {
        $this->assertEquals(0, $this->service->calcAge(''));
    }

    public function test_calc_age_valid_dob(): void
    {
        $age = $this->service->calcAge('1990-06-15');
        $expected = (int) (new \DateTime())->diff(new \DateTime('1990-06-15'))->y;
        $this->assertEquals($expected, $age);
    }
}
