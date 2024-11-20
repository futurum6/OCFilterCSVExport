<?php
class ModelExtensionModuleOcFilterCsvExport extends Model 
{
    /**
     * Retrieves categories with their SEO URLs and names.
     * 
     * @return array Grouped categories with path URLs.
     */
    public function getCategories(): array
    {
        $sql = "
            SELECT 
                su.language_id,
                su.keyword,
                cp.category_id,
                cp.path_id,
                cd.name AS category_name
            FROM 
                " . DB_PREFIX . "seo_url su
            JOIN 
                " . DB_PREFIX . "category_path cp 
                ON su.query = CONCAT('category_id=', cp.path_id)
            JOIN 
                " . DB_PREFIX . "category_description cd 
                ON cp.path_id = cd.category_id 
                AND cd.language_id = su.language_id
            JOIN 
                " . DB_PREFIX . "category c 
                ON c.category_id = cp.category_id
            WHERE 
                su.query LIKE 'category_id=%' 
                AND su.store_id = 0 
                AND c.status = 1
            ORDER BY 
                cp.level ASC
        ";
    
        $result = $this->db->query($sql);
        $categories = $result->rows;
    
        return $this->groupCategories($categories);
    }
    
 
    /**
     * Groups categories by category_id and language_id, and constructs path URLs.
     * 
     * @param array $categories List of categories to group.
     * @return array Grouped categories with path URLs.
     */
    private function groupCategories($categories): array
    {
        $grouped = [];
        foreach ($categories as $category) {
            $grouped[$category['category_id']][$category['language_id']][] = $category;
        }

        $result = [];
        foreach ($grouped as $category) {
            foreach ($category as $lng) {
                $path = '';
                $count = count($lng);
                foreach ($lng as $i => $c) {
                    $path .= $c['keyword'] . '/';
                    if ($i + 1 == $count) {
                        $c['path_url'] = $path;
                        $result[] = $c;
                    }
                }
            }
        }
        return $result;
    }

    /**
     * Retrieves filters for a given category and language.
     * 
     * @param int $category_id The ID of the category.
     * @param int $language_id The ID of the language.
     * @return array List of filters.
     */
    public function getFilters($category_id, $language_id): array
    {
        return $this->db->query("
            SELECT DISTINCT 
                CONCAT(f.filter_id, '.', f.source) AS filter_key,
                f.filter_id,
                f.source,
                f.type,
                f.sort_order,
                f.status
            FROM " . DB_PREFIX . "product_to_category ptc
            JOIN " . DB_PREFIX . "ocfilter_filter_value_to_product ofvtp 
                ON ptc.product_id = ofvtp.product_id
            JOIN " . DB_PREFIX . "ocfilter_filter f 
                ON ofvtp.filter_id = f.filter_id AND f.status = 1
            JOIN " . DB_PREFIX . "ocfilter_filter_description fd 
                ON f.filter_id = fd.filter_id AND fd.language_id = {$language_id}
            WHERE ptc.category_id = {$category_id}
            GROUP BY f.filter_id
        ")->rows;

    }

    /**
     * Retrieves filter values for a given category, filter key, source, and language.
     * 
     * @param int $category_id The ID of the category.
     * @param string $filter_key The key of the filter.
     * @param string $source The source of the filter.
     * @param int $language_id The ID of the language.
     * @return array List of filter values.
     */ 
    public function getValues($category_id, $filter_key, $source, $language_id): array
    {
        return $this->db->query("SELECT DISTINCT 
                        oov.value_id, 
                        oovd.name AS value_name
                    FROM " . DB_PREFIX . "ocfilter_filter_value oov
                    JOIN " . DB_PREFIX . "ocfilter_filter_value_description oovd 
                        ON oov.value_id = oovd.value_id
                    LEFT JOIN " . DB_PREFIX . "ocfilter_filter_value_to_product oofp 
                        ON oov.filter_id = oofp.filter_id AND oov.value_id = oofp.value_id
                    LEFT JOIN " . DB_PREFIX . "product_to_category op2c 
                        ON oofp.product_id = op2c.product_id
                    WHERE oov.filter_id = '{$filter_key}' 
                        AND oov.source = '{$source}' 
                        AND oofp.filter_id IS NOT NULL 
                        AND oofp.value_id IS NOT NULL 
                        AND op2c.category_id = '{$category_id}' 
                        AND oovd.language_id = {$language_id}")->rows;
    }

    /**
     * Retrieves brands for a given category and language.
     * 
     * @param int $category_id The ID of the category.
     * @param int $language_id The ID of the language.
     * @return array List of brands.
     */
    public function getBrands($category_id, $language_id): array
    {
        return $this->db->query("
            SELECT 
                m.manufacturer_id
            FROM " . DB_PREFIX . "manufacturer m
            JOIN " . DB_PREFIX . "product p 
                ON m.manufacturer_id = p.manufacturer_id
            JOIN " . DB_PREFIX . "product_to_category pc 
                ON p.product_id = pc.product_id
            LEFT JOIN " . DB_PREFIX . "seo_url su 
                ON su.query = CONCAT('manufacturer_id=', m.manufacturer_id) 
                AND su.language_id = {$language_id} -- Фильтруем сразу по language_id
            WHERE 
                pc.category_id = {$category_id}
            GROUP BY 
                m.manufacturer_id
        ")->rows;
    }
    

    public function getPageIdByUrl($filterParam, $category_id, $language_id)
    {
        $query = $this->db->query("
        SELECT su.keyword
        FROM " . DB_PREFIX . "ocfilter_page ocp
        JOIN " . DB_PREFIX . "seo_url su ON su.query = CONCAT('ocfilter_page_id=', ocp.page_id)
        WHERE 
            ocp.params LIKE '{\"1.0\":[\"{$filterParam}\"]}' 
            AND su.language_id = {$language_id} 
            AND ocp.dynamic_id = 0
            AND ocp.category_id = {$category_id} 
        ");
      

        if ($query->num_rows) {
            return $query->row['keyword'];
        }

        return null;
    }
}
