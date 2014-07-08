<?php
/*
Plugin Name: DiG Universal Nav Sidebar Widget
Plugin_URI: http://www.wearearchitect.com
Description: A plugin that allows DiG Admins to display a main navigation with Dig Campaigns
Version: 0.1.0
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
      __('Universal Nav Sidebar', 'text_domain'), // Name
      array( 'description' => __( 'Universal Nav Sidebar Widget', 'text_domain' ), ) // Args
    );
  }

  /**
   * Front-end display of widget.
   * @see WP_Widget::widget()
   * @param array $args     Widget arguments.
   * @param array $instance Saved values from database.
   */
  public function widget( $args, $instance ) {



    $dir = explode('themes/', dirname(__FILE__));

    $cache = $dir[0] . '/cache';

    if ( !file_exists($cache) ) {
        mkdir($cache, 0755, true);
    }

    if(!is_writable($cache) )
    {
       chmod($cache, 0755);
    }

    $file = $cache . '/latest_universal_nav_items_'.substr(get_locale(),0,2).'.txt';
    $current_time = time();
    $expire_time = 1 * 60 * 60;

    if(file_exists($file)) {
      $file_time = filemtime($file);
    }

    if(! file_exists($file) || ($current_time - $expire_time > $file_time)) {

      $result = $this->getItems($instance);
      file_put_contents($file,$result);
    }
    else{

       $result = file_get_contents($file);
    }

   // echo $args['before_widget'];

    if($result)
    {

      $result = json_decode($result);
      if($result->status == 1)
      {
          echo '<a style="background-image:url('.$result->logo.')" href="" class="logo">';
          bloginfo('name');
          echo '</a><nav class="main-navigation">';
          $this->buildMenu($result);
          echo '<div class="social-media-menu"><div class="title">';
          _e('Follow us on', 'dirtisgood');
          echo '</div>';
          $this->buildSocial($result);
          echo '</div></nav>';

          $subNavImages = $this->cpg_get_subnav_images_array($result);

          $localisations = array(
            'subNavImages' => $subNavImages,
          );

          wp_localize_script( 'core', 'navigationData', $localisations );
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

    //echo $args['after_widget'];
  }


public function cpg_get_subnav_images_array($result) {

  $nav_images = array();

  if( is_array($result) ) {
    foreach($result as $menu_item)
    {
      if(isset($menu_item->image) && $menu_item->image != '')
      {
        $nav_images['menu-item-'.$menu_item->wp_id] = $menu_item->image;
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
  public function getItems($instance) {
    $fields = [];

    $fields['language'] =  substr(get_locale(),0,2);
    $fields['market_id'] = get_option('cpg_options_market_id');

    if(isset($instance['aws_url']))
    {
      $urlprefix = rtrim($instance['aws_url'],'/');
    }
    else
    {
      $urlprefix = 'https://s3-eu-west-1.amazonaws.com';
    }

    $url = $urlprefix.'/cached-menus/'.'menu-'.$fields['market_id'].'-'.$fields['language'].'.json';

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
          $content = $this->buildSubNav($result, $navitem);
          $parent_class = ($content != '' ? 'menu-item-has-children' : '');
          echo '<li id="menu-item-'.$navitem->wpid.'" class=" '.$parent_class.' menu-item menu-itemmenu-item-'.$navitem->wpid.' '.$navitem->classes.'" id="menu-item-'.$navitem->wpid.'"><a href="'.$navitem->url.'">'.stripcslashes($navitem->title).'</a>';
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

  // Widget Backend
  public function form( $instance ) {
    if ( isset( $instance[ 'aws_url' ] ) ) {
      $aws_url = $instance[ 'aws_url' ];
    }
    else {
      $aws_url = __( 'New AWL URL', 'wpb_widget_domain' );
    }
    // Widget admin form
    ?>
    <p>
    <label for="<?php echo $this->get_field_id( 'aws_url' ); ?>"><?php _e( 'AWS URL:' ); ?></label>
    <input class="widefat" id="<?php echo $this->get_field_id( 'aws_url' ); ?>" name="<?php echo $this->get_field_name( 'aws_url' ); ?>" type="text" value="<?php echo esc_attr( $aws_url ); ?>" />
    </p>
    <?php
  }

  /**
   * Build the a sub nav menu if it exists
   * @see WP_Widget::form()
   * @param array $instance Previously saved values from database.
   */
  public function buildSubNav($result, $thisNavitem)
  {
    $content = '';
    //if sub items exist
    foreach($result->navitems as $navitem)
    {
      if($navitem->parent == $thisNavitem->wpid)
      {
          $content .= '<li class="menu-item menu-item-type-post_type menu-item-object-page menu-item-'.$navitem->wpid.'" id="menu-item-'.$navitem->wpid.'"><a href="http://'.$navitem->url.'">'.stripcslashes($navitem->title).'</a></li>';
      }
    }
    return $content;
  }

  /**
   * Build the menu template with retreved nav items
   * @see WP_Widget::form()
   * @param array $instance Previously saved values from database.
   */
  public function buildSocial($result)
  {
     echo '<ul class="menu">';
     foreach($result->social_channels as $social_channel)
     {
        if($social_channel->name != '')
        {
           echo ' <li><a href="'.$social_channel->name.'" target="_blank" class=" link-'.strtolower($social_channel->name).' ct-click-event" data-ct="Custom:Social media link clicks:'.$social_channel->name.'"><img src="'.$social_channel->image.'">'.$social_channel->name.'</a></li>';
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

}

// register Foo_Widget widget
function register_universal_nav_sidebar_widget() {
    register_widget( 'Universal_Nav_Sidebar_Widget' );
}
add_action( 'widgets_init', 'register_universal_nav_sidebar_widget' );

