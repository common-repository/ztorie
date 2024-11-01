<?php
/**
 * Plugin Name:       Ztorie
 * Description:       plugin for using Ztorie widget
 * Version:           1.1.5
 * Author:            Ztorie team
 * Author URI:        https://ztorie.com
 * Text Domain:       ztorie
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */
define('ZTORIE_SCRIPT', 'https://assets.ztorie.com/widgets/reader/app-client-bundle.js');

class ZtoriePlugin
{


    function __construct()
    {
        $this->zt_init();
    }

    function zt_init()
    {
        add_action('admin_menu', array($this, 'zt_create_plugin_settings_page'));
        add_shortcode("ztorie", array($this, "zt_callback"));
        add_action("wp_enqueue_scripts", array($this, "zt_register_ztorie_plugin_scripts"));
        add_action("wp_enqueue_scripts", array($this, "zt_register_ztorie_plugin_styles"));

        add_filter("mce_external_plugins", array($this, "zt_enqueue_mce_plugin_scripts"));
        add_filter("mce_buttons", array($this, "zt_register_mce_buttons_editor"));

        add_action("wp_ajax_nopriv_ztorie_admin_add_post", array($this, "zt_admin_add_post"));
        add_action("wp_ajax_ztorie_admin_add_post", array($this, "zt_admin_add_post"));

        add_action("wp_ajax_nopriv_ztorie_admin_check_api_key", array($this, "zt_admin_check_api_key"));
        add_action("wp_ajax_ztorie_admin_check_api_key", array($this, "zt_admin_check_api_key"));

        add_action("wp_ajax_nopriv_ztorie_admin_remove_api_key", array($this, "zt_admin_remove_api_key"));
        add_action("wp_ajax_ztorie_admin_remove_api_key", array($this, "zt_admin_remove_api_key"));

        wp_enqueue_script('custom_admin_script', 'https://code.jquery.com/jquery-3.3.1.min.js', array('jquery'));
        add_filter('single_template', array($this, 'zt_load_ztorie_template'));

        add_action("admin_init", array($this, "zt_admin_init"));
        add_action('wp', array($this, 'zt_story_load_frontend'));

        add_action('admin_post_ztorie_save_settings', array($this, 'zt_save_settings'));
        add_action('admin_post_nopriv_ztorie_save_settings', array($this, 'zt_save_settings'));

        add_action('post_submitbox_misc_actions', array($this, 'zt_add_publish_meta_options'));

        add_action('admin_head', array($this, 'zt_update_ztorie_admin_menu_icon'));

    }

    public function zt_admin_remove_api_key()
    {
        $this->zt_save_app_key('');
    }

    public function zt_admin_check_api_key()
    {
        if (isset($_POST['apiKey'])) {
            $response = wp_remote_get("https://alp.svc.ztorie.com/ajaxManager.php?service=app&action=checkApiKey",
                array('headers' => ['Z-App-Key' => $_POST['apiKey']]));
            if ($response['response']['code'] == 200) {
                $this->zt_save_app_key($_POST['apiKey']);
                echo 'valid';
                wp_die();
            } else {
                echo 'invalid';
                wp_die();
            }
        }
    }

    public function zt_save_app_key($code)
    {
        $exCode = get_option('ztorie_app_key', false);
        $cleanCode = sanitize_text_field($code);
        if ($exCode !== false)
            update_option('ztorie_app_key', $cleanCode);
        else
            add_option('ztorie_app_key', $cleanCode);

    }

    public function zt_save_settings()
    {
        $code = get_option('ztorie_app_key', false);

        $cleanCode = sanitize_text_field($_POST['ztorie_app_key']);
        if ($code !== false)
            update_option('ztorie_app_key', $cleanCode);
        else
            add_option('ztorie_app_key', $cleanCode);

        wp_redirect(admin_url('admin.php?page=ztorie_admin&save=1'));
    }

    function zt_admin_init()
    {
        $code = get_option('ztorie_app_key', false);

//        if ($code !== false) {
        add_meta_box("ztorie_add_post_metabox", "Ztorie", array($this, "zt_show_ztorie_metabox"), "post", "normal", "high", 'post');
        add_meta_box("ztorie_add_page_metabox", "Ztorie", array($this, "zt_show_ztorie_metabox"), "page", "normal", "high", 'page');
//        }
        add_action('save_post', array($this, 'zt_save_ztorie_meta'));
    }


