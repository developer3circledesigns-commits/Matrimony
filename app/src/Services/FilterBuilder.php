<?php
namespace Matrimony\Services;

use Matrimony\Database\Connection;

final class FilterBuilder
{
    private array $where = [];
    private array $params = [];
    private array $joins = [];
    private array $joinAliases = [];

    public function build(array $filters, int $viewerId, string $viewerGender): array
    {
        $this->where = ['p.gender = :opp_gender'];
        $this->params = [':opp_gender' => $viewerGender === 'male' ? 'female' : 'male'];
        $this->joins = [];
        $this->joinAliases = [];

        $this->addAgeFilter($filters);
        $this->addHeightFilter($filters);
        $this->addMaritalStatus($filters);
        $this->addReligion($filters);
        $this->addCaste($filters);
        $this->addMotherTongue($filters);
        $this->addEducation($filters);
        $this->addOccupation($filters);
        $this->addIncome($filters);
        $this->addLocation($filters);
        $this->addDiet($filters);
        $this->addSmoke($filters);
        $this->addDrink($filters);
        $this->addBodyType($filters);
        $this->addComplexion($filters);
        $this->addHasChildren($filters);
        $this->addCreatedBy($filters);
        $this->addPhotoRequired($filters);
        $this->addVerifiedOnly($filters);
        $this->addRecentlyActive($filters);
        $this->addNewProfiles($filters);
        $this->addPremiumOnly($filters);
        $this->addEmployedIn($filters);
        $this->addFamilyType($filters);
        $this->addFamilyValues($filters);
        $this->addSearch($filters);
        $this->addDistanceRadius($filters);

        $this->excludeViewer($viewerId);

        return [
            'where' => implode(' AND ', $this->where),
            'params' => $this->params,
            'joins' => $this->joins,
        ];
    }

    private function addJoin(string $alias, string $joinSql): void
    {
        if (!isset($this->joinAliases[$alias])) {
            $this->joinAliases[$alias] = true;
            $this->joins[] = $joinSql;
        }
    }

    private function addFilter(string $clause, array $params): void
    {
        $this->where[] = $clause;
        foreach ($params as $k => $v) {
            $this->params[$k] = $v;
        }
    }

    private function addInClause(string $column, array $values, string $prefix, ?string $table = null): void
    {
        if (empty($values)) return;
        $col = $table ? "$table.$column" : "p.$column";
        $placeholders = [];
        foreach ($values as $i => $v) {
            $key = ":{$prefix}_{$i}";
            $placeholders[] = $key;
            $this->params[$key] = $v;
        }
        $this->where[] = "$col IN (" . implode(',', $placeholders) . ')';
    }

    private function addAgeFilter(array $f): void
    {
        if (!empty($f['age_min'])) {
            $this->addFilter('TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) >= :age_min', [':age_min' => (int) $f['age_min']]);
        }
        if (!empty($f['age_max'])) {
            $this->addFilter('TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) <= :age_max', [':age_max' => (int) $f['age_max']]);
        }
    }

    private function addHeightFilter(array $f): void
    {
        if (!empty($f['height_min_cm'])) {
            $this->addFilter('p.height_cm >= :hmin', [':hmin' => (int) $f['height_min_cm']]);
        }
        if (!empty($f['height_max_cm'])) {
            $this->addFilter('p.height_cm <= :hmax', [':hmax' => (int) $f['height_max_cm']]);
        }
    }

    private function addMaritalStatus(array $f): void
    {
        $this->addInClause('marital_status', $f['marital_status'] ?? [], 'ms');
    }

    private function addReligion(array $f): void
    {
        $this->addInClause('religion', $f['religion'] ?? [], 'rel');
    }

    private function addCaste(array $f): void
    {
        $this->addInClause('caste', $f['caste'] ?? [], 'cas');
        if (!empty($f['sub_caste'])) {
            $this->addFilter('p.sub_caste = :sub_caste', [':sub_caste' => $f['sub_caste']]);
        }
    }

    private function addMotherTongue(array $f): void
    {
        $this->addInClause('mother_tongue', $f['mother_tongue'] ?? [], 'mt');
    }

    private function addEducation(array $f): void
    {
        $this->addInClause('education', $f['education'] ?? [], 'edu');
    }

