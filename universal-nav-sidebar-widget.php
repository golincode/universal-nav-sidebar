<?php
/*
Plugin Name: DiG Universal Nav Sidebar Widget
Plugin_URI: http://www.wearearchitect.com
Description: A plugin that allows DiG Admins to display main & footer navigation with Dig Campaigns
Version: 0.2.0
Author: Sam Woolner
Author URI: http://www.wearearchitect.com
License: GPL2
*/

class Universal_Nav_Sidebar_Widget extends WP_Widget {

/**
* Register widget with WordPress
*/
function __construct() {
    parent::__construct(
        'universal_nav_sidebar_widget', // Base ID
        __('Universal Nav Menu', 'text_domain'), // Name
        array( 'description' => __( 'Universal Nav Sidebar Widget', 'text_domain' ), ) // Args
    );
}

public function check_cache($market_id, $language_iso) {

    $dir = explode('themes/', dirname(__FILE__));
    $cache = $dir[0] . '/cache';

    if ( !file_exists($cache) ) {
        mkdir($cache, 0755, true);
    }

    if(!is_writable($cache) )
    {
        chmod($cache, 0755);
    }

    $current_time = time();
    $expire_time = 1 * 60 * 60;

    $file = $cache . '/latest_universal_nav_items_'.$market_id.'_'.$language_iso.'.txt';

    if(file_exists($file)) {
        $file_time = filemtime($file);
    }

    if(! file_exists($file) || ($current_time - $expire_time > $file_time))
    {
        $result = $this->getItems($instance, $market_id, $language_iso);
        file_put_contents($file,$result);
    }
    else
    {
        $result = file_get_contents($file);
    }

    return $result;

}

/**
* Front-end display of widget.
* @see WP_Widget::widget()
* @param array $args     Widget arguments.
* @param array $instance Saved values from database.
*/
public function widget( $args, $instance ) {

    $lang =  explode('_', get_locale());
    if(isset($args['market']))
    {
        $market_id = $args['market'];
    }
    else
    {
        $market_id = get_option('cpg_options_market_id');
    }
    if(isset($args['language']))
    {
        $language_iso = $args['language'];
    }
    else
    {
         $language_iso = strtolower($lang[0]);
    }

    $result = $this->check_cache($market_id, $language_iso);

    if($result)
    {

        $result = json_decode($result);

        if($result->status == 1)
        {

        //echo $args['before_widget'];

        switch ($args['section']) {

            case 'logo':
                echo '<a class="logo" href="/"><img alt="' . get_bloginfo('name') . '" src="' . $result->logo . '"></a>';
            break;

            case 'footer':
                $this->outputFooterMenu($result);
            break;

            case 'social-media':
                $this->buildSocial($result);
            break;

            default:

                $this->buildMenu($result);

                $subNavImages = $this->cpg_get_subnav_images_array($result->navitems);

                echo '<script type="text/javascript">
                /* <![CDATA[ */
                var navigationData = {"subNavImages":'.json_encode($subNavImages).'};
                /* ]]> */
                </script>';
            break;

        }

        //echo $args['after_widget'];

        }
        else
        {
        if(isset($result->error))
        {
        echo '<div class="error below-h2" id="message"><p>'.$result->error.'</p></div>';
        }
        else
        {
        echo '<div class="error below-h2" id="message"><p>Unknown Error. Contact Support</p></div>';
        }

        }
    }
    else
    {
    echo '<div class="error below-h2" id="message"><p>Error Connecting to the API.</p></div>';
    }
}


public function cpg_get_subnav_images_array($result) {

    $nav_images = array();

    if( is_array($result) ) {
        foreach($result as $menu_item)
        {
            if(isset($menu_item->image) && $menu_item->image != '')
            {
                $nav_images['menu-item-'.$menu_item->wpid] = $menu_item->image;
            }
        }
    }

    return $nav_images;
}

/**
* Back-end widget form.
* @see WP_Widget::form()
* @param array $instance Previously saved values from database.
*/
public function getItems($instance, $market_id, $language_iso) {

    if(isset($instance['aws_url']))
    {
        $urlprefix = rtrim($instance['aws_url'],'/');
    }
    else
    {
        $urlprefix = 'https://s3-eu-west-1.amazonaws.com';
    }

    $url = $urlprefix.'/'.'menu-'.$market_id.'-'.$language_iso.'.json';

    //open connection
    $ch = curl_init();
    //set the url, number of POST vars, POST data
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $result = curl_exec($ch);
    curl_close($ch);

    return $result;

}

/**
* Build the menu template with retreved nav items
* @see WP_Widget::form()
* @param array $instance Previously saved values from database.
*/
public function buildMenu($result) {

    echo '<ul class="menu menu-main" id="menu-main-navigation">';
    if(is_array($result->navitems))
    {
        foreach($result->navitems as $navitem)
        {
            if($navitem->location == 'main' && $navitem->parent == 0)
            {
                $current_class = $this->getCurrentClass($navitem->url);

                list($content, $current_menu_parent) = $this->buildSubNav($result, $navitem);

                $parent_class = ($content != '' ? 'menu-item-has-children' : '');
                echo '<li id="menu-item-'.$navitem->wpid.'" class=" '.$parent_class.' '.$current_class.' '.$current_menu_parent.' menu-item menu-itemmenu-item-'.$navitem->wpid.' '.$navitem->classes.'" id="menu-item-'.$navitem->wpid.'"><a href="'.$navitem->url.'">'.stripcslashes($navitem->title).'</a>';
                if($content != '')
                {
                    echo '<ul class="sub-menu">';
                    echo $content;
                    echo '</ul>';
                }
                echo '<li>';
            }
        }
    }

    echo '</ul>';
}

public function getCurrentClass($current_url)
{
    $current_page = str_replace('http://', '', $_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"]);
    $current_page = str_replace('https://', '', $current_page);
    $current_page = rtrim($current_page,'/');
    $current_menu_item = str_replace('http://', '', $current_url);
    $current_menu_item = str_replace('https://', '', $current_menu_item);
    $current_menu_item = rtrim($current_menu_item,'/');
    $class = ($current_page == $current_menu_item) ? 'current-menu-item' : '';



    return $class;
}


/**
* Build the a sub nav menu if it exists
* @see WP_Widget::form()
* @param array $instance Previously saved values from database.
*/
public function buildSubNav($result, $thisNavitem) {

    $content = '';
    $current_menu_parent = '';
    //if sub items exist
    foreach($result->navitems as $navitem)
    {
        if($navitem->parent == $thisNavitem->wpid)
        {
            $content .= '<li class="menu-item menu-item-type-post_type menu-item-object-page menu-item-'.$navitem->wpid.'" id="menu-item-'.$navitem->wpid.'"><a href="'.$navitem->url.'">'.stripcslashes($navitem->title).'</a></li>';
            if($this->getCurrentClass($navitem->url) == 'current-menu-item')
            {
                $current_menu_parent = 'current-menu-parent';
            }
        }

    }

    //if this is a sub-nav item
    //give parent the current parent class -itme.


    return array($content, $current_menu_parent);
}

/**
* Build the menu template with retreved nav items
* @see WP_Widget::form()
* @param array $instance Previously saved values from database.
*/
public function buildSocial($result) {

    echo '<ul class="menu">';
    foreach($result->social_channels as $social_channel)
    {
        if($social_channel->type != '')
        {
            echo ' <li>
            <a data-ct="Custom:Social media link clicks:'.$social_channel->type.'" class="'.$social_channel->type.' ct-click-event" target="_blank" href="'.$social_channel->url.'"><i class="icon-'.$social_channel->type.'"></i>';
            echo ($social_channel->type == 'youtube') ? 'YouTube' : ucfirst($social_channel->type);
            echo '</a></li>';
        }
    }
    echo '</ul>';
}

/**
* Sanitize widget form values as they are saved.
* @see WP_Widget::update()
* @param array $new_instance Values just sent to be saved.
* @param array $old_instance Previously saved values from database.
* @return array Updated safe values to be saved.
*/
public function update( $new_instance, $old_instance ) {

    $instance = array();
    $instance['aws_url'] = ( ! empty( $new_instance['aws_url'] ) ) ? strip_tags( $new_instance['aws_url'] ) : '';

    return $instance;
}

/**
* Build the menu template with retreved nav items
* @see WP_Widget::form()
* @param array $instance Previously saved values from database.
*/
public function outputFooterMenu($result) {

    echo '<ul class="menu footer-navigation" id="menu-footer-menu">';

    if(isset($result->navitems))
    {
        foreach($result->navitems as $navitem)
        {
            if($navitem->location == 'footer')
            {
            echo '<li class="menu-item menu-item-type-post_type menu-item-object-page menu-item-'.$navitem->wpid.'" id="menu-item-'.$navitem->wpid.'"><a href="'.$navitem->url.'">'.stripcslashes($navitem->title).'</a></li>';
            }
        }
    }

    echo '</ul>';
}

/**
* Back-end widget form.
* @see WP_Widget::form()
* @param array $instance Previously saved values from database.
*/
public function getFooterItems($instance) {

    $fields = [];
    $lang =  explode('_', get_locale());
    $fields['language'] = strtolower($lang[0]);
    $fields['market_id'] = get_option('cpg_options_market_id');

    if(isset($instance['aws_url']))
    {
        $urlprefix = rtrim($instance['aws_url'],'/');
    }
    else
    {
        $urlprefix = 'https://s3-eu-west-1.amazonaws.com';
    }

    $url = $urlprefix.'/'.'menu-'.$fields['market_id'].'-'.$fields['language'].'.json';

    //open connection
    $ch = curl_init();
    //set the url, number of POST vars, POST data
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $result = curl_exec($ch);
    curl_close($ch);

    return $result;

    }
}

// register Foo_Widget widget
function register_universal_nav_sidebar_widget() {
    register_widget( 'Universal_Nav_Sidebar_Widget' );
}

add_action( 'widgets_init', 'register_universal_nav_sidebar_widget' );

