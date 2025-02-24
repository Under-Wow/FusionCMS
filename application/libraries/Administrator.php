<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * @package FusionCMS
 * @author  Jesper Lindström
 * @author  Xavier Geerinck
 * @author  Elliott Robbins
 * @author  Keramat Jokar (Nightprince) <https://github.com/Nightprince>
 * @author  Ehsan Zare (Darksider) <darksider.legend@gmail.com>
 * @link    https://github.com/FusionWowCMS/FusionCMS
 */
class Administrator
{
    protected $CI;
    private string $theme_path;
    private array $menu;
    private string $title;
    private string $currentPage;

    /**
     * Define our paths and objects
     */
    public function __construct()
    {
        $this->CI = &get_instance();
        $this->theme_path = "application/themes/admin/";
        $this->menu = array();
        $this->title = '';
        $this->currentPage = '';

        if (!$this->CI->user->isStaff()) {
            show_404();
        }

        $this->showLogIn();

        if (!$this->CI->input->is_ajax_request() && !isset($_GET['is_json_ajax'])) {
            $this->loadModules();
            $this->getMenuLinks();
        }
    }

    /**
     * Handle admin log ins
     */
    private function logIn()
    {
        $security_code = $this->CI->input->post('security_code');

        // Make sure the user has permission to view the admin panel
        if (!hasPermission("view", "admin")) {
            die("permission");
        }

        if ($security_code == $this->CI->config->item('security_code')) {
            $this->CI->session->set_userdata(array('admin_access' => true));

            die("welcome");
        } else {
            die("key");
        }
    }

    /**
     * Add an extra page title
     *
     * @param String $title
     */
    public function setTitle(string $title): void
    {
        $this->title = $title . " - ";
    }

    /**
     * Get the modules and their manifests as an array
     *
     * @return mixed
     */
    public function getModules(): mixed
    {
        $this->loadModules();

        return $this->modules;
    }


    /**
     * Load and read all modules manifest
     */
    public function loadModules(): void
    {
        if (empty($this->modules)) {
            foreach (glob("application/modules/*") as $file) {
                if (is_dir($file)) {
                    $name = $this->getModuleName($file);

                    $this->modules[$name] = @file_get_contents($file . "/manifest.json");

                    if (!$this->modules[$name]) {
                        die("The module <b>" . $name . "</b> is missing manifest.json");
                    } else {
                        $this->modules[$name] = json_decode($this->modules[$name], true);

                        // Add the module folder name as name if none was specified
                        if (!array_key_exists("name", $this->modules[$name])) {
                            $this->modules[$name]['name'] = $name;
                        }

                        // Add the enabled disabled setting, DEFAULT: disabled
                        if (!array_key_exists("enabled", $this->modules[$name])) {
                            $this->modules[$name]["enabled"] = false;
                        }

                        // Add default description if none was specified
                        if (!array_key_exists("description", $this->modules[$name])) {
                            $this->modules[$name]['description'] = "This module has no description";
                        }

                        // Check if the module has any configs
                        if ($this->hasConfigs($name)) {
                            $this->modules[$name]['has_configs'] = true;
                        } else {
                            $this->modules[$name]['has_configs'] = false;
                        }
                    }
                }
            }
        }
    }

    /**
     * Get the module name out of the path
     *
     * @param String $path
     * @return String
     */
    private function getModuleName(string $path = ""): string
    {
        return preg_replace("/application\/modules\//", "", $path);
    }

