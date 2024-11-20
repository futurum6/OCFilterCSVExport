<?php
class ControllerExtensionModuleOcFilterCsvExport extends Controller
{

    private $error = [];

    /**
     * Main method to handle the module's index page.
     */
    public function index(): void
    {

        $this->load->language('extension/module/oc_filter_csv_export');
        $this->document->setTitle($this->language->get('heading_title'));

        // Load required models
        $this->load->model('localisation/language');
        $this->load->model('extension/module/oc_filter_csv_export');

        if ($this->isPostRequest()) {
            if ($this->validate()) {
                $this->export();
            }
        }

        // Breadcrumbs
        $data['breadcrumbs'] = [];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        ];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/ocfiltercsvexport', 'user_token=' . $this->session->data['user_token'], true)
        ];

        $data['languages'] = $this->model_localisation_language->getLanguages();
        $data['action'] = $this->url->link('extension/module/ocfiltercsvexport/export', 'user_token=' . $this->session->data['user_token'], true);

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        if ($this->error) {
            print_r($this->error);
            die();
        }

        $data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
        $this->response->setOutput($this->load->view('extension/module/oc_filter_csv_export', $data));
    }

    /**
     * Handles the export functionality.
     * Validates the request and generates the CSV file.
     */
    public function export(): void
    {
        $language_id = $this->request->post['language_id'] ?? null;
        try {
            $links = $this->generateLinks($language_id);
            $this->outputCsv($links);
        } catch (Exception $e) {
            $this->log->write('Error in getfilterurls: ' . $e->getMessage());
            http_response_code(500);
            exit('Error generating URLs');
        }
    }

    /**
     * Generates the filter URLs based on the provided language ID.
     * 
     * @param int|null $language_id The language ID to filter categories and filters.
     * @return array The generated URLs.
     */
    private function generateLinks($language_id = null): array
    {
        $links = [];
        $categories = $this->model_extension_module_oc_filter_csv_export->getCategories();
      
        foreach ($categories as $category) {
            if ($language_id && $category['language_id'] != $language_id) {
                continue;
            }
       
            $filters = $this->model_extension_module_oc_filter_csv_export->getFilters($category['category_id'], $category['language_id']);
            foreach ($filters as $f) {
                $values = $this->model_extension_module_oc_filter_csv_export->getValues($category['category_id'], $f['filter_id'], $f['source'], $category['language_id']);
            
                foreach ($values as $value) {
                    $links[] = $this->getUrl([
                        'path_url' => $category['path_url'],
                        'filter_key' => $f['filter_key'],
                        'value_id' => $value['value_id'],
                    ], $category['language_id']);
                }
            }


            $brands = $this->model_extension_module_oc_filter_csv_export->getBrands($category['category_id'], $category['language_id']);
            foreach ($brands as $brand) {
                $links[] = $this->getUrl([
                    'path_url' => $category['path_url'],
                    'manufacturer_id' => $brand['manufacturer_id'],
                    'category_id' => $category['category_id'],
                ], $category['language_id']);
            }
        }
  
        return $links;
    }

    /**
     * Outputs the generated URLs to a CSV file and initiates the download.
     * 
     * @param array $links The URLs to be included in the CSV file.
     * @throws Exception If the CSV file cannot be created or written.
     */
    private function outputCsv($links): void
    {
    
        $file_path = DIR_DOWNLOAD . 'urls_for_google_indexing.csv';

        if (!$file = @fopen($file_path, 'w')) {
            throw new Exception('Cannot create CSV file');
        }

        foreach ($links as $link) {
            fputcsv($file, [$link]);
        }
        fclose($file);

        if (!file_exists($file_path)) {
            throw new Exception('CSV file not created');
        }

        header('Content-Description: File Transfer');
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="urls_for_google_indexing.csv"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file_path));
        readfile($file_path);
    }

    /**
     * Constructs the URL based on the provided filter data.
     * 
     * @param array $filterData The filter data including path URL, filter key, and value ID.
     * @param int|null $language_id The language ID
     * @return string The constructed URL.
     */
    private function getUrl($filterData, ?int $language_id = null): string
    {
        $base_url = $this->config->get('config_url') ?: HTTP_SERVER;
        $base_url = str_replace('/admin/', '/', $base_url);
   
      
        if (isset($filterData['manufacturer_id'])) {
           
            $postfix = "?ocf=F1S0V{$filterData['manufacturer_id']}";
           
            $seo_url = $this->getCachedUrl($filterData['manufacturer_id'],  $filterData['category_id'], $language_id);
           
            if (!empty($seo_url)) {
                $postfix = $seo_url;
            }
            // if ((!empty($filterData['manufacturer_id']) && $filterData['manufacturer_id'] == 30) && ((!empty($filterData['path_url']) && $filterData['path_url'] == 'dytyacha-kimnata/stilchyky-dlya-goduvannya/'))) {
            //     echo "<pre>";print_r( rtrim($base_url, '/') . '/' . ($filterData['path_url'] ?? '') . $postfix );exit;
            // } 
            return rtrim($base_url, '/') . '/' . ($filterData['path_url'] ?? '') . $postfix;
        } elseif (isset($filterData['filter_key']) && isset($filterData['value_id'])) {
            $parameter = $this->ocfilter->params->encode([
                $filterData['filter_key'] => [$filterData['value_id']]
            ]);
            return rtrim($base_url, '/') . '/' . $filterData['path_url'] . '?ocf=' . $parameter;
        }
        
        return rtrim($base_url, '/') . '/' . ($filterData['path_url'] ?? '');
    }

    /**
     * Gets cached URL or fetches from database
     */
    private function getCachedUrl(int $manufacturer_id, int $category_id,  ?int $language_id): ?string 
    {
        return $this->model_extension_module_oc_filter_csv_export->getPageIdByUrl($manufacturer_id, $category_id, $language_id);
    }

    /**
     * Checks if the current request is a POST request.
     * 
     * @return bool True if the request method is POST, false otherwise.
     */
    private function isPostRequest(): bool
    {
        return $this->request->server['REQUEST_METHOD'] == 'POST';
    }

    /**
     * Validates the request data and permissions.
     * 
     * @return bool True if the request is valid, false otherwise.
     */
    protected function validate(): bool
    {
        if (!$this->user->hasPermission('modify', 'extension/module/oc_filter_csv_export')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (!isset($this->request->post['language_id'])) {
            $this->error['warning'] = $this->language->get('error_language');
        }

        return !$this->error;
    }
}
