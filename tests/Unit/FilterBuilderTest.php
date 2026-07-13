<?php
declare(strict_types=1);

namespace Tests\Unit;

use Matrimony\Services\FilterBuilder;
use Tests\TestCase;

final class FilterBuilderTest extends TestCase
{
    private FilterBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new FilterBuilder();
    }

    public function test_build_with_empty_filters_excludes_self_declined_blocked(): void
    {
        $result = $this->builder->build([], 1, 'male');
        $this->assertStringContainsString('p.gender = :opp_gender', $result['where']);
        $this->assertEquals('female', $result['params'][':opp_gender']);

        // Always exclude viewer, declined, blocked
        $this->assertStringContainsString('p.user_id != :me', $result['where']);
        $this->assertStringContainsString('declined', $result['where']);
        $this->assertStringContainsString('blocked', $result['where']);
        $this->assertStringContainsString('profile_blocks', $result['where']);
        $this->assertArrayHasKey(':me', $result['params']);
    }

    public function test_build_with_age_filter(): void
    {
        $result = $this->builder->build(['age_min' => 24, 'age_max' => 30], 1, 'female');
        $this->assertStringContainsString('age_min', $result['where']);
        $this->assertStringContainsString('age_max', $result['where']);
        $this->assertEquals(24, $result['params'][':age_min']);
        $this->assertEquals(30, $result['params'][':age_max']);
        $this->assertEquals('male', $result['params'][':opp_gender']);
    }

    public function test_build_with_religion_and_caste(): void
    {
        $result = $this->builder->build([
            'religion' => ['Hindu', 'Sikh'],
            'caste' => ['Brahmin', 'Kshatriya'],
        ], 1, 'male');

        $this->assertStringContainsString('p.religion IN', $result['where']);
        $this->assertStringContainsString('p.caste IN', $result['where']);
        $this->assertEquals('Hindu', $result['params'][':rel_0']);
        $this->assertEquals('Sikh', $result['params'][':rel_1']);
        $this->assertEquals('Brahmin', $result['params'][':cas_0']);
        $this->assertEquals('Kshatriya', $result['params'][':cas_1']);
    }

    public function test_build_with_height_range(): void
    {
        $result = $this->builder->build(['height_min_cm' => 150, 'height_max_cm' => 175], 1, 'male');
        $this->assertStringContainsString('height_cm >= :hmin', $result['where']);
        $this->assertStringContainsString('height_cm <= :hmax', $result['where']);
        $this->assertEquals(150, $result['params'][':hmin']);
        $this->assertEquals(175, $result['params'][':hmax']);
    }

    public function test_build_with_mother_tongue(): void
    {
        $result = $this->builder->build(['mother_tongue' => ['Hindi', 'Punjabi']], 1, 'male');
        $this->assertStringContainsString('mother_tongue IN', $result['where']);
        $this->assertEquals('Hindi', $result['params'][':mt_0']);
    }

    public function test_build_with_education(): void
    {
        $result = $this->builder->build(['education' => ['Masters', 'PhD']], 1, 'male');
        $this->assertStringContainsString('education IN', $result['where']);
    }

    public function test_build_with_marital_status(): void
    {
        $result = $this->builder->build(['marital_status' => ['never_married', 'divorced']], 1, 'male');
        $this->assertStringContainsString('marital_status IN', $result['where']);
    }

    public function test_build_with_income_range(): void
    {
        $result = $this->builder->build([
            'income_min' => '₹ 5,00,000',
            'income_max' => '₹ 20,00,000',
        ], 1, 'male');

        $this->assertStringContainsString('annual_income AS UNSIGNED) >= :inc_min', $result['where']);
        $this->assertStringContainsString('annual_income AS UNSIGNED) <= :inc_max', $result['where']);
        $this->assertEquals(500000, $result['params'][':inc_min']);
        $this->assertEquals(2000000, $result['params'][':inc_max']);
    }

    public function test_build_with_location(): void
    {
        $result = $this->builder->build([
            'country' => ['India'],
            'state' => ['Maharashtra', 'Gujarat'],
            'city' => ['Mumbai', 'Pune'],
        ], 1, 'male');

        $this->assertStringContainsString('p.country IN', $result['where']);
        $this->assertStringContainsString('p.state IN', $result['where']);
        $this->assertStringContainsString('p.city IN', $result['where']);
    }

    public function test_build_with_diet_smoke_drink(): void
    {
        $result = $this->builder->build([
            'diet' => ['vegetarian', 'vegan'],
            'smoke' => ['no'],
            'drink' => ['no'],
        ], 1, 'male');

        $this->assertNotEmpty($result['joins']);
        $joinStr = implode(' ', $result['joins']);
        $this->assertStringContainsString('profile_assets pa_diet', $joinStr);
        $this->assertStringContainsString('profile_assets pa_smoke', $joinStr);
        $this->assertStringContainsString('profile_assets pa_drink', $joinStr);
        $this->assertStringContainsString('pa_diet.diet IN', $result['where']);
        $this->assertStringContainsString('pa_smoke.smoke IN', $result['where']);
        $this->assertStringContainsString('pa_drink.drink IN', $result['where']);
    }

    public function test_build_with_has_children(): void
    {
        $result = $this->builder->build(['has_children' => 'no'], 1, 'male');
        $this->assertStringContainsString('p.has_children = :has_children', $result['where']);
    }

    public function test_build_with_distance_radius(): void
    {
        $result = $this->builder->build([
            'latitude' => 19.0760,
            'longitude' => 72.8777,
            'radius_km' => 50,
        ], 1, 'male');

        $this->assertStringContainsString('profile_lifestyle pl_dist', implode(' ', $result['joins']));
        $this->assertStringContainsString('6371 * ACOS', $result['where']);
        $this->assertEquals(19.0760, $result['params'][':dist_lat']);
        $this->assertEquals(50, $result['params'][':dist_radius']);
    }

    public function test_build_with_photo_required(): void
    {
        $result = $this->builder->build(['photo_required' => true], 1, 'male');
        $this->assertStringContainsString('EXISTS (SELECT 1 FROM profile_photos', $result['where']);
    }

    public function test_build_with_verified_only(): void
    {
        $result = $this->builder->build(['verified_only' => true], 1, 'male');
        $this->assertStringContainsString('u.is_verified = 1', $result['where']);
    }

    public function test_build_with_recently_active(): void
    {
        $result = $this->builder->build(['recently_active_days' => 7], 1, 'male');
        $this->assertStringContainsString('last_login_at >= DATE_SUB(NOW(), INTERVAL :recent_d DAY)', $result['where']);
        $this->assertEquals(7, $result['params'][':recent_d']);
    }

    public function test_build_with_family_filters(): void
    {
        $result = $this->builder->build([
            'family_type' => 'nuclear',
            'family_values' => 'liberal',
        ], 1, 'male');

        $joinStr = implode(' ', $result['joins']);
        $this->assertStringContainsString('profile_family pf_fam', $joinStr);
        $this->assertStringContainsString('profile_family pf_val', $joinStr);
        $this->assertStringContainsString('pf_fam.family_type = :fam_type', $result['where']);
        $this->assertStringContainsString('pf_val.family_values = :fam_val', $result['where']);
    }

    public function test_build_with_created_by(): void
    {
        $result = $this->builder->build(['created_by' => ['self', 'parent']], 1, 'male');
        $this->assertStringContainsString('p.created_by IN', $result['where']);
    }

    public function test_build_with_sub_caste(): void
    {
        $result = $this->builder->build(['sub_caste' => 'Gaud'], 1, 'male');
        $this->assertStringContainsString('p.sub_caste = :sub_caste', $result['where']);
        $this->assertEquals('Gaud', $result['params'][':sub_caste']);
    }

    public function test_build_with_residency_status(): void
    {
        $result = $this->builder->build(['residency_status' => ['citizen', 'pr']], 1, 'male');
        $this->assertStringContainsString('profile_lifestyle pl', implode(' ', $result['joins']));
        $this->assertStringContainsString('pl.residency_status IN', $result['where']);
    }

    public function test_build_with_willing_to_relocate(): void
    {
        $result = $this->builder->build(['willing_to_relocate' => true], 1, 'male');
        $this->assertStringContainsString('pl_reloc.willing_to_relocate = 1', $result['where']);
    }

    public function test_get_order_by_default(): void
    {
        $this->assertStringContainsString('last_login_at DESC', $this->builder->getOrderBy(''));
        $this->assertStringContainsString('last_login_at DESC', $this->builder->getOrderBy('invalid'));
    }

    public function test_get_order_by_recently_joined(): void
    {
        $order = $this->builder->getOrderBy('recently_joined');
        $this->assertStringContainsString('created_at DESC', $order);
    }

    public function test_get_order_by_newest_first(): void
    {
        $order = $this->builder->getOrderBy('newest_first');
        $this->assertStringContainsString('created_at DESC', $order);
    }

    public function test_get_order_by_viewed_me(): void
    {
        $order = $this->builder->getOrderBy('viewed_me');
        $this->assertStringContainsString('profile_views', $order);
    }

    public function test_opposite_gender(): void
    {
        $this->assertEquals('female', FilterBuilder::oppositeGender('male'));
        $this->assertEquals('male', FilterBuilder::oppositeGender('female'));
    }

    public function test_build_with_body_type_and_complexion(): void
    {
        $result = $this->builder->build([
            'body_type' => ['slim', 'athletic'],
            'complexion' => ['fair'],
        ], 1, 'male');

        $joinStr = implode(' ', $result['joins']);
        $this->assertStringContainsString('pa_body.body_type IN', $result['where']);
        $this->assertStringContainsString('pa_comp.complexion IN', $result['where']);
        $this->assertStringContainsString('profile_assets pa_body', $joinStr);
        $this->assertStringContainsString('profile_assets pa_comp', $joinStr);
    }

    public function test_build_female_viewer_returns_male_gender(): void
    {
        $result = $this->builder->build([], 1, 'female');
        $this->assertEquals('male', $result['params'][':opp_gender']);
    }

    public function test_build_with_new_profiles(): void
    {
        $result = $this->builder->build(['new_profiles' => '1'], 1, 'male');
        $this->assertStringContainsString('p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)', $result['where']);
    }

    public function test_build_new_profiles_empty_skips_filter(): void
    {
        $result = $this->builder->build(['new_profiles' => ''], 1, 'male');
        $this->assertStringNotContainsString('created_at >= DATE_SUB', $result['where']);
    }

    public function test_build_with_premium_only(): void
    {
        $result = $this->builder->build(['premium_only' => '1'], 1, 'male');
        $joinStr = implode(' ', $result['joins']);
        $this->assertStringContainsString('memberships m_prem', $joinStr);
        $this->assertStringContainsString('membership_plans mp_prem', $joinStr);
        $this->assertStringContainsString('mp_prem.code IS NOT NULL', $result['where']);
        $this->assertStringContainsString('mp_prem.code !=', $result['where']);
    }

    public function test_build_premium_only_empty_skips_filter(): void
    {
        $result = $this->builder->build(['premium_only' => ''], 1, 'male');
        $joinStr = implode(' ', $result['joins']);
        $this->assertStringNotContainsString('m_prem', $joinStr);
        $this->assertStringNotContainsString('mp_prem', $joinStr);
    }
}