    function zt_add_publish_meta_options($post_obj)
    {


        $cont = "
                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        
                        changeAmpStatus(document.querySelector('#zt-replace').checked);
                        document.querySelector('#zt-replace').addEventListener('change', function() {
                                changeAmpStatus(this.checked);
//                                if(this.checked) {    
//                                    document.querySelector('#amp-publish-status').textContent = document.querySelector('#amp-publish-status').textContent+'Enabled'
//                                } else {
//                                    document.querySelector('#amp-publish-status').textContent = 'Disabled'
//                                }
                            });
                    });
                    function changeAmpStatus(status) {
                        let elem = document.querySelector('#amp-publish-status');
                        let baseElem = document.querySelector('#amp-publish');
                        if(status)
                            elem.textContent = 'Enabled';
                        else 
                            elem.textContent = 'Disabled';
                        baseElem.style.display = 'block';
                    }
                </script>"
            . "<div class='misc-pub-section misc-pub-section-last'>"
            . "<b><span id='amp-publish' style='display:none'><img style='vertical-align: middle' src='" . plugins_url('/assets/images/icon_wp.png', __FILE__) . "' alt=''> <span style='vertical-align: text-top'>AMP Story: <span id='amp-publish-status'></span></span></span></b>"
            . "</div>";

        echo $cont;

    }


    function zt_show_ztorie_metabox($args1, $args2)
    {

        global $post;

        $typeName = ucfirst($args2['args']);

        $appKey = get_option('ztorie_app_key', false);
        if (!$appKey) {
//            $contents = file_get_contents(__DIR__ . '/admin/not-authorized.html');

            $contents = str_replace(
                '{PLUGIN_URL}',
                plugins_url('', __FILE__),
                file_get_contents(__DIR__ . '/admin/not-authorized.html'));
        } else {

            $enabled = get_post_meta($post->ID, '_ztorie_enabled', true);
            $storyCode = get_post_meta($post->ID, '_ztorie_code', true);
            $response = wp_remote_get("https://svc.ztorie.com/ajaxManager.php?service=app&action=getPublicStories",
                array('headers' => ['Z-App-Key' => $appKey]));
            $stories = [];
            if (is_array($response)) {
                $stories = json_decode($response['body']);
            }
            $stories = $stories->Data;

            if (!count($stories)) {
                $contents = str_replace(
                    '{PLUGIN_URL}',
                    plugins_url('', __FILE__),
                    file_get_contents(__DIR__ . '/admin/edit-empty.html'));

            } else {
                $storiesHtml = '';
                foreach ($stories as $story) {
                    $storiesHtml .= "
                                     <div class='zt-item zt-item-post " . (($story->code == $storyCode) ? "zt-selected" : "") . "' style=''>
                                        <input type='hidden' name='code' value='{$story->code}'>
                                        <input type='hidden' name='title' value='{$story->title}'>
                                        <input type='hidden' name='seo_title' value='" . ($story->seoField->title ?: $story->seo_title) . "'>
                                        <img style='' class='zt-use-story' src='{$story->latest_thumb->first_thumb}' alt=''>
                                        <p>{$story->title}</p>
                                        <div class='zt-buttons'>
                                            <a target='_blank' href='https://app.ztorie.com/story/create/{$story->code}'>Edit</a>
                                            <a class='zt-preview' >Preview</a>
                                        </div>
                                    </div>
                                ";
                }

                $contents = str_replace(array(
                    '{EDIT_CONTENT}',
                    '{ZTORIE_ENABLED}',
                    '{SELECTED_CODE}',
                    '{POPUP_SCRIPT}',
                    '{PLUGIN_URL}',
                    '{TYPE_NAME}'
                ), array(
                    $storiesHtml,
                    $enabled ? "checked" : '',
                    $storyCode,
                    $this->zt_popup_scripts($stories),
                    plugins_url('', __FILE__),
                    $typeName
                ), file_get_contents(__DIR__ . '/admin/edit.html'));
            }


        }
        echo $contents;
    }

    function zt_popup_scripts($stories)
    {
        $content = '<script>
        var ztZtorieStories = ' . json_encode($stories) . '
        </script>';
        return $content;
    }


    function zt_story_load_frontend()
    {
        global $post;

        $ztorie_activated = get_post_meta($post->ID, '_ztorie_enabled', true);
//        $ztorie_primary = get_post_meta($post->ID, '_ztorie_replace', true);
        if ($ztorie_activated && !is_admin() && (is_single() || is_page())) {
            require_once('assets/template/story-template.php');
            die();
        }
        $code = get_post_meta($post->ID, '_ztorie_code', true);
        if (sanitize_text_field($_GET['amp'] == 1) && $code) {
            require_once('assets/template/story-template.php');
            die();
        }

    }


    function zt_save_ztorie_meta()
    {
        global $post;

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

//        if (isset($_POST['ztorie_enabled'])) {
        update_post_meta($post->ID, "_ztorie_enabled", sanitize_text_field($_POST['ztorie_enabled']));
//        } else {
//            delete_post_meta($post->ID, "_ztorie_enabled", '');
//        }

        update_post_meta($post->ID, "_ztorie_code", sanitize_text_field($_POST['ztorie_code']));
//        } else {
//            delete_post_meta($post->ID, "_ztorie_enabled", '');
//        }

    }


    function zt_load_ztorie_template($template)
    {
        global $post;

        if ($post->post_type == "ztorie" && $template !== locate_template(array("assets/template/story-template.php"))) {
            return plugin_dir_path(__FILE__) . "assets/template/story-template.php";
        }

        return $template;
    }


    function zt_create_plugin_settings_page()
    {
        global $submenu;
        $page_title = 'Generat';
        $menu_title = 'Ztorie';
        $capability = 'manage_options';
        $slug = 'ztorie_admin';
        $callback = array($this, 'zt_admin_index');
        $icon = plugins_url('/assets/images/icon_wp.png', __FILE__);
        $position = 10;
        add_menu_page($page_title, $menu_title, $capability, $slug, $callback, $icon, $position);
        add_submenu_page($slug, 'General', 'General', 'manage_options', $slug, $callback);
//        add_submenu_page($slug, 'Analytics', 'Analytics', 'manage_options', 'ztorie-stats', array($this, 'zt_admin_stats'));

        $submenu[$slug][] = array('Analytics', 'manage_options', 'https://app.ztorie.com/story/analytics');


//        $menu_slug = "externallink"; // used as "key" in menus
//        $menu_pos = 1; // whatever position you want your menu to appear
//
//        // create the top level menu
//        add_menu_page( 'external_link', 'External Links', 'read', $menu_slug, '', '', $menu_pos);
//
//        // add the external links to the slug you used when adding the top level menu
//        $submenu[$menu_slug][] = array('Example', 'manage_options', 'http://www.example.com/');
//        $submenu[$menu_slug][] = array('Google', 'manage_options', 'https://www.google.com/');


    }


    function zt_update_ztorie_admin_menu_icon()
    {
        $str = "
                <style type='text/css'>
            
                        #toplevel_page_ztorie_admin .wp-menu-image img {
                            display: none;
                        }
                        
                        #adminmenu #toplevel_page_ztorie_admin div.wp-menu-image:before {
                            content: \" \";
                            background: url('" . plugins_url('/assets/images/icon_wp.png', __FILE__) . "');
                            background-size: auto;
                            background-repeat: no-repeat;
                            background-position: center;
                        }
                        
                        #adminmenu #toplevel_page_ztorie_admin  a:hover div.wp-menu-image:before {
                            opacity: 1;
                            background: url('" . plugins_url('/assets/images/icon_wp_hover.png', __FILE__) . "');
                            background-size: auto;
                            background-repeat: no-repeat;
                            background-position: center;
                            width: 28px;    
                            color:#0073aa;
                        }
                        
                        #adminmenu #toplevel_page_ztorie_admin  a.wp-menu-open:hover div.wp-menu-image:before {
                            background: url('" . plugins_url('/assets/images/icon_wp_active.png', __FILE__) . "');
                            background-size: auto;
                            background-repeat: no-repeat;
                            background-position: center;
                            width: 28px;
                        }
                        
                        #adminmenu #toplevel_page_ztorie_admin  .wp-menu-open div.wp-menu-image:before {
                              opacity: 1;
                            background: url('" . plugins_url('/assets/images/icon_wp_active.png', __FILE__) . "');
                            background-size: auto;
                            background-repeat: no-repeat;
                            background-position: center;
                            width: 28px;     
                        }
        </style>
        ";
        echo $str;
    }


    function zt_admin_index()
    {

        $actionUrl = admin_url('admin-post.php');
        $code = get_option('ztorie_app_key', false);

        $assetsPath = plugins_url('', __FILE__);

        $saved = $_GET['save'] == 1 ? true : false;

        if ($code) {
            $contents = str_replace(array(
                '{ASSETS_PATH}',
            ), array(
                $assetsPath,
            ), file_get_contents(__DIR__ . '/admin/main-conected.html'));
        } else {
            $contents = str_replace(array(
                '{FORM_ACTION}',
                '{CODE}',
                '{ASSETS_PATH}',
                '{MESSAGE}'
            ), array(
                $actionUrl,
                $code,
                $assetsPath,
                $saved ? 'Connected !' : ''

            ), file_get_contents(__DIR__ . '/admin/main.html'));
        }

        echo $contents;
    }

    function zt_admin_stats()
    {

        global $submenu;
        $permalink = admin_url('edit-tags.php') . '?taxonomy=category';
        $submenu['options-general.php'][] = array('Manage', 'manage_options', $permalink);
//        echo "<h1>Ztorie stats</h1>";
        ?>


        <h1>Ztorie stats</h1>
        <script>window.location = "<?php echo "https://app.ztorie.com/story/analytics" ?>";</script><?php
    }


    function zt_callback($atts = null, $content = null)
    {
        extract($atts);
        return "<div class='ztorie-container-parent'><div class='ztorie-container-child' ><div class='ztorie-video' data-id='" . $id . "'></div></div></div>";
    }


    function zt_register_ztorie_plugin_scripts()
    {

        wp_register_script('ztorie-bundle', ZTORIE_SCRIPT, '', '', true);
        wp_enqueue_script('ztorie-bundle');
    }


    function zt_register_ztorie_plugin_styles()
    {
        wp_register_style("ztorie-wp-css", plugins_url("/assets/css/ztorie-wp.css", __FILE__));
        wp_enqueue_style("ztorie-wp-css");
    }

    function zt_enqueue_mce_plugin_scripts($plugin_array)
    {
        $plugin_array["ztorie_button_plugin"] = plugin_dir_url(__FILE__) . "assets/js/mce.js";
        return $plugin_array;
    }


    function zt_register_mce_buttons_editor($buttons)
    {
        array_push($buttons, "ztorie");
        return $buttons;
    }


}

$ztorie = new ZtoriePlugin();