    /**
     * Check if the module has any configs
     *
     * @param String $moduleName
     * @return Boolean
     */
    public function hasConfigs(string $moduleName): bool
    {
        if (file_exists("application/modules/" . $moduleName . "/config")) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Get the menu of tools
     *
     * @return void
     */
    private function getMenuLinks(): void
    {
        // Loop through all modules that have manifests
        foreach ($this->modules as $module => $manifest) {
            if (empty($manifest['enabled']) || empty($manifest['admin'])) {
                continue;
            }

            $adminManifests = isset($manifest['admin']['group']) ? array($manifest['admin']) : $manifest['admin'];

            foreach ($adminManifests as $menuGroup) {
                if (!isset($this->menu[$menuGroup['text']])) {
                    $this->menu[$menuGroup['text']] = array(
                        'links' => array(),
                        'icon' => $menuGroup['icon']
                    );
                }

                foreach ($menuGroup['links'] as $key => $link) {
                    if (!empty($link['requirePermission']) && !hasPermission($link['requirePermission'], $module)) {
                        continue;
                    }

                    $menuGroup['links'][$key]['module'] = $module;

                    if ($module == $this->CI->router->fetch_module()) {
                        $url = $this->CI->router->fetch_class() . ($this->CI->router->fetch_method() != "index" ? "/" . $this->CI->router->fetch_method() : "");

                        if ($url == $menuGroup['links'][$key]['controller']) {
                            $menuGroup['links'][$key]['active'] = true;
                            $this->currentPage = "$module/" . $menuGroup['links'][$key]['controller'];
                        }
                    }

                    $this->menu[$menuGroup['text']]['links'][] = $menuGroup['links'][$key];
                }

                if (empty($this->currentPage) && $this->CI->router->fetch_module() == "admin") {
                    $this->currentPage = $this->CI->router->fetch_class();
                }
            }
        }
    }

    /**
     * Loads the template
     *
     * @param String $content The page content
     * @param false|String $css Full path to your css file
     * @param false|String $js Full path to your js file
     */
    public function view(string $content, false|string $css = false, false|string $js = false)
    {
        if ($this->CI->input->is_ajax_request() && isset($_GET['is_json_ajax']) && $_GET['is_json_ajax'] == 1) {
            $array = array(
                "title" => ($this->title) ? $this->title : "",
                "content" => $content,
                "js" => $js,
                "css" => $css
            );

            die(json_encode($array));
        }

        $menu = $this->menu;
        if ($menu) {
            $menui = 1;
            array_walk($menu, function (&$value) use (&$menui) {
                $value['nr'] = $menui++;
                foreach ($value['links'] as $lvalue) {
                    if (isset($lvalue['active'])) {
                        $value['active'] = true;
                        break;
                    }
                }
            });
        }


        $notifications = $this->CI->cms_model->getNotifications($this->CI->user->getId(), true);
        //var_dump($notifications);

        // Gather the theme data
        $data = array(
            "page" => '<div id="content_ajax">' . $content . '</div>',
            "url" => $this->CI->template->page_url,
            "menu" => $menu,
            "title" => $this->title,
            "extra_js" => $js,
            "extra_css" => $css,
            "nickname" => $this->CI->user->getNickname(),
            "current_page" => $this->currentPage,
            "defaultLanguage" => $this->CI->config->item('language'),
            "languages" => $this->CI->language->getAllLanguages(),
            "abbreviationLanguage" => $this->CI->language->getAbbreviationByLanguage($this->CI->language->getLanguage()),
            "serverName" => $this->CI->config->item('server_name'),
            "avatar"    => $this->CI->user->getAvatar($this->CI->user->getId()),
            "groups" => $this->CI->acl_model->getGroupsByUser(),
            "notifications" => $notifications,
            "cdn_link" => $this->CI->config->item('cdn') === true ? $this->CI->config->item('cdn_link') : null
        );

        // Load the main template
        $output = $this->CI->smarty->view($this->theme_path . "template.tpl", $data, true);

        die($output);
    }

    /**
     * Shorthand for loading a content box
     *
     * @param String $title
     * @param String $body
     * @param Boolean $full
     * @param false|String $css
     * @param false|String $js
     * @return String
     */
    public function box(string $title, string $body, bool $full = false, false|string $css = false, false|string $js = false)
    {
        $data = array(
            "headline" => $title,
            "content" => $body
        );

        $page = $this->CI->smarty->view($this->theme_path . "box.tpl", $data, true);

        if ($full) {
            $this->view($page, $css, $js);
        } else {
            return $page;
        }
    }

    /**
     * Get the FusionCMS version
     *
     * @return String
     */
    public function getVersion(): string
    {
        return $this->CI->config->item('FusionCMSVersion');
    }

    /**
     * Get if the module is enabled or not
     *
     * @param $moduleName
     * @return Boolean
     */
    public function isEnabled($moduleName): mixed
    {
        return $this->modules[$moduleName]["enabled"];
    }

    public function getEnabledModules(): array
    {
        $enabled = array();

        foreach ($this->getModules() as $name => $manifest) {
            if ($manifest['enabled']) {
                $enabled[$name] = $manifest;
            }
        }

        return $enabled;
    }

    public function getDisabledModules(): array
    {
        $disabled = array();

        foreach ($this->getModules() as $name => $manifest) {
            if (!array_key_exists("enabled", $manifest) || !$manifest['enabled']) {
                $disabled[$name] = $manifest;
            }
        }

        return $disabled;
    }

    /**
     * Make sure only admins and owners can access
     */
    private function showLogIn()
    {
        if (!$this->CI->session->userdata('admin_access') || !hasPermission("view", "admin")) {
            if ($this->CI->input->post('send')) {
                $this->logIn();
            } else {
                if (!$this->CI->input->is_ajax_request() && !isset($_GET['is_json_ajax'])) {
                    $data = array(
                        "url" => $this->CI->template->page_url,
                        "isOnline" => $this->CI->user->isOnline(),
                        "username" => $this->CI->user->getUsername(),
                        "avatar"    => $this->CI->user->getAvatar($this->CI->user->getId()),
                        "cdn_link" => $this->CI->config->item('cdn') === true ? $this->CI->config->item('cdn_link') : null
                    );

                    $output = $this->CI->smarty->view($this->theme_path . "login.tpl", $data, true);

                    die($output);
                } else {
                    die('<script>window.location.reload(true);</script>');
                }
            }
        }
    }
}
