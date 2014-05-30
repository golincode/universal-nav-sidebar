<?php
/*
Plugin Name: DiG Universal Nav Widget
Plugin_URI: http://www.wearearchitect.com
Description: A plugin that allows DiG Admins to display a main navigation with Dig Campaigns
Version: 0.1.0
Author: Sam Woolner
Author URI: http://www.wearearchitect.com
License: GPL2
*/
class Universal_Nav_Menu_Widget extends WP_Widget {

  protected $urlprefix = 'https://s3-eu-west-1.amazonaws.com/cached-menus/';

  /**
   * Register widget with WordPress
   */
  function __construct() {
    parent::__construct(
      'universal_nav_menu_widget', // Base ID
      __('Universal Nav Menu', 'text_domain'), // Name
      array( 'description' => __( 'Universal Nav Menu Widget', 'text_domain' ), ) // Args
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

    if (!file_exists($cache)) {
        mkdir($cache, 0777, true);
    }
    $file = $cache . '/latest_universal_nav_items_'.ICL_LANGUAGE_CODE.'.txt';
    $current_time = time();
    $expire_time = 1 * 60 * 60;

    if(file_exists($file)) {
      $file_time = filemtime($file);
    }

    if(! file_exists($file) || ($current_time - $expire_time > $file_time)) {

      $result = $this->getItems();
      file_put_contents($file,$result);
    }
    else{

       $result = file_get_contents($file);
    }

    echo $args['before_widget'];

    if($result)
    {

      $result = json_decode($result);
      if($result->status == 1)
      {
          echo '<a style="background-image:url('.$result->logo.')" href="<?php echo home_url(\'/\'); ?>" class="logo">';
          bloginfo('name');
          echo '</a>';
          $this->buildMenu($result);
          echo '<div class="social-media-menu"><div class="title">';
          _e('Follow us on', 'dirtisgood');
          echo '</div>';
          $this->buildSocial($result);
          echo '</div>';
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

    echo $args['after_widget'];
  }

  /**
   * Back-end widget form.
   * @see WP_Widget::form()
   * @param array $instance Previously saved values from database.
   */
  public function getItems() {
    $fields = [];

    $fields['api_key'] = $options[2]['api_key'];
    $fields['language'] =  ICL_LANGUAGE_CODE;
    $fields['market_id'] = get_option('cpg_options_market_id');

    $url = $this->urlprefix.'menu-'.$fields['market_id'].'-'.$fields['language'].'.json';

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
          echo '<li id="menu-item-33" class="menu-item menu-itemmenu-item-'.$navitem->wpid.' '.$navitem->classes.'" id="menu-item-'.$navitem->wpid.'"><a href="'.$navitem->url.'">'.stripcslashes($navitem->title).'</li></a>';
          $this->buildSubNav($result, $navitem);
        } 
      }
    }

    echo '</ul>';
  }

  /**
   * Build the a sub nav menu if it exists
   * @see WP_Widget::form()
   * @param array $instance Previously saved values from database.
   */
  public function buildSubNav($result, $thisNavitem)
  {
    //if sub items exist
    echo '<ul class="sub-menu">';
      foreach($result->navitems as $navitem)
      {
        if($navitem->parent == $thisNavitem->wpid)
        {
            echo '<li class="menu-item menu-item-type-post_type menu-item-object-page menu-item-'.$thisNavitem->wpid.'" id="menu-item-'.$thisNavitem->wpid.'"><a href="http://'.$navitem->url.'"><img src="http://'.$navitem->image.'" class="sub-nav-image">'.stripcslashes($navitem->title).'</a></li>';
        }
      }
    echo '</ul>';
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
           echo ' <li><a href="'.$social_channel->name.'" target="_blank" class=" link-'.strtolower($social_channel->name).' ct-click-event" data-ct="Custom:Social media link clicks:'.$social_channel->name.'"><img src="'.$social_channel->image.'"><i class="icon-'.strtolower($social_channel->name).'"></i>'.$social_channel->name.'</a></li>';
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
    $instance['api_key'] = ( ! empty( $new_instance['api_key'] ) ) ? strip_tags( $new_instance['api_key'] ) : '';

    return $instance;
  }

}

// register Foo_Widget widget
function register_universal_nav_menu_widget() {
    register_widget( 'Universal_Nav_Menu_Widget' );
}
add_action( 'widgets_init', 'register_universal_nav_menu_widget' );

