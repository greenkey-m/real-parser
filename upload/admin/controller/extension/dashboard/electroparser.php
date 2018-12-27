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

        // загружаем модель параметров
        $this->load->model('setting/setting');

        // если это сохранение настроек, то сохраняем и возвращаемся в раздел маркета
        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
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

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/dashboard/electroparser_form', $data));
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