    private function addOccupation(array $f): void
    {
        $this->addInClause('occupation', $f['occupation'] ?? [], 'occ');
    }

    private function addIncome(array $f): void
    {
        if (!empty($f['income_min'])) {
            $min = (int) preg_replace('/[^0-9]/', '', $f['income_min']);
            $this->addFilter('CAST(p.annual_income AS UNSIGNED) >= :inc_min', [':inc_min' => $min]);
        }
        if (!empty($f['income_max'])) {
            $max = (int) preg_replace('/[^0-9]/', '', $f['income_max']);
            $this->addFilter('CAST(p.annual_income AS UNSIGNED) <= :inc_max', [':inc_max' => $max]);
        }
        if (!empty($f['employed_in'])) {
            $this->addInClause('occupation', $f['employed_in'], 'emp');
        }
    }

    private function addLocation(array $f): void
    {
        $this->addInClause('country', $f['country'] ?? [], 'cnt');
        $this->addInClause('state', $f['state'] ?? [], 'st');
        $this->addInClause('city', $f['city'] ?? [], 'ct');
        if (!empty($f['residency_status'])) {
            $this->addJoin('pl', 'LEFT JOIN profile_lifestyle pl ON pl.user_id = p.user_id');
            $this->addInClause('residency_status', $f['residency_status'], 'res', 'pl');
        }
        if (!empty($f['willing_to_relocate'])) {
            $this->addFilter('pl_reloc.willing_to_relocate = 1', []);
        }
    }

