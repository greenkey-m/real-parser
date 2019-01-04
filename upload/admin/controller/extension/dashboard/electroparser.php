<?php
/**
 * Created by PhpStorm.
 * User: matt
 * Date: 14.12.2018
 * Time: 16:12
 */

class ControllerExtensionDashboardElectroparser extends Controller {
    private $error = array();

    public function index() {

        // загружаем языковые констатнты
        $this->load->language('extension/dashboard/electroparser');

        // устанавливаем заголовок
        $this->document->setTitle($this->language->get('heading_title'));
        $this->document->addScript('view/javascript/electroparser/bootstrap-treeview.min.js');
        $this->document->addStyle('view/javascript/electroparser/bootstrap-treeview.min.css');
        $this->document->addScript('view/javascript/electroparser/electroparser.js');
        $this->document->addStyle('view/stylesheet/electroparser.css');

        $this->document->addScript('https://unpkg.com/vue/dist/vue.js');
        $this->document->addScript('https://unpkg.com/element-ui/lib/index.js');
        $this->document->addStyle('https://unpkg.com/element-ui/lib/theme-chalk/index.css');

        // загружаем модель параметров
        $this->load->model('setting/setting');

        // загружаем модель парсера
        $this->load->model('extension/dashboard/electroparser');

        // если это сохранение настроек, то сохраняем и возвращаемся в раздел маркета
        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {

            // Сохраняем наценки в БД
            //print_r($this->request->post);
            $result = $this->model_extension_dashboard_electroparser->saveAllCategories($this->request->post['dashboard_electroparser_markups']);
            if (!$result) {
                // Проблемы при записи
            }

            $this->model_setting_setting->editSetting('dashboard_electroparser', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=dashboard', true));
        }

        // если есть проблемы выводим
        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=dashboard', true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/dashboard/electroparser', 'user_token=' . $this->session->data['user_token'], true)
        );


        // переменные в data
        $data['action'] = $this->url->link('extension/dashboard/electroparser', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'] . '&type=dashboard', true);

        if (isset($this->request->post['dashboard_electroparser_width'])) {
            $data['dashboard_electroparser_width'] = $this->request->post['dashboard_electroparser_width'];
        } else {
            $data['dashboard_electroparser_width'] = $this->config->get('dashboard_electroparser_width');
        }

        $data['columns'] = array();

        for ($i = 3; $i <= 12; $i++) {
            $data['columns'][] = $i;
        }

        if (isset($this->request->post['dashboard_electroparser_status'])) {
            $data['dashboard_electroparser_status'] = $this->request->post['dashboard_electroparser_status'];
        } else {
            $data['dashboard_electroparser_status'] = $this->config->get('dashboard_electroparser_status');
        }

        if (isset($this->request->post['dashboard_electroparser_sort_order'])) {
            $data['dashboard_electroparser_sort_order'] = $this->request->post['dashboard_electroparser_sort_order'];
        } else {
            $data['dashboard_electroparser_sort_order'] = $this->config->get('dashboard_electroparser_sort_order');
        }

        if (isset($this->request->post['dashboard_electroparser_markup'])) {
            $data['dashboard_electroparser_markup'] = $this->request->post['dashboard_electroparser_markup'];
        } else {
            $data['dashboard_electroparser_markup'] = $this->config->get('dashboard_electroparser_markup');
        }

        // Получаем все категории товаров
        $cats = $this->model_extension_dashboard_electroparser->loadAllCategories();

        $categories = $this->form_tree($cats);
        //преобразовать в соответствии с деревом
        $data['categories'] = json_encode($this->build_tree($categories, 0, ""), JSON_UNESCAPED_UNICODE);


        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/dashboard/electroparser_form', $data));
    }

    private function form_tree($mess)
    {
        if (!is_array($mess)) {
            return false;
        }
        $tree = array();
        foreach ($mess as $value) {
            $tree[$value['parent_id']][] = $value;
        }
        return $tree;
    }

    //$parent_id - какой parentid считать корневым
    //по умолчанию 0 (корень)
    private function build_tree($cats, $parent_id, $pathy)
    {
        if (is_array($cats) && isset($cats[$parent_id])) {
            $tree = array();
            foreach ($cats[$parent_id] as $cat) {

                $line = '<div class="catline form-inline"><span>'.$cat['name'].'</span>'.
                    '<div class="input-group">'.
                    '<div class="input-group-addon input-sm">%</div>'.
                    '      <input type="text" class="form-control input-sm" '.
                    'name="dashboard_electroparser_markups['.$cat['category_id'].']" '.
                    'value="'.$cat['markup'].'" placeholder="0" id="markup_'.$cat['category_id'].'">'.
                    '      <span class="input-group-btn">'.
                    '        <button class="btn btn-success btn-sm" type="button">OK</button>'.
                    '        <button class="btn btn-danger btn-sm" type="button">Children</button>'.
                    '      </span></div>';

                $tree[] = array(
                    'id' => $cat['category_id'],
                    'label'       => $cat['name'],
                    'markup'      => $cat['markup'],
                    'ref'         => $cat['markup'],
                    'input'       => 'dashboard_electroparser_markups['.$cat['category_id'].']',
                    'tags'        => ["0"],
                    'href'        => $this->url->link('product/category', 'path=' . ($pathy <> "" ? $pathy."_" : "") . $cat['category_id']),
                    'children'    => $this->build_tree($cats, $cat['category_id'], ($pathy <> "" ? $pathy."_" : "").$cat['category_id'])
                );
            }
        } else {
            return false;
        }
        return $tree;
    }


    protected function validate() {
        // проверка уровня доступа
        if (!$this->user->hasPermission('modify', 'extension/dashboard/electroparser')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        return !$this->error;
    }

    public function dashboard() {

        // загружаем языковые константы
        $this->load->language('extension/dashboard/electroparser');

        $data['user_token'] = $this->session->data['user_token'];

        // какая-то перменные, данные
        $data['parserlastdate'] = array();

        // загружаем модель парсера
        $this->load->model('extension/dashboard/electroparser');

        //$results = $this->model_extension_dashboard_electroparser->getParserStatus();

        // выводим в шаблон
        return $this->load->view('extension/dashboard/electroparser_info', $data);
    }
}