    private function addDistanceRadius(array $f): void
    {
        if (!empty($f['latitude']) && !empty($f['longitude']) && !empty($f['radius_km'])) {
            $this->addJoin('pl_dist', 'INNER JOIN profile_lifestyle pl_dist ON pl_dist.user_id = p.user_id');
            $lat = (float) $f['latitude'];
            $lng = (float) $f['longitude'];
            $radius = (int) $f['radius_km'];
            $this->where[] = "(
                6371 * ACOS(COS(RADIANS(:dist_lat)) * COS(RADIANS(pl_dist.latitude))
                * COS(RADIANS(pl_dist.longitude) - RADIANS(:dist_lng))
                + SIN(RADIANS(:dist_lat2)) * SIN(RADIANS(pl_dist.latitude)))
            ) <= :dist_radius";
            $this->params[':dist_lat'] = $lat;
            $this->params[':dist_lng'] = $lng;
            $this->params[':dist_lat2'] = $lat;
            $this->params[':dist_radius'] = $radius;
        }
    }

    private function addDiet(array $f): void
    {
        if (!empty($f['diet'])) {
            $this->addJoin('pa_diet', 'LEFT JOIN profile_assets pa_diet ON pa_diet.user_id = p.user_id');
            $this->addInClause('diet', $f['diet'], 'diet', 'pa_diet');
        }
    }

    private function addSmoke(array $f): void
    {
        if (!empty($f['smoke'])) {
            $this->addJoin('pa_smoke', 'LEFT JOIN profile_assets pa_smoke ON pa_smoke.user_id = p.user_id');
            $this->addInClause('smoke', $f['smoke'], 'smk', 'pa_smoke');
        }
    }

    private function addDrink(array $f): void
    {
        if (!empty($f['drink'])) {
            $this->addJoin('pa_drink', 'LEFT JOIN profile_assets pa_drink ON pa_drink.user_id = p.user_id');
            $this->addInClause('drink', $f['drink'], 'drk', 'pa_drink');
        }
    }

    private function addBodyType(array $f): void
    {
        if (!empty($f['body_type'])) {
            $this->addJoin('pa_body', 'LEFT JOIN profile_assets pa_body ON pa_body.user_id = p.user_id');
            $this->addInClause('body_type', $f['body_type'], 'body', 'pa_body');
        }
    }

    private function addComplexion(array $f): void
    {
        if (!empty($f['complexion'])) {
            $this->addJoin('pa_comp', 'LEFT JOIN profile_assets pa_comp ON pa_comp.user_id = p.user_id');
            $this->addInClause('complexion', $f['complexion'], 'comp', 'pa_comp');
        }
    }

    private function addHasChildren(array $f): void
    {
        if (!empty($f['has_children']) && $f['has_children'] !== 'doesnt_matter') {
            $this->addFilter('p.has_children = :has_children', [':has_children' => $f['has_children']]);
        }
    }

    private function addCreatedBy(array $f): void
    {
        if (!empty($f['created_by'])) {
            $this->addInClause('created_by', $f['created_by'], 'cb');
        }
    }

    private function addPhotoRequired(array $f): void
    {
        if (!empty($f['photo_required'])) {
            $this->where[] = 'EXISTS (SELECT 1 FROM profile_photos ph2 WHERE ph2.user_id = p.user_id)';
        }
    }

    private function addVerifiedOnly(array $f): void
    {
        if (!empty($f['verified_only'])) {
            $this->where[] = 'u.is_verified = 1';
        }
    }

    private function addRecentlyActive(array $f): void
    {
        if (!empty($f['recently_active_days'])) {
            $this->addFilter('u.last_login_at >= DATE_SUB(NOW(), INTERVAL :recent_d DAY)', [':recent_d' => (int) $f['recently_active_days']]);
        }
    }

    private function addNewProfiles(array $f): void
    {
        if (!empty($f['new_profiles'])) {
            $this->where[] = 'p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
        }
    }

    private function addPremiumOnly(array $f): void
    {
        if (!empty($f['premium_only'])) {
            $this->addJoin('m_prem', 'LEFT JOIN memberships m_prem ON m_prem.user_id = p.user_id AND m_prem.status = \'active\' AND m_prem.ends_at >= NOW()');
            $this->addJoin('mp_prem', 'LEFT JOIN membership_plans mp_prem ON mp_prem.id = m_prem.plan_id');
            $this->where[] = 'mp_prem.code IS NOT NULL AND mp_prem.code != \'FREE\'';
        }
    }

    private function addEmployedIn(array $f): void
    {
        if (!empty($f['employed_in'])) {
            $this->addInClause('occupation', $f['employed_in'], 'emp2');
        }
    }

    private function addFamilyType(array $f): void
    {
        if (!empty($f['family_type'])) {
            $this->addJoin('pf_fam', 'LEFT JOIN profile_family pf_fam ON pf_fam.user_id = p.user_id');
            $this->addFilter('pf_fam.family_type = :fam_type', [':fam_type' => $f['family_type']]);
        }
    }

    private function addFamilyValues(array $f): void
    {
        if (!empty($f['family_values'])) {
            $this->addJoin('pf_val', 'LEFT JOIN profile_family pf_val ON pf_val.user_id = p.user_id');
            $this->addFilter('pf_val.family_values = :fam_val', [':fam_val' => $f['family_values']]);
        }
    }

    private function addSearch(array $f): void
    {
        if (!empty($f['search'])) {
            $term = '%' . $f['search'] . '%';
            $this->where[] = '(p.first_name LIKE :search_term OR p.last_name LIKE :search_term2)';
            $this->params[':search_term'] = $term;
            $this->params[':search_term2'] = $term;
        }
    }

    private function excludeViewer(int $viewerId): void
    {
        $this->where[] = 'p.user_id != :me';
        $this->where[] = 'p.user_id NOT IN (SELECT target_id FROM matches WHERE user_id = :me2 AND status IN ("declined","blocked"))';
        $this->where[] = 'p.user_id NOT IN (SELECT blocked_id FROM profile_blocks WHERE blocker_id = :me3)';
        $this->params[':me'] = $viewerId;
        $this->params[':me2'] = $viewerId;
        $this->params[':me3'] = $viewerId;
    }

    public function getOrderBy(string $sort): string
    {
        return match ($sort) {
            'recently_joined' => 'p.created_at DESC, u.last_login_at DESC',
            'last_active'     => 'u.last_login_at DESC',
            'newest_first'    => 'p.created_at DESC',
            'viewed_me'       => '(SELECT MAX(viewed_at) FROM profile_views WHERE profile_id = p.user_id) DESC',
            default            => 'u.last_login_at DESC',
        };
    }

    public static function oppositeGender(string $gender): string
    {
        return $gender === 'male' ? 'female' : 'male';
    }
}
